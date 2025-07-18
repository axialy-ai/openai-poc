<?php
// /get_custom_organizations.php
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_custom_organizations.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE'   => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'GET'            => $_GET,
    'POST'           => $_POST,
    'SESSION'        => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_custom_organizations.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId       = $_SESSION['user_id'];
    $defaultOrgId = $_SESSION['default_organization_id'];

    debugLog("Starting organization fetch", [
        'user_id'       => $userId,
        'default_org_id'=> $defaultOrgId
    ]);

    // Get user's custom organizations
    $stmt = $pdo->prepare("
        SELECT co.id, 
               co.custom_organization_name, 
               co.point_of_contact, 
               co.email, 
               co.phone, 
               co.website, 
               co.organization_notes, 
               co.logo_path,
               co.created_at,
               CASE 
                   WHEN aph.custom_organization_id IS NOT NULL THEN TRUE 
                   ELSE FALSE 
               END as is_in_use
        FROM custom_organizations co
        LEFT JOIN analysis_package_headers aph
               ON co.id = aph.custom_organization_id
        WHERE co.user_id = :user_id
        GROUP BY co.id
        ORDER BY is_in_use DESC, co.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("Organizations query results", [
        'sql'    => $stmt->queryString,
        'params' => [':user_id' => $userId],
        'count'  => count($organizations),
        'first_org' => $organizations[0] ?? null
    ]);

    // Also get any orgs user has packages for, even if not owned
    $stmt = $pdo->prepare("
        SELECT DISTINCT co.id, 
                        co.custom_organization_name,
                        co.point_of_contact,
                        co.email,
                        co.phone,
                        co.website,
                        co.organization_notes,
                        co.logo_path,
                        co.created_at,
                        TRUE as is_in_use
        FROM custom_organizations co
        INNER JOIN analysis_package_headers aph
                ON co.id = aph.custom_organization_id
        WHERE aph.default_organization_id = :default_org_id
          AND co.user_id != :user_id
        ORDER BY co.created_at DESC
    ");
    $stmt->execute([
        ':default_org_id' => $defaultOrgId,
        ':user_id'        => $userId
    ]);
    $additionalOrgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("Additional organizations query results", [
        'sql'    => $stmt->queryString,
        'params' => [
            ':default_org_id' => $defaultOrgId,
            ':user_id'        => $userId
        ],
        'count'  => count($additionalOrgs),
        'first_org' => $additionalOrgs[0] ?? null
    ]);

    // Merge/deduplicate
    $allOrgs = array_merge($organizations, $additionalOrgs);
    $uniqueOrgs = [];
    $seenIds = [];
    foreach ($allOrgs as $org) {
        if (!isset($seenIds[$org['id']])) {
            $uniqueOrgs[] = $org;
            $seenIds[$org['id']] = true;
        }
    }

    debugLog("Final organization list", [
        'total_count' => count($uniqueOrgs),
        'owned_count' => count($organizations),
        'additional_count' => count($additionalOrgs),
        'sample_orgs' => array_slice($uniqueOrgs, 0, 3)
    ]);

    echo json_encode([
        'status'        => 'success',
        'organizations' => $uniqueOrgs
    ]);

} catch (PDOException $e) {
    debugLog("Database error in get_custom_organizations.php", [
        'error_message' => $e->getMessage(),
        // ...
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    debugLog("General error in get_custom_organizations.php", [
        'error_message' => $e->getMessage(),
        // ...
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

debugLog("Response complete in get_custom_organizations.php");
