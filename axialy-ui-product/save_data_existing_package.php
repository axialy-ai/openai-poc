<?php
/**
 * save_data_existing_package.php
 *
 * Takes a JSON payload that includes:
 *   - package_id (the existing package)
 *   - collectedData (array of focus areas from ribbons)
 *   - input_text_summaries_id (optional, used to link input_text_summaries)
 *
 * For each distinct focus_area_label in collectedData, either:
 *   - Find the existing focus area. If found, create new version => add records
 *   - Otherwise, create a brand-new focus area => version 0 => add records
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
requireAuth();

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/db_connection.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status'=>'error','message'=>'Method not allowed']);
    exit;
}

// Parse JSON
$raw = file_get_contents('php://input');
$data= json_decode($raw,true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
    exit;
}

// Validate
$packageId       = isset($data['package_id']) ? (int)$data['package_id'] : 0;
$collectedData   = isset($data['collectedData']) && is_array($data['collectedData'])
                     ? $data['collectedData'] : [];
$inputSummaries  = isset($data['input_text_summaries_id']) ? $data['input_text_summaries_id'] : [];

if ($packageId <= 0 || empty($collectedData)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Check that the package is not deleted
    $sqlChk = "
      SELECT id
      FROM analysis_package_headers
      WHERE id = :pid
        AND is_deleted = 0
      LIMIT 1
    ";
    $stmtChk= $pdo->prepare($sqlChk);
    $stmtChk->execute([':pid'=>$packageId]);
    $rowPkg = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if (!$rowPkg) {
        throw new Exception("Package #{$packageId} not found or deleted.");
    }

    /**
     * Utility function:
     * Creates a brand new focus area => version 0 => inserts records
     */
    function createNewFocusArea(PDO $pdo, int $pkgId, string $faName, array $records, $inputSummaries) {
        // Insert into analysis_package_focus_areas
        $stmtFA = $pdo->prepare("
            INSERT INTO analysis_package_focus_areas
              (analysis_package_headers_id, focus_area_name, is_deleted, created_at)
            VALUES
              (:pkg, :fan, 0, NOW())
        ");
        $stmtFA->execute([
            ':pkg' => $pkgId,
            ':fan' => $faName
        ]);
        $faId = (int)$pdo->lastInsertId();

        // Create version 0
        $stmtFav= $pdo->prepare("
          INSERT INTO analysis_package_focus_area_versions
            (analysis_package_headers_id, analysis_package_focus_areas_id,
             focus_area_version_number, focus_area_revision_summary, created_at)
          VALUES
            (:pkg, :faid, 0, 'Initial version from existing-package save', NOW())
        ");
        $stmtFav->execute([
            ':pkg' => $pkgId,
            ':faid'=> $faId
        ]);
        $favId = (int)$pdo->lastInsertId();

        // Update the focus area’s current version
        $stmtUpd= $pdo->prepare("
          UPDATE analysis_package_focus_areas
             SET current_analysis_package_focus_area_versions_id = :vid
           WHERE id = :faid
        ");
        $stmtUpd->execute([
            ':vid' => $favId,
            ':faid'=> $faId
        ]);

        // Insert the records
        $stmtRec= $pdo->prepare("
          INSERT INTO analysis_package_focus_area_records
            (analysis_package_headers_id, analysis_package_focus_areas_id,
             analysis_package_focus_area_versions_id, input_text_summaries_id,
             grid_index, display_order, properties, is_deleted, created_at)
          VALUES
            (:pkg, :faid, :favid, :its, :gdx, :disp, :prp, 0, NOW())
        ");
        $gridIndex = 0;
        foreach ($records as $r) {
            $itsVal = !empty($r['input_text_summaries_id']) ? (int)$r['input_text_summaries_id'] : null;
            $props = isset($r['properties']) ? json_encode($r['properties']) : '{}';

            $stmtRec->execute([
                ':pkg'   => $pkgId,
                ':faid'  => $faId,
                ':favid' => $favId,
                ':its'   => $itsVal,
                ':gdx'   => $gridIndex,
                ':disp'  => ($gridIndex+1),
                ':prp'   => $props
            ]);
            $gridIndex++;
        }
    }

    /**
     * Utility function:
     * For an *existing* focus area => create next version => copy old records => insert new records
     */
    function addNewVersionToExistingFocusArea(PDO $pdo, int $pkgId, array $faRow, array $newRecords) {
        $focusAreaId = (int)$faRow['id'];
        $currentVerId= (int)$faRow['current_analysis_package_focus_area_versions_id'];

        // Find the current version's number
        $sqlVer = "
          SELECT focus_area_version_number
          FROM analysis_package_focus_area_versions
          WHERE id = :vid
            AND analysis_package_focus_areas_id = :faid
          LIMIT 1
        ";
        $stmtV = $pdo->prepare($sqlVer);
        $stmtV->execute([':vid'=>$currentVerId, ':faid'=>$focusAreaId]);
        $vRow = $stmtV->fetch(PDO::FETCH_ASSOC);
        $oldVerNum = $vRow ? (int)$vRow['focus_area_version_number'] : 0;
        $newVerNum = $oldVerNum + 1;

        // Create the new version
        $stmtInsV= $pdo->prepare("
          INSERT INTO analysis_package_focus_area_versions
            (analysis_package_headers_id, analysis_package_focus_areas_id,
             focus_area_version_number, focus_area_revision_summary, created_at)
          VALUES
            (:pkg, :faid, :vnum, 'Appended from existing-package save', NOW())
        ");
        $stmtInsV->execute([
            ':pkg'  => $pkgId,
            ':faid' => $focusAreaId,
            ':vnum' => $newVerNum
        ]);
        $newVerId= (int)$pdo->lastInsertId();

        // Mark the focus area => new current
        $stmtUpdFA= $pdo->prepare("
          UPDATE analysis_package_focus_areas
             SET current_analysis_package_focus_area_versions_id = :nvid
           WHERE id = :faid
        ");
        $stmtUpdFA->execute([
            ':nvid'=>$newVerId,
            ':faid'=>$focusAreaId
        ]);

        // Copy old records into the new version
        $sqlOld = "
          SELECT *
          FROM analysis_package_focus_area_records
          WHERE analysis_package_focus_area_versions_id = :oldVid
        ";
        $stmtOld= $pdo->prepare($sqlOld);
        $stmtOld->execute([':oldVid'=>$currentVerId]);
        $oldRecs = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

        $sqlCopy= "
          INSERT INTO analysis_package_focus_area_records
            (analysis_package_headers_id, analysis_package_focus_areas_id,
             analysis_package_focus_area_versions_id, input_text_summaries_id,
             grid_index, display_order, properties, is_deleted, created_at)
          VALUES
            (:pkg, :faid, :nvid, :its, :gdx, :disp, :prp, :del, :cat)
        ";
        $stmtCopy= $pdo->prepare($sqlCopy);
        foreach($oldRecs as $or) {
            $stmtCopy->execute([
                ':pkg' => $or['analysis_package_headers_id'],
                ':faid'=> $or['analysis_package_focus_areas_id'],
                ':nvid'=> $newVerId,
                ':its' => $or['input_text_summaries_id'],
                ':gdx' => $or['grid_index'],
                ':disp'=> $or['display_order'],
                ':prp' => $or['properties'],
                ':del' => $or['is_deleted'],
                ':cat' => $or['created_at']
            ]);
        }

        // Insert the brand-new records into that newly created version
        $stmtMax= $pdo->prepare("
          SELECT COALESCE(MAX(grid_index),0)
          FROM analysis_package_focus_area_records
          WHERE analysis_package_focus_area_versions_id = :vid
        ");
        $sqlIns= "
          INSERT INTO analysis_package_focus_area_records
            (analysis_package_headers_id, analysis_package_focus_areas_id,
             analysis_package_focus_area_versions_id, input_text_summaries_id,
             grid_index, display_order, properties, is_deleted, created_at)
          VALUES
            (:pkg, :faid, :nvid, :its, :gdx, :disp, :prp, 0, NOW())
        ";
        $stmtIns= $pdo->prepare($sqlIns);

        foreach($newRecords as $nr) {
            // find the current max grid_index in the new version
            $stmtMax->execute([':vid'=>$newVerId]);
            $mx = (int)$stmtMax->fetchColumn();
            $gdx= $mx+1;

            $itsVal = !empty($nr['input_text_summaries_id']) ? (int)$nr['input_text_summaries_id'] : null;
            $propsJson = isset($nr['properties']) ? json_encode($nr['properties']) : '{}';

            $stmtIns->execute([
                ':pkg'  => $pkgId,
                ':faid' => $focusAreaId,
                ':nvid' => $newVerId,
                ':its'  => $itsVal,
                ':gdx'  => $gdx,
                ':disp' => ($gdx+1),
                ':prp'  => $propsJson
            ]);
        }
    }

    // 2) Group the collectedData by focus_area_label
    $map = [];
    foreach($collectedData as $item) {
        $faLabel = isset($item['focus_area_label']) ? trim($item['focus_area_label']) : 'Untitled';
        if (!isset($map[$faLabel])) {
            $map[$faLabel] = [];
        }
        // Each $item can contain multiple sub-records; merge them in
        $recordList = [];
        if (!empty($item['focusAreaRecords'])) {
            $recordList = array_merge($recordList, $item['focusAreaRecords']);
        }
        if (!empty($item['stakeholderRecords'])) {
            $recordList = array_merge($recordList, $item['stakeholderRecords']);
        }
        if (empty($recordList) && !empty($item['properties']) && is_array($item['properties'])) {
            // single-record fallback
            $recordList[] = [ 'properties' => $item['properties'] ];
        }
        $map[$faLabel] = array_merge($map[$faLabel], $recordList);
    }

    // 3) For each distinct focus_area_label => check if it exists => new version or brand new
    $sqlFindFa = "
      SELECT id, current_analysis_package_focus_area_versions_id
      FROM analysis_package_focus_areas
      WHERE analysis_package_headers_id = :pid
        AND focus_area_name = :fan
        AND is_deleted = 0
      LIMIT 1
    ";
    $stmtFindFa= $pdo->prepare($sqlFindFa);

    foreach($map as $faName => $faRecords) {
        $stmtFindFa->execute([':pid'=>$packageId, ':fan'=>$faName]);
        $existing = $stmtFindFa->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            // Create brand new focus area => version 0
            createNewFocusArea($pdo, $packageId, $faName, $faRecords, $inputSummaries);
        } else {
            // Add new version to the existing focus area
            addNewVersionToExistingFocusArea($pdo, $packageId, $existing, $faRecords);
        }
    }

    $pdo->commit();
    echo json_encode([
        'status'   => 'success',
        'message'  => "Data saved into existing package #{$packageId}."
    ]);
} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
