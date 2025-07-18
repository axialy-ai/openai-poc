<?php
/****************************************************************************
 * /process_recover_focus_area.php
 *
 * Recovers a past focus-area version by:
 *   1) Locating the old version row matching focus_area_version_number = recover_version_num
 *   2) Creating a new version row with version_number = (the latest + 1)
 *   3) Copying that old version’s records into the new version
 *   4) Marking the new version as current
 *   5) Returning { status: 'success', new_version_number: ... }
 ****************************************************************************/
require_once 'includes/db_connection.php';
header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

// Extract
$packageId          = isset($data['package_id'])         ? (int)$data['package_id']         : 0;
$focusAreaName      = isset($data['focus_area_name'])     ? trim($data['focus_area_name'])   : '';
$currentVersionNum  = isset($data['current_version_num']) ? (int)$data['current_version_num']: 0;
$recoverVersionNum  = isset($data['recover_version_num']) ? (int)$data['recover_version_num']: -1;

// Here, we allow version 0
if ($packageId <= 0 || $focusAreaName === '' || $recoverVersionNum < 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid parameters.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Locate the focus area
    $sqlFa = "
        SELECT
          fa.id AS focusAreaId,
          fa.current_analysis_package_focus_area_versions_id AS currentVerId,
          fa.analysis_package_headers_id AS packageHeadersId
        FROM analysis_package_focus_areas fa
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name             = :faName
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([':pkg' => $packageId, ':faName' => $focusAreaName]);
    $faRow = $stmtFa->fetch(PDO::FETCH_ASSOC);
    if (!$faRow) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Focus area not found.']);
        exit;
    }
    $focusAreaId      = (int)$faRow['focusAreaId'];
    $packageHeadersId = (int)$faRow['packageHeadersId']; // needed for new version

    // 2) Find the version row with focus_area_version_number = recoverVersionNum
    $sqlOldV = "
        SELECT v.id AS oldVersionId,
               v.focus_area_version_number,
               v.focus_area_revision_summary
        FROM analysis_package_focus_area_versions v
        WHERE v.analysis_package_focus_areas_id = :faid
          AND v.focus_area_version_number       = :revNum
        LIMIT 1
    ";
    $stmtOld = $pdo->prepare($sqlOldV);
    $stmtOld->execute([':faid' => $focusAreaId, ':revNum' => $recoverVersionNum]);
    $oldV = $stmtOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldV) {
        $pdo->rollBack();
        echo json_encode(['status'=>'error','message'=>'No such version to recover.']);
        exit;
    }
    $oldVersionId = (int)$oldV['oldVersionId'];

    // 3) Find the latest version_number for that focus area => new version = max + 1
    $sqlMax = "
        SELECT MAX(focus_area_version_number)
        FROM analysis_package_focus_area_versions
        WHERE analysis_package_focus_areas_id = :faid
    ";
    $stmtMax = $pdo->prepare($sqlMax);
    $stmtMax->execute([':faid' => $focusAreaId]);
    $maxVerNum = (int)$stmtMax->fetchColumn();
    $newVersionNum = $maxVerNum + 1;

    // 4) Insert new version row
    $summaryText = sprintf(
        "Focus area '%s' recovered from version %d => new version %d.",
        $focusAreaName, $recoverVersionNum, $newVersionNum
    );

    // Fix for the NULL issue: we explicitly set analysis_package_headers_id
    $sqlInsV = "
        INSERT INTO analysis_package_focus_area_versions
        (
          analysis_package_headers_id,
          analysis_package_focus_areas_id,
          focus_area_version_number,
          focus_area_revision_summary,
          created_at
        )
        VALUES
        (
          :aphId,
          :faid,
          :verNum,
          :revSumm,
          NOW()
        )
    ";
    $stmtInsV = $pdo->prepare($sqlInsV);
    $stmtInsV->execute([
        ':aphId'  => $packageHeadersId, // ensures analysis_package_headers_id is not NULL
        ':faid'   => $focusAreaId,
        ':verNum' => $newVersionNum,
        ':revSumm'=> $summaryText
    ]);
    $newVerId = (int)$pdo->lastInsertId();

    // 5) Copy the old version’s records => new version
    $sqlOldRecs = "
        SELECT r.*
        FROM analysis_package_focus_area_records r
        WHERE r.analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtOR = $pdo->prepare($sqlOldRecs);
    $stmtOR->execute([':oldVid' => $oldVersionId]);
    $oldRecs = $stmtOR->fetchAll(PDO::FETCH_ASSOC);

    $sqlCopy = "
        INSERT INTO analysis_package_focus_area_records
        (
          analysis_package_headers_id,
          analysis_package_focus_areas_id,
          analysis_package_focus_area_versions_id,
          input_text_summaries_id,
          grid_index,
          display_order,
          properties,
          is_deleted,
          created_at
        )
        VALUES
        (
          :pkgHeadersId,
          :faid,
          :newVid,
          :its,
          :gdx,
          :disp,
          :props,
          :del,
          :cat
        )
    ";
    $stmtCopy = $pdo->prepare($sqlCopy);

    foreach ($oldRecs as $row) {
        $stmtCopy->execute([
            ':pkgHeadersId' => $row['analysis_package_headers_id'],
            ':faid'         => $row['analysis_package_focus_areas_id'],
            ':newVid'       => $newVerId,
            ':its'          => $row['input_text_summaries_id'],
            ':gdx'          => $row['grid_index'],
            ':disp'         => $row['display_order'],
            ':props'        => $row['properties'],
            ':del'          => $row['is_deleted'],
            ':cat'          => $row['created_at']
        ]);
    }

    // 6) Mark the new version as current
    $sqlSetCur = "
        UPDATE analysis_package_focus_areas
        SET current_analysis_package_focus_area_versions_id = :nvid,
            updated_at = NOW()
        WHERE id = :faid
    ";
    $stmtSC = $pdo->prepare($sqlSetCur);
    $stmtSC->execute([':nvid' => $newVerId, ':faid' => $focusAreaId]);

    $pdo->commit();

    echo json_encode([
        'status'            => 'success',
        'new_version_number'=> $newVersionNum
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
