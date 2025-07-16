<?php
session_name('axialy_admin_session');
session_start();

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connection.php';

// For retrieving Admin info from Axialy_ADMIN
// (If your admin_auth logic doesn't store the email in session, we'll fetch from admin_users)
require_once __DIR__ . '/includes/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;

$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch($action) {
    case 'list':
        listIssues($pdo);
        break;
    case 'get':
        getIssue($pdo);
        break;
    case 'update':
        updateIssue($pdo);
        break;
    case 'sendEmail':
        sendEmail($pdo);
        break;
    default:
        echo json_encode(['success'=>false, 'message'=>'Unknown action']);
        break;
}

// ------------------------------------------------
function listIssues(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM issues ORDER BY id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
}

// ------------------------------------------------
function getIssue(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success'=>false, 'message'=>'No ID provided']);
        return;
    }
    // fix the correct user_email column name:
    $sql = "SELECT i.*,
                   u.id AS user_id_lookup,
                   u.username,
                   u.user_email AS user_email
            FROM issues i
            LEFT JOIN ui_users u ON i.user_id = u.id
            WHERE i.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success'=>false,'message'=>'Issue not found']);
        return;
    }
    echo json_encode($row);
}

// ------------------------------------------------
function updateIssue(PDO $pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        echo json_encode(['success'=>false, 'message'=>'No ID']);
        return;
    }
    $id     = (int)$input['id'];
    $title  = trim($input['issue_title'] ?? '');
    $desc   = trim($input['issue_description'] ?? '');
    $stat   = trim($input['status'] ?? 'Open');
    if (!$title || !$desc) {
        echo json_encode(['success'=>false, 'message'=>'Title and description cannot be empty.']);
        return;
    }
    try {
        $stmt = $pdo->prepare("
          UPDATE issues
             SET issue_title       = :t,
                 issue_description = :d,
                 status           = :s,
                 updated_at       = NOW()
           WHERE id = :id
        ");
        $stmt->execute([
            ':t' => $title,
            ':d' => $desc,
            ':s' => $stat,
            ':id'=> $id
        ]);
        echo json_encode(['success'=>true]);
    } catch (Exception $ex) {
        echo json_encode(['success'=>false, 'message'=>$ex->getMessage()]);
    }
}

// ------------------------------------------------
// NEW: Send an email to the user who submitted the issue
function sendEmail(PDO $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id']) || empty($data['personal_message'])) {
        echo json_encode(['success'=>false,'message'=>'Missing issue ID or personal message.']);
        return;
    }
    $issueId = (int)$data['id'];
    $personalMessage = trim($data['personal_message']);

    // 1) Look up the issue, user info, etc.
    $sql = "SELECT i.*,
                   u.username,
                   u.user_email
            FROM issues i
            LEFT JOIN ui_users u ON i.user_id = u.id
            WHERE i.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $issueId]);
    $issueRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$issueRow) {
        echo json_encode(['success'=>false,'message'=>'Issue not found.']);
        return;
    }
    // If the user has no email or something, handle that:
    if (empty($issueRow['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'User has no email on file.']);
        return;
    }

    // 2) Get the Admin's "from" email from session
    // We'll do a quick fetch from Axialy_ADMIN to get the logged in admin's email.
    $adminUserId = $_SESSION['admin_user_id'] ?? 0;
    if (!$adminUserId) {
        echo json_encode(['success'=>false,'message'=>'No admin user in session.']);
        return;
    }
    try {
        $adminDB = AdminDBConfig::getInstance()->getPdo();
        $admStmt = $adminDB->prepare("SELECT email FROM admin_users WHERE id=:uid LIMIT 1");
        $admStmt->execute([':uid' => $adminUserId]);
        $adminEmail = $admStmt->fetchColumn();
        if (!$adminEmail) {
            // fallback
            $adminEmail = 'admin@axiaba.com';
        }
    } catch (\Exception $ex) {
        // fallback
        $adminEmail = 'admin@axiaba.com';
    }

    // 3) Build the email subject & body
    $issueTitle = $issueRow['issue_title'];
    $issueDesc  = $issueRow['issue_description'];
    $issueStatus= $issueRow['status'];
    $createdAt  = $issueRow['created_at'];
    $updatedAt  = $issueRow['updated_at'] ?? '(none)';
    $toEmail    = $issueRow['user_email'];

    // Subject:
    // e.g. "Axialy Support Ticket #123 - [Issue Title]"
    $subject = "Axialy Support Ticket #{$issueId} - {$issueTitle}";

    // Body: personalMessage + a summary block
    // You can style this with HTML or keep it plain text
    $body = 
"Hello {$issueRow['username']},

{$personalMessage}

--------------------------------------------------
Ticket Details:
Title: {$issueTitle}
Description: {$issueDesc}
Status: {$issueStatus}
Submitted: {$createdAt}
Last Updated: {$updatedAt}
--------------------------------------------------

Thank you for using Axialy. If you have any follow-up,
you can reply to support@axiaba.com.

Best regards,
Axialy Support Team
";

    // 4) Actually send the email (simple mail() or a real mail library)
    // Here’s a quick mail() example:
    $headers  = "From: {$adminEmail}\r\n";
    $headers .= "Reply-To: support@axiaba.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Cc: support@axiaba.com\r\n";
    $headers .= "Bcc: {$adminEmail}\r\n";

    // Try to send
    $ok = @mail($toEmail, $subject, $body, $headers);
    if (!$ok) {
        echo json_encode(['success'=>false, 'message'=>'mail() failed or disabled.']);
        return;
    }

    // 5) Return success
    echo json_encode(['success'=>true]);
}
