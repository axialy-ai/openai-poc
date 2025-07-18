<?php

// /app.axialy.ai/update_focus_organization.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging (optional, disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in update_focus_organization.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in update_focus_organization.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Retrieve POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['focus_org_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'focus_org_id is required']);
    exit;
}

$focusOrgId = $input['focus_org_id'];
$userId = $_SESSION['user_id'];

try {
    if ($focusOrgId === 'default' || $focusOrgId === null) {
        // Set to default: remove any existing focus organization
        $stmt = $pdo->prepare("DELETE FROM user_focus_organizations WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
    } else {
        // Validate that the custom organization belongs to the user
        $stmt = $pdo->prepare("SELECT id FROM custom_organizations WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $focusOrgId, ':user_id' => $userId]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Custom Organization ID']);
            exit;
        }

        // Check if a record exists
        $stmt = $pdo->prepare("SELECT id FROM user_focus_organizations WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE user_focus_organizations SET focus_org_id = :focus_org_id, created_at = NOW() WHERE user_id = :user_id");
            $stmt->execute([':focus_org_id' => $focusOrgId, ':user_id' => $userId]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO user_focus_organizations (user_id, focus_org_id, created_at) VALUES (:user_id, :focus_org_id, NOW())");
            $stmt->execute([':user_id' => $userId, ':focus_org_id' => $focusOrgId]);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Focus Organization updated successfully']);
} catch (PDOException $e) {
    debugLog("Database error in update_focus_organization.php", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? 'unknown',
        'driver_error_code' => $e->errorInfo[1] ?? 'unknown',
        'driver_error_message' => $e->errorInfo[2] ?? 'unknown',
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
} catch (Exception $e) {
    debugLog("General error in update_focus_organization.php", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Log the response being sent
debugLog("Response complete in update_focus_organization.php");

?>
