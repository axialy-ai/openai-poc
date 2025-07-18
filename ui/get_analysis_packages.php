<?php
// /get_analysis_packages.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging (optional, disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_analysis_packages.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_analysis_packages.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    debugLog("Authenticated user details in get_analysis_packages.php", [
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

    debugLog("User's default organization ID in get_analysis_packages.php", [
        'default_organization_id' => $userDefaultOrgId
    ]);

    // Fetch analysis packages associated with user's default organization and having at least one feedback request
    $stmt = $pdo->prepare("
        SELECT 
            ap.id, 
            ap.package_name 
        FROM 
            analysis_package_headers ap
        WHERE 
            ap.default_organization_id = :default_org_id
            AND EXISTS (
                SELECT 1 FROM stakeholder_feedback_headers sfr
                WHERE sfr.analysis_package_headers_id = ap.id
                LIMIT 1
            )
        ORDER BY 
            ap.package_name ASC
    ");
    $stmt->execute([':default_org_id' => $userDefaultOrgId]);
    $analysisPackages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("Filtered analysis packages fetched successfully in get_analysis_packages.php", ['count' => count($analysisPackages)]);

// Return JSON response
    echo json_encode([
        'status' => 'success',
        'data' => $analysisPackages
    ]);

} catch (PDOException $e) {
    debugLog("Database error in get_analysis_packages.php", [
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
    debugLog("General error in get_analysis_packages.php", [
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
debugLog("Response complete in get_analysis_packages.php");
?>
