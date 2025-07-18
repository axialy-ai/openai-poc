<?php
// /app.axialy.ai/logout.php
require_once 'includes/db_connection.php';
require_once 'includes/debug_utils.php';
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get user data before clearing session
    $userId       = $_SESSION['user_id'] ?? null;
    $sessionToken = $_SESSION['session_token'] ?? null;

    debugLog("Logout initiated", [
        'userId'    => $userId,
        'sessionId' => session_id()
    ]);

    if ($userId && $sessionToken) {
        // Remove session from database (only UI session row)
        $stmt = $pdo->prepare("
            DELETE FROM ui_user_sessions
             WHERE user_id = ?
               AND session_token = ?
               AND product='ui'
        ");
        $stmt->execute([$userId, $sessionToken]);

        debugLog("Database session removed", [
            'userId'       => $userId,
            'rowsAffected' => $stmt->rowCount()
        ]);
    }

    // Clear all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        debugLog("Session cookie destroyed");
    }

    // Destroy the session
    session_destroy();
    
    debugLog("Session destroyed successfully");
    // Redirect to login page with logged_out parameter
    header('Location: login.php?logged_out=1');
    exit;
} catch (Exception $e) {
    debugLog("Error during logout", ['error' => $e->getMessage()]);

    // Still try to clear session data
    session_destroy();

    // Redirect to login page with error parameter
    header('Location: login.php?logout_error=1');
    exit;
}
?>
