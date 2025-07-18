<?php
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';
require_once 'includes/debug_utils.php';
require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';

use AxiaBA\Config\Config;
$config = Config::getInstance();

// Enable error reporting (optional in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Verify session
if (!validateSession()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check if it's POST with multipart/form-data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }

    // Make sure we have an organization_id
    if (!isset($_POST['organization_id']) || !is_numeric($_POST['organization_id'])) {
        throw new Exception('organization_id is required and must be numeric.');
    }

    $orgId = (int) $_POST['organization_id'];
    $userId = $_SESSION['user_id'];

    // Validate the user truly owns this organization
    $stmt = $pdo->prepare("SELECT * FROM custom_organizations WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $orgId, ':user_id' => $userId]);
    $existingOrg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingOrg) {
        throw new Exception('Organization not found or you do not have permission to update it.');
    }

    // Gather input fields, fall back to existing values if not provided
    $orgName         = isset($_POST['org-name']) ? trim($_POST['org-name']) : $existingOrg['custom_organization_name'];
    $pointOfContact  = isset($_POST['point-of-contact']) ? trim($_POST['point-of-contact']) : $existingOrg['point_of_contact'];
    $orgEmail        = isset($_POST['org-email']) ? trim($_POST['org-email']) : $existingOrg['email'];
    $orgPhone        = isset($_POST['org-phone']) ? trim($_POST['org-phone']) : $existingOrg['phone'];
    $orgWebsite      = isset($_POST['org-website']) ? trim($_POST['org-website']) : $existingOrg['website'];
    $orgNotes        = isset($_POST['org-notes']) ? trim($_POST['org-notes']) : $existingOrg['organization_notes'];

    // Prepare for file upload
    $logosDir = rtrim($config['uploads']['logos_dir'], '/') . '/';
    $storedFilename   = $existingOrg['logo_path'];     // keep current logo_path unless a new file is uploaded
    $originalFilename = $existingOrg['image_file'];    // keep current image_file unless a new file is uploaded

    if (isset($_FILES['org-logo']) && $_FILES['org-logo']['error'] === UPLOAD_ERR_OK) {
        // If user uploaded a new file
        if (!file_exists($logosDir)) {
            mkdir($logosDir, 0755, true);
        }

        $fileTmpPath  = $_FILES['org-logo']['tmp_name'];
        $originalName = basename($_FILES['org-logo']['name']);
        $fileSize     = $_FILES['org-logo']['size'];
        $fileType     = $_FILES['org-logo']['type'];

        $fileNameCmps  = explode(".", $originalName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedExtensions = ['png', 'jpg', 'jpeg'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type for logo. Only PNG/JPG/JPEG allowed.');
        }

        // Generate a new unique filename
        $newFileName = md5(time() . $originalName) . '.' . $fileExtension;
        $destPath    = $logosDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception('Failed to upload logo.');
        }

        // If successful, store hashed path & the original file name
        $storedFilename   = $newFileName;
        $originalFilename = $originalName;
    }

    // Update the record (no updated_at reference, to avoid DB errors)
    $sql = "
       UPDATE custom_organizations
          SET custom_organization_name = :org_name,
              point_of_contact         = :point_of_contact,
              email                    = :email,
              phone                    = :phone,
              website                  = :website,
              organization_notes       = :org_notes,
              logo_path               = :logo_path,
              image_file              = :image_file
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':org_name'         => $orgName,
        ':point_of_contact' => $pointOfContact,
        ':email'            => $orgEmail,
        ':phone'            => $orgPhone,
        ':website'          => $orgWebsite,
        ':org_notes'        => $orgNotes,
        ':logo_path'        => $storedFilename,
        ':image_file'       => $originalFilename,
        ':id'               => $orgId,
        ':user_id'          => $userId
    ]);

    // Fetch the updated record to return in JSON
    $stmt = $pdo->prepare("SELECT * FROM custom_organizations WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $orgId, ':user_id' => $userId]);
    $updatedOrg = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'       => 'success',
        'message'      => 'Organization updated successfully',
        'organization' => $updatedOrg
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
