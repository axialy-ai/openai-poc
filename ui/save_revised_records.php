<?php
/****************************************************************************
 * /save_revised_records.php
 *
 * COMPLETE, FULLY INTEGRATED FILE CONTENT
 *
 * Creates a new focus-area version by cloning the old version’s records
 * and applying user changes. Preserves each record’s original grid_index
 * (including for is_deleted=1 items) so that we do NOT recycle grid_index
 * if the user deletes a record. Also ensures that truly brand-new records
 * get a unique new grid_index which is 1 higher than any existing row
 * in the new version (after we have physically inserted the updated rows).
 *
 * Expected JSON payload:
 * {
 *   "package_id": 123,
 *   "focus_area_name": "Some Focus Area",
 *   "focus_area_version_id": 20,        // concurrency check against current_version
 *   "focus_area_records": [
 *     {
 *       "focusAreaRecordID": "214",     // or "new"
 *       "is_deleted": 1,
 *       "axia_properties": { ... },
 *       "input_text_summaries_id": 99   // optional
 *     },
 *     ...
 *   ],
 *   "summary_of_revisions": "...",
 *   "actionedFeedback": [ ... ]
 * }
 ***************************************************************************/

header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid method'
    ]);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid JSON payload'
    ]);
    exit;
}

// ----------------------------------------------------------------------
// Extract required fields
// ----------------------------------------------------------------------
$packageId       = isset($data['package_id'])            ? (int)$data['package_id'] : 0;
$faName          = isset($data['focus_area_name'])       ? trim($data['focus_area_name']) : '';
$versionId       = isset($data['focus_area_version_id']) ? (int)$data['focus_area_version_id'] : 0;
$records         = !empty($data['focus_area_records'])   ? $data['focus_area_records'] : [];
$summary         = isset($data['summary_of_revisions'])  ? trim($data['summary_of_revisions']) : '';
$actionedFdb     = !empty($data['actionedFeedback'])     ? $data['actionedFeedback'] : [];

if ($packageId <= 0 || $faName === '' || $versionId <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid parameters (package_id, focus_area_name, focus_area_version_id)'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ------------------------------------------------------------------
    // 1) Locate the focus area by package + name; concurrency check
    // ------------------------------------------------------------------
    $sqlFa = "
        SELECT
          fa.id AS focusAreaId,
          fa.current_analysis_package_focus_area_versions_id AS currentVerId,
          fa.analysis_package_headers_id AS faPkgId
        FROM analysis_package_focus_areas fa
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name = :fan
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([':pkg' => $packageId, ':fan' => $faName]);
    $faRow = $stmtFa->fetch(\PDO::FETCH_ASSOC);
    if (!$faRow) {
        $pdo->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Focus area not found'
        ]);
        exit;
    }
    $focusAreaId  = (int)$faRow['focusAreaId'];
    $currentVerId = (int)$faRow['currentVerId'];
    $faPkgId      = (int)$faRow['faPkgId'];

    if ($currentVerId !== $versionId) {
        $pdo->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Version mismatch. Please refresh and retry.'
        ]);
        exit;
    }

    // ------------------------------------------------------------------
    // 2) Get the numeric version => newNumericVer = old+1
    // ------------------------------------------------------------------
    $sqlNum = "
        SELECT focus_area_version_number
        FROM analysis_package_focus_area_versions
        WHERE id = :vid
          AND analysis_package_focus_areas_id = :faid
        LIMIT 1
    ";
    $stmtNum = $pdo->prepare($sqlNum);
    $stmtNum->execute([':vid' => $versionId, ':faid' => $focusAreaId]);
    $verRow = $stmtNum->fetch(\PDO::FETCH_ASSOC);
    if (!$verRow) {
        $pdo->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Focus-area version row not found'
        ]);
        exit;
    }
    $currentNumericVer = (int)$verRow['focus_area_version_number'];
    $newNumericVer     = $currentNumericVer + 1;

    // ------------------------------------------------------------------
    // 3) Create the new version row
    // ------------------------------------------------------------------
    $sqlNewVer = "
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
          :pkgid,
          :faid,
          :vernum,
          :revsum,
          NOW()
        )
    ";
    $stmtNewVer = $pdo->prepare($sqlNewVer);
    $stmtNewVer->execute([
        ':pkgid'  => $faPkgId,
        ':faid'   => $focusAreaId,
        ':vernum' => $newNumericVer,
        ':revsum' => $summary
    ]);
    $newVerId = (int)$pdo->lastInsertId();

    // ------------------------------------------------------------------
    // 4) Mark new version as current
    // ------------------------------------------------------------------
    $sqlUpdFa = "
        UPDATE analysis_package_focus_areas
        SET current_analysis_package_focus_area_versions_id = :nvid,
            updated_at = NOW()
        WHERE id = :faid
    ";
    $stmtUpdFa = $pdo->prepare($sqlUpdFa);
    $stmtUpdFa->execute([':nvid' => $newVerId, ':faid' => $focusAreaId]);

    // ------------------------------------------------------------------
    // We need to physically insert “updated” old records first, so that
    // their grid_index is recognized before we insert brand-new rows.
    // This means we skip the naive “copy all old rows except updated.”
    // Instead, we copy everything to the new version, then we will
    // apply user changes (UPDATE or brand-new INSERT) on top.
    // ------------------------------------------------------------------

    // Step A: Copy ALL old records from old version -> new version
    // so the new version starts with the identical set. Then user “updates” them.
    $sqlOld = "
        SELECT r.*
        FROM analysis_package_focus_area_records r
        WHERE r.analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtOld = $pdo->prepare($sqlOld);
    $stmtOld->execute([':oldVid' => $versionId]);
    $oldRows = $stmtOld->fetchAll(\PDO::FETCH_ASSOC);

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
          :vid,
          :its,
          :gdx,
          :disp,
          :prp,
          :del,
          :cat
        )
    ";
    $stmtCopy = $pdo->prepare($sqlCopy);

    // This map: old_id -> newlyInserted_id, so we can update them
    $mapOldIdToNewId = [];
    foreach ($oldRows as $row) {
        $stmtCopy->execute([
            ':pkg'  => $faPkgId,
            ':faid' => $focusAreaId,
            ':vid'  => $newVerId,
            ':its'  => $row['input_text_summaries_id'],
            ':gdx'  => $row['grid_index'],
            ':disp' => $row['display_order'],
            ':prp'  => $row['properties'],
            ':del'  => $row['is_deleted'],
            ':cat'  => $row['created_at']
        ]);
        $newId = (int)$pdo->lastInsertId();
        $mapOldIdToNewId[$row['id']] = $newId;
    }

    // ------------------------------------------------------------------
    // Step B: For each user-changed record:
    //   1) If numeric => update the newly inserted row with new "is_deleted", properties
    //   2) If 'new' => brand-new insert with fresh grid_index
    // ------------------------------------------------------------------

    // a) We'll do an UPDATE on existing newVersion rows
    $sqlUpdate = "
        UPDATE analysis_package_focus_area_records
        SET
          input_text_summaries_id = :its,
          properties             = :prp,
          is_deleted            = :del,
          created_at            = NOW()
        WHERE id = :newId
          AND analysis_package_focus_area_versions_id = :vid
    ";
    $stmtUpd = $pdo->prepare($sqlUpdate);

    // b) We do a brand-new INSERT for truly new record
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
          :vid,
          :its,
          :gdx,
          :disp,
          :prp,
          :del,
          NOW()
        )
    ";
    $stmtIns = $pdo->prepare($sqlIns);

    // We'll do the “UPDATE old row” first, ignoring brand-new for a moment
    $brandNewRows = [];
    foreach ($records as $rec) {
        $recIdVal = isset($rec['focusAreaRecordID']) ? trim($rec['focusAreaRecordID']) : 'new';
        $delVal   = !empty($rec['is_deleted']) ? (int)$rec['is_deleted'] : 0;

        // JSON-encode user properties
        $propsJson = '{}';
        if (!empty($rec['axia_properties']) && is_array($rec['axia_properties'])) {
            $propsJson = json_encode($rec['axia_properties']);
        }

        // If numeric => user is updating an existing row
        if (ctype_digit($recIdVal)) {
            $oldRecId = (int)$recIdVal;
            // We have the newly inserted ID via map
            if (!empty($mapOldIdToNewId[$oldRecId])) {
                $newlyInsertedId = $mapOldIdToNewId[$oldRecId];
                // Possibly input_text_summaries_id
                $itsVal = null;
                if (!empty($rec['input_text_summaries_id']) && ctype_digit((string)$rec['input_text_summaries_id'])) {
                    $itsVal = (int)$rec['input_text_summaries_id'];
                } else {
                    // keep the old value => fetch from the old row
                    $oldRow = null;
                    foreach ($oldRows as $rr) {
                        if ((int)$rr['id'] === $oldRecId) {
                            $oldRow = $rr;
                            break;
                        }
                    }
                    if ($oldRow) {
                        $itsVal = $oldRow['input_text_summaries_id'];
                    }
                }
                // do the UPDATE
                $stmtUpd->execute([
                    ':its' => $itsVal,
                    ':prp' => $propsJson,
                    ':del' => $delVal,
                    ':newId'=> $newlyInsertedId,
                    ':vid' => $newVerId
                ]);
            }
            // else no old row => skip (rare edge case)
        } else {
            // brand-new
            $brandNewRows[] = $rec;
        }
    }

    // c) Now handle truly brand-new records => we do a fresh grid_index
    //    by scanning the new version's current max grid_index
    $sqlMax = "
        SELECT COALESCE(MAX(grid_index), 0) AS max_idx
        FROM analysis_package_focus_area_records
        WHERE analysis_package_focus_area_versions_id = :vid
    ";
    $stmtMax = $pdo->prepare($sqlMax);

    foreach ($brandNewRows as $bn) {
        $stmtMax->execute([':vid' => $newVerId]);
        $maxRow  = $stmtMax->fetch(\PDO::FETCH_ASSOC);
        $maxIdx  = (int)$maxRow['max_idx'];
        $newIdx  = $maxIdx + 1;

        $delVal = !empty($bn['is_deleted']) ? (int)$bn['is_deleted'] : 0;
        $propsJson = '{}';
        if (!empty($bn['axia_properties']) && is_array($bn['axia_properties'])) {
            $propsJson = json_encode($bn['axia_properties']);
        }
        $itsVal = null;
        if (!empty($bn['input_text_summaries_id']) && ctype_digit((string)$bn['input_text_summaries_id'])) {
            $itsVal = (int)$bn['input_text_summaries_id'];
        }
        // We guess display_order => newIdx + 1, or you can do newIdx as well
        $stmtIns->execute([
            ':pkg'  => $faPkgId,
            ':faid' => $focusAreaId,
            ':vid'  => $newVerId,
            ':its'  => $itsVal,
            ':gdx'  => $newIdx,
            ':disp' => ($newIdx + 1),
            ':prp'  => $propsJson,
            ':del'  => $delVal
        ]);
    }

    // ------------------------------------------------------------------
    // 8) Mark any feedback as resolved
    // ------------------------------------------------------------------
    $sqlItem = "
        UPDATE stakeholder_itemized_feedback
        SET resolved_at = NOW(),
            resolved_action = :ra
        WHERE id = :recId
          AND resolved_at IS NULL
    ";
    $stmtItem = $pdo->prepare($sqlItem);

    $sqlGen = "
        UPDATE stakeholder_general_feedback
        SET resolved_at = NOW(),
            resolved_action = :ra
        WHERE id = :detailId
          AND resolved_at IS NULL
    ";
    $stmtGen = $pdo->prepare($sqlGen);

    foreach ($actionedFdb as $fb) {
        $actionVal = !empty($fb['action']) ? $fb['action'] : 'Resolved';
        $src       = isset($fb['feedbackSource']) ? $fb['feedbackSource'] : '';
        if ($src === 'itemizedFeedback') {
            if (!empty($fb['itemizedFeedbackRecordID'])) {
                $idListRaw = explode(',', (string)$fb['itemizedFeedbackRecordID']);
                foreach ($idListRaw as $idStr) {
                    $fid = (int)trim($idStr);
                    if ($fid > 0) {
                        $stmtItem->execute([
                            ':ra'   => $actionVal,
                            ':recId'=> $fid
                        ]);
                    }
                }
            }
        } else {
            // generalFeedback
            if (!empty($fb['generalFeedbackRecordID'])) {
                $gfId = (int)$fb['generalFeedbackRecordID'];
                if ($gfId > 0) {
                    $stmtGen->execute([
                        ':ra'      => $actionVal,
                        ':detailId'=> $gfId
                    ]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // 9) commit
    // ------------------------------------------------------------------
    $pdo->commit();

    echo json_encode([
        'status'            => 'success',
        'new_version_row_id'=> $newVerId,
        'new_version_number'=> $newNumericVer
    ]);

} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[save_revised_records] Exception => '.$ex->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
