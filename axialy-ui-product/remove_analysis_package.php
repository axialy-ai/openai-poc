<?php
// remove_analysis_package.php
require_once 'includes/auth.php'; // or similar
requireAuth();

header('Content-Type: application/json');

// Parse incoming JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['package_id']) || !is_numeric($input['package_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid package_id']);
    exit;
}

$packageId = (int) $input['package_id'];
// Connect to DB
require_once 'includes/db_connection.php';

// Mark is_deleted = 1
$query = "UPDATE analysis_package_headers
          SET is_deleted = 1
          WHERE id = ? AND is_deleted = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $packageId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error',
                          'message' => 'No rows updated (already deleted or not found).']);
    }
} else {
    echo json_encode(['status' => 'error',
                      'message' => $conn->error]);
}
$stmt->close();
$conn->close();
