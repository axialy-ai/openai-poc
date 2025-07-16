<?php
require_once __DIR__ . '/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;

/**
 * Ensures the admin session is valid in Axialy_ADMIN.admin_user_sessions,
 * or else redirects to /admin_login.php.
 */
function requireAdminAuth()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // If session keys are missing, go to your new admin_login
    if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_session_token'])) {
        logoutAndRedirect();
    }

    // Query Axialy_ADMIN DB for admin_user_sessions
    $adminDB = AdminDBConfig::getInstance()->getPdo();
    $stmt = $adminDB->prepare("
        SELECT s.id, s.expires_at,
               u.username, u.is_active, u.is_sys_admin
          FROM admin_user_sessions s
          JOIN admin_users u ON s.admin_user_id = u.id
         WHERE s.admin_user_id = :uid
           AND s.session_token  = :tok
           AND s.expires_at    > NOW()
         LIMIT 1
    ");
    $stmt->execute([
        ':uid' => $_SESSION['admin_user_id'],
        ':tok' => $_SESSION['admin_session_token']
    ]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    // If not found or expired, logout
    if (!$row) {
        logoutAndRedirect();
    }
    if ((int)$row['is_active'] !== 1) {
        logoutAndRedirect('Account disabled');
    }

    // If needed: check sys_admin
    // if ((int)$row['is_sys_admin'] !== 1) {
    //     logoutAndRedirect('Requires sys_admin privileges.');
    // }
}

/**
 * Clears admin session row and local session, then redirects to admin_login.
 */
function logoutAndRedirect($msg = '')
{
    $adminDB = AdminDBConfig::getInstance()->getPdo();

    if (!empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_session_token'])) {
        $del = $adminDB->prepare("
            DELETE FROM admin_user_sessions
             WHERE admin_user_id = :uid
               AND session_token  = :tok
        ");
        $del->execute([
            ':uid' => $_SESSION['admin_user_id'],
            ':tok' => $_SESSION['admin_session_token']
        ]);
    }

    $_SESSION = [];
    session_destroy();

    header('Location: /admin_login.php');
    exit;
}
