<?php
/****************************************************************************
 * /fetch_past_versions.php
 *
 * Lists all past versions for a specific focus area within a given package,
 * returning:
 *   - version_num (the focus_area_version_number)
 *   - created_at  (the earliest creation date among that version’s records)
 *   - focus_area_object (the official name from the DB or override name)
 *   - revision_summary (the focus_area_revision_summary from the DB)
 ****************************************************************************/
require_once 'includes/db_connection.php';
header('Content-Type: application/json');

// Query params
$pkgId       = isset($_GET['package_id'])       ? (int)$_GET['package_id'] : 0;
$focusAreaNm = isset($_GET['focus_area_name'])  ? trim($_GET['focus_area_name']) : '';

if ($pkgId <= 0 || $focusAreaNm === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid parameters (package_id, focus_area_name)'
    ]);
    exit;
}

try {
    // 1) Find the focus-area row
    $sqlFa = "
        SELECT fa.id AS focusAreaId
        FROM analysis_package_focus_areas fa
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name             = :faName
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([':pkg' => $pkgId, ':faName' => $focusAreaNm]);
    $faRow = $stmtFa->fetch(PDO::FETCH_ASSOC);

    if (!$faRow) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'No such focus area found.'
        ]);
        exit;
    }
    $focusAreaId = (int)$faRow['focusAreaId'];

    // 2) Query all versions for that focusArea
    //    We left-join to analysis_package_focus_area_records to get earliest record creation date
    //    We group by the version row, ordering by focus_area_version_number ASC
    $sql = "
        SELECT
          v.focus_area_version_number          AS version_num,
          COALESCE(MIN(r.created_at), v.created_at) AS created_at,
          COALESCE(v.focus_area_name_override, fa.focus_area_name) AS focus_area_object,
          COALESCE(v.focus_area_revision_summary, '') AS revision_summary
        FROM analysis_package_focus_area_versions v
        JOIN analysis_package_focus_areas fa
          ON fa.id = v.analysis_package_focus_areas_id
        LEFT JOIN analysis_package_focus_area_records r
          ON r.analysis_package_focus_area_versions_id = v.id
        WHERE v.analysis_package_focus_areas_id = :faId
        GROUP BY v.id, fa.focus_area_name
        ORDER BY v.focus_area_version_number ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':faId' => $focusAreaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
