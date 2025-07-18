<?php

// /app.axialy.ai/get_focus_organization.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging (optional, disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_focus_organization.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_focus_organization.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    debugLog("Authenticated user details in get_focus_organization.php", [
        'user_id' => $userId
    ]);

    // Fetch the current focus organization for the user
    $stmt = $pdo->prepare("
        SELECT focus_org_id 
        FROM user_focus_organizations 
        WHERE user_id = :user_id 
        LIMIT 1
    ");

    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['focus_org_id'])) {
        $focusOrgId = $result['focus_org_id'];
        debugLog("Focus organization fetched successfully", ['focus_org_id' => $focusOrgId]);

        echo json_encode([
            'status' => 'success',
            'focus_org_id' => $focusOrgId
        ]);
    } else {
        // If no focus organization is set, return 'default'
        debugLog("No focus organization set for user");
        echo json_encode([
            'status' => 'success',
            'focus_org_id' => 'default'
        ]);
    }

} catch (PDOException $e) {
    debugLog("Database error in get_focus_organization.php", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? 'unknown',
        'driver_error_code' => $e->errorInfo[1] ?? 'unknown',
        'driver_error_message' => $e->errorInfo[2] ?? 'unknown',
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    debugLog("General error in get_focus_organization.php", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Log the response being sent
debugLog("Response complete in get_focus_organization.php");

?>
