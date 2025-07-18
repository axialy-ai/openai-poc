<?php
/****************************************************************************
 * /process_delete_focus_area_data.php
 *
 * Handles removing (deleting) a focus area by:
 *   1) Checking concurrency against focus_area_version_id
 *   2) Creating a new "analysis_package_focus_area_versions" row
 *      with a revision summary describing the removal
 *   3) Copying old version’s records to the new version, marking them is_deleted=1
 *   4) Marking the focus area itself is_deleted=1
 *
 * Expects JSON payload:
 * {
 *   "focus_area_name": "SomeFocusArea",
 *   "package_id": 123,
 *   "focus_area_version_id": 10
 * }
 ****************************************************************************/

require_once __DIR__ . '/includes/db_connection.php';
header('Content-Type: application/json');

// Optional debug logger
function logData($info) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/process_delete_focus_area_data.log';
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' - ' . print_r($info, true) . "\n",
        FILE_APPEND
    );
}

// Only POST is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Parse incoming JSON
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Extract the fields with the updated naming conventions
$focusAreaName       = isset($data['focus_area_name'])       ? trim($data['focus_area_name']) : '';
$packageId           = isset($data['package_id'])            ? (int)$data['package_id']       : 0;
$focusAreaVersionRow = isset($data['focus_area_version_id']) ? (int)$data['focus_area_version_id'] : 0;

if ($focusAreaName === '' || $packageId <= 0 || $focusAreaVersionRow <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing input data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ----------------------------------------------------------------------
    // 1) Locate the focus area row
    // ----------------------------------------------------------------------
    $sqlFA = "
        SELECT
          fa.id AS focusAreaId,
          fa.analysis_package_headers_id,
          fa.current_analysis_package_focus_area_versions_id AS curVerId
        FROM analysis_package_focus_areas fa
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name = :fan
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFA = $pdo->prepare($sqlFA);
    $stmtFA->execute([
        ':pkg' => $packageId,
        ':fan' => $focusAreaName
    ]);
    $faRow = $stmtFA->fetch(\PDO::FETCH_ASSOC);

    if (!$faRow) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Focus area not found or already deleted']);
        exit;
    }

    $focusAreaId  = (int)$faRow['focusAreaId'];
    $currentVerId = (int)$faRow['curVerId'];

    // ----------------------------------------------------------------------
    // 2) Concurrency check => user’s focus_area_version_id must match current
    // ----------------------------------------------------------------------
    if ($focusAreaVersionRow !== $currentVerId) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Version mismatch. Please refresh and try again.']);
        exit;
    }

    // ----------------------------------------------------------------------
    // 3) Find the numeric version number
    // ----------------------------------------------------------------------
    $sqlGetVerNum = "
        SELECT focus_area_version_number
        FROM analysis_package_focus_area_versions
        WHERE id = :verId
          AND analysis_package_focus_areas_id = :faid
        LIMIT 1
    ";
    $stmtVN = $pdo->prepare($sqlGetVerNum);
    $stmtVN->execute([
        ':verId' => $currentVerId,
        ':faid'  => $focusAreaId
    ]);
    $verRow = $stmtVN->fetch(\PDO::FETCH_ASSOC);
    if (!$verRow) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Focus-area version row not found.']);
        exit;
    }

    $oldVersionNum = (int)$verRow['focus_area_version_number'];
    $newVersionNum = $oldVersionNum + 1;

    // ----------------------------------------------------------------------
    // 4) Create the new focus-area version row
    // ----------------------------------------------------------------------
    $summaryText = sprintf(
        "Focus area '%s' was removed in new version %d.",
        $focusAreaName,
        $newVersionNum
    );

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
          :summary,
          NOW()
        )
    ";
    $stmtNV = $pdo->prepare($sqlNewVer);
    $stmtNV->execute([
        ':pkgid'   => $faRow['analysis_package_headers_id'],
        ':faid'    => $focusAreaId,
        ':vernum'  => $newVersionNum,
        ':summary' => $summaryText
    ]);
    $newVerId = (int)$pdo->lastInsertId();

    // Mark this new version as current, and mark the focus area itself is_deleted=1
    $sqlUpdFA = "
        UPDATE analysis_package_focus_areas
        SET current_analysis_package_focus_area_versions_id = :nvid,
            is_deleted = 1,
            updated_at = NOW()
        WHERE id = :faid
    ";
    $stmtUpdFA = $pdo->prepare($sqlUpdFA);
    $stmtUpdFA->execute([
        ':nvid' => $newVerId,
        ':faid' => $focusAreaId
    ]);

    // ----------------------------------------------------------------------
    // 5) Copy all old records => new version, marking them is_deleted=1
    // ----------------------------------------------------------------------
    $sqlOld = "
        SELECT *
        FROM analysis_package_focus_area_records
        WHERE analysis_package_focus_area_versions_id = :oldVid
    ";
    $stmtOld = $pdo->prepare($sqlOld);
    $stmtOld->execute([':oldVid' => $currentVerId]);
    $oldRecords = $stmtOld->fetchAll(\PDO::FETCH_ASSOC);

    if ($oldRecords) {
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

        foreach ($oldRecords as $r) {
            // Force is_deleted=1
            $stmtCopy->execute([
                ':pkg'  => $r['analysis_package_headers_id'],
                ':faid' => $focusAreaId,
                ':vid'  => $newVerId,
                ':its'  => $r['input_text_summaries_id'],
                ':gdx'  => $r['grid_index'],
                ':disp' => $r['display_order'],
                ':prp'  => $r['properties'],
                ':del'  => 1, // forcibly delete
                ':cat'  => $r['created_at']
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // 6) commit
    // ----------------------------------------------------------------------
    $pdo->commit();
    echo json_encode([
        'status'  => 'success',
        'message' => "Focus area '{$focusAreaName}' removed successfully."
    ]);

} catch (\PDOException $pdoEx) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // logData(['PDOException' => $pdoEx->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $pdoEx->getMessage()]);

} catch (\Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // logData(['Exception' => $ex->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $ex->getMessage()]);
}
