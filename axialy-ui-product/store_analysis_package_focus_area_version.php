<?php
// /store_analysis_package_focus_area_version.php
include_once './includes/db_connection.php';
include_once './includes/validation.php';

header('Content-Type: application/json');

// Parse the JSON request body
$data = json_decode(file_get_contents('php://input'), true);

/*
  In the updated schema, each focus area uses
  analysis_package_focus_area_versions to track versioning.
  We expect the client request to provide:
    - analysis_package_focus_areas_id
    - focus_area_version_number
*/
if (
    !isset($data['analysis_package_focus_areas_id']) ||
    !isset($data['focus_area_version_number'])
) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields.'
    ]);
    exit;
}

$focusAreasId        = (int)$data['analysis_package_focus_areas_id'];
$focusAreaVersionNum = (int)$data['focus_area_version_number'];

try {
    // Insert a new record into analysis_package_focus_area_versions
    $stmt = $pdo->prepare("
        INSERT INTO analysis_package_focus_area_versions (
            analysis_package_focus_areas_id,
            focus_area_version_number,
            created_at
        ) VALUES (
            :fa_id,
            :fa_version,
            NOW()
        )
    ");

    $stmt->execute([
        ':fa_id'      => $focusAreasId,
        ':fa_version' => $focusAreaVersionNum
    ]);

    $newVersionId = $pdo->lastInsertId();

    echo json_encode([
        'status'               => 'success',
        'focus_area_version_id' => $newVersionId
    ]);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to create focus area version.'
    ]);
}
