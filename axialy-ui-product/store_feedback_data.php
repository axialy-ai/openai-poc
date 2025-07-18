<?php
// /store_feedback_data.php
//
// Stores focus-area record data. We add display_order = (grid_index + 1) on insert.

$config = require '/home/i17z4s936h3j/private_axiaba/includes/Config.php'; // Adjust path as needed
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: " . $config['app_base_url']);
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/includes/db_connection.php';

function sendJsonResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

try {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format.');
    }

    if (empty($data['focus_area_id']) || empty($data['focus_area_version_id']) || !is_array($data['records'])) {
        throw new Exception('Missing required fields: focus_area_id, focus_area_version_id, or records.');
    }

    $focusAreaVersionId = (int)$data['focus_area_version_id'];
    $records = $data['records'];

    // We'll figure out the package from the focus_area_version
    $stmtFA = $pdo->prepare("
        SELECT fa.analysis_package_headers_id
        FROM analysis_package_focus_areas fa
        JOIN analysis_package_focus_area_versions fav
          ON fa.id = fav.analysis_package_focus_areas_id
        WHERE fav.id = :fav
        LIMIT 1
    ");
    $stmtFA->execute([':fav' => $focusAreaVersionId]);
    $faRow = $stmtFA->fetch(PDO::FETCH_ASSOC);
    if (!$faRow) {
        throw new Exception('Invalid focus_area_version_id - no matching records.');
    }
    $pkgId = (int)$faRow['analysis_package_headers_id'];

    // Insert each record
    $sql = "
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
          :favId,
          :itsId,
          :pkgId,
          :gIndex,
          :dispOrd,
          :props,
          :del,
          NOW()
        )
    ";
    $stmt = $pdo->prepare($sql);

    foreach ($records as $rec) {
        $itsId = isset($rec['input_text_summaries_id']) ? (int)$rec['input_text_summaries_id'] : null;
        $gIndex= isset($rec['grid_index']) ? (int)$rec['grid_index'] : 0;
        $del   = isset($rec['is_deleted']) ? (int)$rec['is_deleted'] : 0;
        $props = isset($rec['properties']) && is_array($rec['properties'])
                 ? json_encode($rec['properties'])
                 : '{}';

        // display_order = grid_index + 1
        $displayOrd = $gIndex + 1;

        $stmt->execute([
            ':favId'   => $focusAreaVersionId,
            ':itsId'   => $itsId,
            ':pkgId'   => $pkgId,
            ':gIndex'  => $gIndex,
            ':dispOrd' => $displayOrd,
            ':props'   => $props,
            ':del'     => $del
        ]);
    }

    sendJsonResponse('success', 'Focus area records stored successfully.');

} catch (Exception $e) {
    error_log('[store_feedback_data.php] Error: ' . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
