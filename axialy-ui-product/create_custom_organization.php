<?php
// /api/create_custom_organization.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';
require_once 'includes/debug_utils.php';
require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';

use AxiaBA\Config\Config;
$config = Config::getInstance();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in create_custom_organization.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE'   => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'POST'           => $_POST,
    'FILES'          => $_FILES,
    'SESSION'        => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in create_custom_organization.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Fetch user context from session
    $userId       = $_SESSION['user_id']       ?? null;
    $defaultOrgId = $_SESSION['default_organization_id'] ?? null;

    debugLog("Authenticated user details in create_custom_organization.php", [
        'user_id'       => $userId,
        'default_org_id'=> $defaultOrgId
    ]);

    // Validate required fields
    if (!isset($_POST['org-name']) || empty(trim($_POST['org-name']))) {
        debugLog("Validation failed - missing organization name");
        throw new Exception('Organization name is required');
    }

    // Log input fields
    debugLog("Processing form data in create_custom_organization.php", [
        'org-name'         => $_POST['org-name'],
        'point-of-contact' => $_POST['point-of-contact'] ?? null,
        'org-email'        => $_POST['org-email'] ?? null,
        'org-phone'        => $_POST['org-phone'] ?? null,
        'org-website'      => $_POST['org-website'] ?? null,
        'org-notes'        => $_POST['org-notes'] ?? null
    ]);

    // Retrieve private logos directory from config
    $logosDir = rtrim($config['uploads']['logos_dir'], '/') . '/';

    // Initialize the stored filename (for DB)
    $storedFilename = null;

    // Handle file upload
    if (isset($_FILES['org-logo']) && $_FILES['org-logo']['error'] === UPLOAD_ERR_OK) {
        // Ensure target directory
        if (!file_exists($logosDir)) {
            mkdir($logosDir, 0755, true);
            debugLog("Created upload directory", ['directory' => $logosDir]);
        }

        $fileTmpPath  = $_FILES['org-logo']['tmp_name'];
        $originalName = basename($_FILES['org-logo']['name']);
        $fileSize     = $_FILES['org-logo']['size'];
        $fileType     = $_FILES['org-logo']['type'];

        // Extract extension
        $fileNameCmps = explode(".", $originalName);
        $fileExtension= strtolower(end($fileNameCmps));

        // Allowed
        $allowedfileExtensions = ['png','jpg','jpeg'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Generate new unique filename
            $newFileName = md5(time() . $originalName) . '.' . $fileExtension;
            $destPath    = $logosDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $storedFilename = $newFileName;
                debugLog("Logo uploaded successfully", [
                    'physical_path' => $destPath,
                    'db_filename'   => $storedFilename
                ]);
            } else {
                debugLog("Failed to move uploaded logo", ['destination' => $destPath]);
                throw new Exception('Failed to upload logo.');
            }
        } else {
            debugLog("Invalid file extension for logo", ['file_extension' => $fileExtension]);
            throw new Exception('Invalid file type for logo. Only PNG/JPG/JPEG allowed.');
        }
    }

    // Start DB transaction
    $pdo->beginTransaction();
    debugLog("Transaction started in create_custom_organization.php");

    // Insert statement
    $sql = "
        INSERT INTO custom_organizations (
            user_id,
            custom_organization_name,
            point_of_contact,
            email,
            phone,
            website,
            organization_notes,
            logo_path,
            created_at
        ) VALUES (
            :user_id,
            :org_name,
            :point_of_contact,
            :email,
            :phone,
            :website,
            :org_notes,
            :logo_path,
            NOW()
        )
    ";

    debugLog("Preparing SQL statement in create_custom_organization.php", ['sql' => $sql]);
    $stmt = $pdo->prepare($sql);

    $params = [
        ':user_id'          => $userId,
        ':org_name'         => trim($_POST['org-name']),
        ':point_of_contact' => isset($_POST['point-of-contact']) ? trim($_POST['point-of-contact']) : null,
        ':email'            => isset($_POST['org-email'])        ? trim($_POST['org-email'])        : null,
        ':phone'            => isset($_POST['org-phone'])        ? trim($_POST['org-phone'])        : null,
        ':website'          => isset($_POST['org-website'])      ? trim($_POST['org-website'])      : null,
        ':org_notes'        => isset($_POST['org-notes'])        ? trim($_POST['org-notes'])        : null,
        ':logo_path'        => $storedFilename
    ];

    $stmt->execute($params);
    $newOrgId = $pdo->lastInsertId();
    debugLog("Insert successful in create_custom_organization.php", ['new_org_id' => $newOrgId]);

    // Fetch new org
    $stmt = $pdo->prepare("
        SELECT 
            id,
            custom_organization_name,
            point_of_contact,
            email,
            phone,
            website,
            organization_notes,
            logo_path,
            created_at
        FROM custom_organizations
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $newOrgId, ':user_id' => $userId]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);

    debugLog("Organization fetched after insert in create_custom_organization.php", [
        'organization' => $organization
    ]);

    // Commit
    $pdo->commit();
    debugLog("Transaction committed successfully in create_custom_organization.php");

    // Return JSON
    echo json_encode([
        'status'       => 'success',
        'message'      => 'Organization created successfully',
        'organization' => $organization
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        debugLog("Transaction rolled back due to PDO error in create_custom_organization.php");
    }
    debugLog("Database error occurred in create_custom_organization.php", [
        'error_message' => $e->getMessage(),
        // ...
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        debugLog("Transaction rolled back due to general error in create_custom_organization.php");
    }
    debugLog("General error occurred in create_custom_organization.php", [
        'error_message' => $e->getMessage(),
        // ...
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

debugLog("Response complete in create_custom_organization.php");
