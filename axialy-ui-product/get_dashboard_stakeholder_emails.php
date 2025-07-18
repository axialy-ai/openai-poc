<?php
// /get_dashboard_stakeholder_emails.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log'); // Update this path accordingly
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_dashboard_stakeholder_emails.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_dashboard_stakeholder_emails.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    debugLog("Authenticated user details in get_dashboard_stakeholder_emails.php", [
        'user_id' => $userId
    ]);

    // Fetch user's default organization_id
    $stmt = $pdo->prepare("
        SELECT default_organization_id 
        FROM ui_users 
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    $userDefaultOrgId = $user['default_organization_id'];

    debugLog("User's default organization ID in get_dashboard_stakeholder_emails.php", [
        'default_organization_id' => $userDefaultOrgId
    ]);

    // Fetch distinct stakeholder emails
    $stmt = $pdo->prepare("
        SELECT DISTINCT sfr.stakeholder_email 
        FROM stakeholder_feedback_headers sfr
        JOIN analysis_package_headers ap ON sfr.analysis_package_headers_id = ap.id
        WHERE ap.default_organization_id = :default_org_id
        ORDER BY sfr.stakeholder_email ASC
    ");
    $stmt->execute([':default_org_id' => $userDefaultOrgId]);
    $stakeholderEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    debugLog("Stakeholder emails fetched successfully in get_dashboard_stakeholder_emails.php", ['count' => count($stakeholderEmails)]);

    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'stakeholder_emails' => $stakeholderEmails
    ]);

} catch (PDOException $e) {
    debugLog("Database error in get_dashboard_stakeholder_emails.php", [
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
        'message' => 'Internal Server Error. Please try again later.'
    ]);
} catch (Exception $e) {
    debugLog("General error in get_dashboard_stakeholder_emails.php", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error. Please try again later.'
    ]);
}

// Log the response being sent
debugLog("Response complete in get_dashboard_stakeholder_emails.php");
?>
