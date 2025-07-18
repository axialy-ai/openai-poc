<?php
// /app.axialy.ai/update_analysis_package.php

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/focus_org_session.php';

// Enable error reporting for debugging (remove in production):
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Return JSON:
header('Content-Type: application/json');

// Check user session
if (!validateSession() || !isset($_SESSION['default_organization_id'])) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'Invalid session or missing organization ID']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Validate required fields
$packageId = $data['package_id'] ?? null;
$packageName = $data['package_name'] ?? '';
$shortSummary = $data['short_summary'] ?? '';
$longDescription = $data['long_description'] ?? '';
$customOrgId = $data['custom_organization_id'] ?? 'default';

if (!$packageId) {
    echo json_encode(['status'=>'error','message'=>'Missing package_id']);
    exit;
}

// Attempt DB update
try {
    $pdo->beginTransaction();
    
    // Check user’s default org & focus org if needed
    $defaultOrgId = $_SESSION['default_organization_id'];
    $focusOrg = getFocusOrganization($pdo, $_SESSION['user_id']);
    
    // If $customOrgId is 'default', set the column to NULL; else numeric
    $customOrgValue = ($customOrgId === 'default') ? null : (int)$customOrgId;

    // Prepare update
    $stmt = $pdo->prepare("
        UPDATE analysis_package_headers
        SET 
          package_name         = :pkg_name,
          short_summary        = :short_summary,
          long_description     = :long_description,
          custom_organization_id = :custom_org_id
        WHERE id = :pkg_id
          AND default_organization_id = :default_org
        LIMIT 1
    ");
    $stmt->execute([
        ':pkg_name'     => $packageName,
        ':short_summary'=> $shortSummary,
        ':long_description' => $longDescription,
        ':custom_org_id'=> $customOrgValue,
        ':pkg_id'       => $packageId,
        ':default_org'  => $defaultOrgId
    ]);
    
    if ($stmt->rowCount() < 1) {
        // Possibly the package does not exist or belongs to a different org
        $pdo->rollBack();
        echo json_encode([
            'status'=>'error',
            'message'=>'No rows updated. Package not found or belongs to another organization.'
        ]);
        exit;
    }
    
    $pdo->commit();
    echo json_encode(['status'=>'success','message'=>'Package updated successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in update_analysis_package.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
