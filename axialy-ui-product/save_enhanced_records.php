<?php
// /save_enhanced_records.php
//
// Handles the "Enhance Content" flow.
// Creates a new focus-area version, copies existing records, and updates them,
// or inserts brand-new ones if focusAreaRecordID is "new" or empty.
//
// The front-end provides:
//   package_id,
//   focus_area_name,
//   focus_area_version_id (for concurrency checks),
//   focus_area_records => array of record changes,
//   summary_of_revisions => textual summary
//
// We confirm that the focus_area_version_id matches the DB's latest version row ID.
// If it matches, we create a new version row (with version_number = old + 1),
// copy old records, then apply changes (UPDATE or brand-new INSERT).
// This time, we ensure we populate `analysis_package_focus_areas_id` and
// `analysis_package_focus_area_versions.analysis_package_headers_id` with the
// correct values so they never remain NULL.

require_once __DIR__ . '/includes/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 1) Extract the needed data fields
$pkgId    = isset($data['package_id'])            ? (int)$data['package_id']            : 0;
$faName   = isset($data['focus_area_name'])       ? trim($data['focus_area_name'])       : '';
$verRowId = isset($data['focus_area_version_id']) ? (int)$data['focus_area_version_id']  : 0;
$recs     = !empty($data['focus_area_records']) && is_array($data['focus_area_records'])
              ? $data['focus_area_records']
              : [];
$summary  = isset($data['summary_of_revisions'])  ? trim($data['summary_of_revisions'])  : '';

if ($pkgId <= 0 || $faName === '' || $verRowId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ------------------------------------------------------------------
    // 2) Locate the focus area row (by package + name)
    // ------------------------------------------------------------------
    // We also need the analysis_package_headers_id from that row so we can
    // correctly populate the new version’s “analysis_package_headers_id”.
    $sqlFA = "
        SELECT fa.id,
               fa.analysis_package_headers_id, -- [ADDED LINE]
               fa.current_analysis_package_focus_area_versions_id AS cur_vid
          FROM analysis_package_focus_areas fa
         WHERE fa.analysis_package_headers_id = :pkg
           AND fa.focus_area_name = :fan
           AND fa.is_deleted = 0
         LIMIT 1
    ";
    $stmtFA = $pdo->prepare($sqlFA);
    $stmtFA->execute([
        ':pkg' => $pkgId,
        ':fan' => $faName
    ]);
    $faRow = $stmtFA->fetch(PDO::FETCH_ASSOC);
    if (!$faRow) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Focus area not found']);
        exit;
    }
    $focusAreaId      = (int)$faRow['id'];
    $focusAreaHdrId   = (int)$faRow['analysis_package_headers_id']; // [ADDED LINE]

    // ------------------------------------------------------------------
    // 3) Retrieve the "latest" version row for concurrency checks
    // ------------------------------------------------------------------
    $sqlVer = "
        SELECT v.id, v.focus_area_version_number
          FROM analysis_package_focus_area_versions v
         WHERE v.analysis_package_focus_areas_id = :faid
         ORDER BY v.focus_area_version_number DESC
         LIMIT 1
    ";
    $stmtV = $pdo->prepare($sqlVer);
    $stmtV->execute([':faid' => $focusAreaId]);
    $vRow = $stmtV->fetch(PDO::FETCH_ASSOC);
    if (!$vRow) {
        $pdo->rollBack();
        echo json_encode(['error' => 'No existing version found']);
        exit;
    }
    $latestRowId      = (int)$vRow['id'];
    $latestVersionNum = (int)$vRow['focus_area_version_number'];

    // concurrency check
    if ($verRowId !== $latestRowId) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Version mismatch. Please refresh and try again.']);
        exit;
    }

    // ------------------------------------------------------------------
    // 4) Create the next version row
    // ------------------------------------------------------------------
    $newVerNum = $latestVersionNum + 1;
    $sqlNewVer = "
        INSERT INTO analysis_package_focus_area_versions
        (
          analysis_package_headers_id,  -- [ADDED LINE]
          analysis_package_focus_areas_id,
          focus_area_version_number,
          focus_area_revision_summary,
          created_at
        )
        VALUES
        (
          :aphId,   -- [ADDED LINE]
          :faid,
          :vernum,
          :summary,
          NOW()
        )
    ";
    $stmtNV = $pdo->prepare($sqlNewVer);
    $stmtNV->execute([
        ':aphId'  => $focusAreaHdrId, // [ADDED LINE]
        ':faid'   => $focusAreaId,
        ':vernum' => $newVerNum,
        ':summary'=> $summary
    ]);
    $newVerId = (int)$pdo->lastInsertId();

    // Mark the focus area as using the new version
    $sqlUFa = "
        UPDATE analysis_package_focus_areas
           SET current_analysis_package_focus_area_versions_id = :nvid
         WHERE id = :faid
    ";
    $stmtUF = $pdo->prepare($sqlUFa);
    $stmtUF->execute([
        ':nvid' => $newVerId,
        ':faid' => $focusAreaId
    ]);

    // ------------------------------------------------------------------
    // 5) Copy old records from old version => new version
    // ------------------------------------------------------------------
    $sqlOldRec = "
        SELECT r.*
          FROM analysis_package_focus_area_records r
         WHERE r.analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtOR = $pdo->prepare($sqlOldRec);
    $stmtOR->execute([':oldVid' => $latestRowId]);
    $oldRecords = $stmtOR->fetchAll(PDO::FETCH_ASSOC);

    // Insert copies, referencing the new version
    // and set analysis_package_focus_areas_id = $focusAreaId
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
          :pkg,
          :faid,
          :fav,
          :its,
          :gdx,
          :disp,
          :prp,
          :del,
          :cat
        )
    ";
    $stmtCopy = $pdo->prepare($sqlCopy);

    // We'll map old row ID => new row ID
    $mapOldIdToNewId = [];

    foreach ($oldRecords as $or) {
        $stmtCopy->execute([
            ':pkg'  => $or['analysis_package_headers_id'],
            ':faid' => $focusAreaId, // we do NOT leave it null
            ':fav'  => $newVerId,
            ':its'  => $or['input_text_summaries_id'],
            ':gdx'  => $or['grid_index'],
            ':disp' => $or['display_order'],
            ':prp'  => $or['properties'],
            ':del'  => $or['is_deleted'],
            ':cat'  => $or['created_at']
        ]);
        $newlyInsertedId = (int)$pdo->lastInsertId();
        $mapOldIdToNewId[$or['id']] = $newlyInsertedId;
    }

    // ------------------------------------------------------------------
    // 6) Update or Insert the user-submitted record changes
    // ------------------------------------------------------------------
    // For existing records => we look up the newly inserted row in $mapOldIdToNewId
    // For brand-new => we do a new INSERT with a fresh grid_index
    // setting analysis_package_focus_areas_id = $focusAreaId
    // a) We'll do an UPDATE on existing newVersion rows
    $sqlUpd = "
        UPDATE analysis_package_focus_area_records
           SET properties = :prp,
               is_deleted = :del,
               created_at = NOW()
         WHERE id = :rid
           AND analysis_package_focus_area_versions_id = :nvid
    ";
    $stmtUpd = $pdo->prepare($sqlUpd);

    // b) We'll do an INSERT for brand-new items
    $sqlMax = "
        SELECT COALESCE(MAX(grid_index), 0) AS max_idx
          FROM analysis_package_focus_area_records
         WHERE analysis_package_focus_area_versions_id = :nvid
    ";
    $stmtMax = $pdo->prepare($sqlMax);

    $sqlIns = "
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
          :pkg,
          :faid,
          :nvid,
          NULL,
          :gdx,
          :disp,
          :prp,
          :del,
          NOW()
        )
    ";
    $stmtIns = $pdo->prepare($sqlIns);

    // We'll find a valid package_headers_id by looking up any row from the new version
    // or we can retrieve from the original oldRecords if they're guaranteed to have
    // the same analysis_package_headers_id. E.g.:
    $anyOldRow             = reset($oldRecords);
    $pkgHeaderIdForInserts = $anyOldRow ? (int)$anyOldRow['analysis_package_headers_id'] : 0;

    foreach ($recs as $rec) {
        $recIdStr = isset($rec['focusAreaRecordID']) ? trim($rec['focusAreaRecordID']) : 'new';
        $isDel    = !empty($rec['is_deleted']) ? (int)$rec['is_deleted'] : 0;
        $propArr  = !empty($rec['axia_properties']) && is_array($rec['axia_properties'])
                        ? $rec['axia_properties']
                        : [];
        $propsJson = json_encode($propArr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        if (ctype_digit($recIdStr)) {
            // existing record => update the newly inserted row
            $oldId = (int)$recIdStr;
            if (!empty($mapOldIdToNewId[$oldId])) {
                $newlyId = $mapOldIdToNewId[$oldId];
                $stmtUpd->execute([
                    ':prp'  => $propsJson,
                    ':del'  => $isDel,
                    ':rid'  => $newlyId,
                    ':nvid' => $newVerId
                ]);
            }
        } else {
            // brand-new => find next grid_index
            $stmtMax->execute([':nvid' => $newVerId]);
            $maxIdxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $maxIdx    = (int)$maxIdxRow['max_idx'];
            $newGdx    = $maxIdx + 1;
            $dispOrd   = $newGdx + 1;

            $stmtIns->execute([
                ':pkg'  => $pkgHeaderIdForInserts,
                ':faid' => $focusAreaId, // do NOT leave null
                ':nvid' => $newVerId,
                ':gdx'  => $newGdx,
                ':disp' => $dispOrd,
                ':prp'  => $propsJson,
                ':del'  => $isDel
            ]);
        }
    }

    // ------------------------------------------------------------------
    // 7) Commit
    // ------------------------------------------------------------------
    $pdo->commit();
    echo json_encode([
        'status'    => 'success',
        'newVersion'=> $newVerNum,
        'message'   => 'Enhanced records saved successfully.'
    ]);

} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error'=> $ex->getMessage()]);
}
