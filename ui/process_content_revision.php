<?php
// /process_content_revision.php
//
// Applies manual revisions for a single focus area in an analysis package,
// creating a new focus-area version, then copying old records to the new version.
// We now copy the display_order as well.

require_once 'includes/db_connection.php';
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit;
}

// Parse JSON
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$packageId  = isset($data['package_id']) ? (int)$data['package_id'] : 0;
$focusName  = isset($data['focus_area_name']) ? trim($data['focus_area_name']) : '';
$oldVersion = isset($data['current_focus_area_version_number']) ? (int)$data['current_focus_area_version_number'] : -1;
$records    = isset($data['records']) && is_array($data['records']) ? $data['records'] : [];

if ($packageId <= 0 || $focusName === '' || $oldVersion < 0 || empty($records)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid input data.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Find the focus area row
    $sqlFa = "
        SELECT fa.id, fa.current_analysis_package_focus_area_versions_id AS cur_ver_id
        FROM analysis_package_focus_areas fa
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name = :fname
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([':pkg' => $packageId, ':fname' => $focusName]);
    $faRow = $stmtFa->fetch(PDO::FETCH_ASSOC);
    if (!$faRow) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Focus area not found or is deleted.']);
        exit;
    }
    $focusAreaId     = (int)$faRow['id'];
    $currentVerId    = (int)$faRow['cur_ver_id'];

    // 2) Get the current version info
    $sqlVer = "
        SELECT v.id, v.focus_area_version_number
        FROM analysis_package_focus_area_versions v
        WHERE v.id = :vid
          AND v.analysis_package_focus_areas_id = :faid
        LIMIT 1
    ";
    $stmtVer = $pdo->prepare($sqlVer);
    $stmtVer->execute([':vid' => $currentVerId, ':faid' => $focusAreaId]);
    $verRow = $stmtVer->fetch(PDO::FETCH_ASSOC);
    if (!$verRow) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Current focus-area version not found.']);
        exit;
    }
    $actualVerNum = (int)$verRow['focus_area_version_number'];
    if ($oldVersion !== $actualVerNum) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'error' => 'Version mismatch. Please refresh and try again.'
        ]);
        exit;
    }

    // 3) Next version => old + 1
    $newVersionNum = $actualVerNum + 1;

    // 4) Create a new entry in analysis_package_focus_area_versions
    $newSummary = sprintf(
        "Manual revision of focus area '%s' to create version %d.",
        $focusName,
        $newVersionNum
    );
    $sqlNewVer = "
        INSERT INTO analysis_package_focus_area_versions
        (analysis_package_focus_areas_id, focus_area_version_number, focus_area_revision_summary, created_at)
        VALUES
        (:faid, :vernum, :summary, NOW())
    ";
    $stmtNV = $pdo->prepare($sqlNewVer);
    $stmtNV->execute([
        ':faid'   => $focusAreaId,
        ':vernum' => $newVersionNum,
        ':summary'=> $newSummary
    ]);
    $newVerId = (int)$pdo->lastInsertId();

    // 5) Copy all records from the old version to the new version, now including display_order
    $sqlCopy = "
        INSERT INTO analysis_package_focus_area_records
        (
          analysis_package_focus_area_versions_id,
          input_text_summaries_id,
          analysis_package_headers_id,
          grid_index,
          display_order, -- ADDED
          properties,
          is_deleted,
          created_at
        )
        SELECT
          :newVid,
          r.input_text_summaries_id,
          r.analysis_package_headers_id,
          r.grid_index,
          r.display_order, -- ADDED
          r.properties,
          r.is_deleted,
          r.created_at
        FROM analysis_package_focus_area_records r
        WHERE r.analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtCopy = $pdo->prepare($sqlCopy);
    $stmtCopy->execute([
        ':newVid' => $newVerId,
        ':oldVid' => (int)$verRow['id']
    ]);

    // 6) Build a map from (old record ID => newly inserted ID) if needed.
    // However, we see the code below updates new records by matching grid_index or similar.
    // We'll keep the same approach.

    // 7) Overwrite data in the new version for each user-submitted record
    $sqlOldRec = "
        SELECT grid_index, display_order, created_at
        FROM analysis_package_focus_area_records
        WHERE id = :oid
          AND analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtOldRec = $pdo->prepare($sqlOldRec);

    $sqlUpdate = "
        UPDATE analysis_package_focus_area_records
        SET is_deleted = :del,
            properties = :props,
            created_at = NOW()
        WHERE id = :nid
          AND analysis_package_focus_area_versions_id = :newVid
    ";
    $stmtUpd = $pdo->prepare($sqlUpdate);

    // For brand-new records, we do an insert with display_order = grid_index + 1
    $sqlGetMaxIdx = "
        SELECT COALESCE(MAX(grid_index), 0)
        FROM analysis_package_focus_area_records
        WHERE analysis_package_focus_area_versions_id = :nvid
    ";
    $stmtMaxI = $pdo->prepare($sqlGetMaxIdx);

    $sqlIns = "
        INSERT INTO analysis_package_focus_area_records
        (
          analysis_package_focus_area_versions_id,
          input_text_summaries_id,
          analysis_package_headers_id,
          grid_index,
          display_order, -- ADDED
          properties,
          is_deleted,
          created_at
        )
        VALUES
        (
          :vid,
          NULL,
          (SELECT analysis_package_headers_id
             FROM analysis_package_focus_area_records
             WHERE analysis_package_focus_area_versions_id = :vid
             LIMIT 1),
          :gdx,
          :dispOrd, -- ADDED
          :prp,
          :del,
          NOW()
        )
    ";
    $stmtIns = $pdo->prepare($sqlIns);

    foreach ($records as $rec) {
        $recId   = isset($rec['id']) ? (int)$rec['id'] : 0;
        $delFlag = isset($rec['is_deleted']) ? (int)$rec['is_deleted'] : 0;
        $propsObj= isset($rec['properties']) && is_array($rec['properties'])
                   ? $rec['properties'] : [];
        $propsJson= json_encode($propsObj);
        if ($recId > 0) {
            // Look up the old record's grid_index
            $stmtOldRec->execute([
                ':oid' => $recId,
                ':oldVid' => (int)$verRow['id']
            ]);
            $oldRow = $stmtOldRec->fetch(PDO::FETCH_ASSOC);
            if ($oldRow) {
                // Find the new record ID
                $keySql = "
                    SELECT id
                    FROM analysis_package_focus_area_records
                    WHERE analysis_package_focus_area_versions_id = :nVid
                      AND grid_index = :gdx
                      AND created_at = :cat
                    LIMIT 1
                ";
                $stmtKey = $pdo->prepare($keySql);
                $stmtKey->execute([
                    ':nVid' => $newVerId,
                    ':gdx'  => (int)$oldRow['grid_index'],
                    ':cat'  => $oldRow['created_at']
                ]);
                $newRec = $stmtKey->fetch(PDO::FETCH_ASSOC);
                if ($newRec) {
                    // Update that newly-copied row
                    $stmtUpd->execute([
                        ':del'  => $delFlag,
                        ':props'=> $propsJson,
                        ':nid'  => (int)$newRec['id'],
                        ':newVid' => $newVerId
                    ]);
                    continue;
                }
            }
        }
        // Otherwise, brand-new
        $stmtMaxI->execute([':nvid' => $newVerId]);
        $maxGrid = (int)$stmtMaxI->fetchColumn();
        $newIdx = $maxGrid + 1;
        // display_order = newIdx + 1
        $dispOrder = $newIdx + 1;

        $stmtIns->execute([
            ':vid'     => $newVerId,
            ':gdx'     => $newIdx,
            ':dispOrd' => $dispOrder,
            ':prp'     => $propsJson,
            ':del'     => $delFlag
        ]);
    }

    // 8) Update the focus area to reference the new current version
    $sqlUpdFa = "
        UPDATE analysis_package_focus_areas
        SET current_analysis_package_focus_area_versions_id = :nvid,
            updated_at = NOW()
        WHERE id = :faid
          AND is_deleted = 0
    ";
    $stmtUpdFa = $pdo->prepare($sqlUpdFa);
    $stmtUpdFa->execute([
        ':nvid' => $newVerId,
        ':faid' => $focusAreaId
    ]);

    $pdo->commit();
    echo json_encode([
        'status'    => 'success',
        'newVersion'=> $newVersionNum,
        'message'   => 'Focus area content revisions saved.'
    ]);

} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $ex->getMessage()]);
}
