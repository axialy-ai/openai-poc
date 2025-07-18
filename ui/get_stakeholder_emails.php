<?php
// /get_stakeholder_emails.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging (optional, disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_stakeholder_emails.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_stakeholder_emails.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    debugLog("Authenticated user details in get_stakeholder_emails.php", [
        'user_id' => $userId
    ]);

    // Fetch user's custom organizations
    $stmt = $pdo->prepare("
        SELECT id FROM custom_organizations 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $userOrganizations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userOrganizations)) {
        // No organizations found for the user
        echo json_encode([
            'status' => 'success',
            'emails' => []
        ]);
        exit;
    }

    // Fetch distinct stakeholder emails associated with user's feedback requests
    $sql = "SELECT DISTINCT 
                sfr.stakeholder_email
            FROM 
                stakeholder_feedback_headers sfr
            JOIN 
                analysis_package_headers ap ON sfr.analysis_package_headers_id = ap.id
            WHERE 
                ap.custom_organization_id IN (" . implode(',', array_fill(0, count($userOrganizations), '?')) . ")
            ORDER BY 
                sfr.stakeholder_email ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($userOrganizations);
    $stakeholderEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    debugLog("Stakeholder emails fetched successfully in get_stakeholder_emails.php", ['count' => count($stakeholderEmails)]);

    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'emails' => $stakeholderEmails
    ]);

} catch (PDOException $e) {
    debugLog("Database error in get_stakeholder_emails.php", [
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
    debugLog("General error in get_stakeholder_emails.php", [
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
debugLog("Response complete in get_stakeholder_emails.php");
?>
