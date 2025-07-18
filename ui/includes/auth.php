<?php
// /includes/auth.php
session_name('axialy_ui_session');   // ★ distinct cookie for the UI

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/debug_utils.php';

/**
 * Validates if user's session is active and subscription is valid
 * @return boolean True if session is valid and subscription is active
 */
function validateSession() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    debugLog("Validating session", [
        'session_id' => session_id(),
        'has_user_id' => isset($_SESSION['user_id']),
        'has_token' => isset($_SESSION['session_token'])
    ]);
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        debugLog("Session missing required variables");
        return false;
    }
    try {
        // Get database connection
        global $pdo;
        if (!$pdo) {
            throw new Exception('Database connection not available');
        }
        
        // Check session validity, subscription status, and subscription details
        $stmt = $pdo->prepare('
            SELECT 
                u.subscription_active,
                u.subscription_plan_type,
                u.trial_end_date
            FROM ui_user_sessions s
            JOIN ui_users u ON s.user_id = u.id 
            WHERE s.user_id = :user_id 
            AND s.session_token = :token 
            AND s.expires_at > NOW()
        ');
        
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':token' => $_SESSION['session_token']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result === false) {
            debugLog("Session not found or expired");
            return false;
        }
        // Initialize subscription status
        $isSubscriptionActive = false;
        
        // Set timezone for date comparisons
        date_default_timezone_set('MST');
        $now = new DateTime('now');
        // Check subscription based on plan type
        if ($result['subscription_plan_type'] === 'day') {
            // For day pass, check if within valid period
            $endDate = new DateTime($result['trial_end_date']);
            $isSubscriptionActive = $now < $endDate;
            
            debugLog("Day pass validation", [
                'currentTime' => $now->format('Y-m-d H:i:s'),
                'endDate' => $endDate->format('Y-m-d H:i:s'),
                'isActive' => $isSubscriptionActive
            ]);
        } else {
            // For regular subscriptions, use subscription_active flag
            $isSubscriptionActive = (bool)$result['subscription_active'];
            
            // If in trial period, check trial expiration
            if ($result['trial_end_date'] !== null) {
                $trialEnd = new DateTime($result['trial_end_date']);
                $isSubscriptionActive = $now < $trialEnd;
                
                debugLog("Trial period validation", [
                    'currentTime' => $now->format('Y-m-d H:i:s'),
                    'trialEnd' => $trialEnd->format('Y-m-d H:i:s'),
                    'isActive' => $isSubscriptionActive
                ]);
            }
        }
        // Store subscription status in session
        $_SESSION['subscription_active'] = $isSubscriptionActive;
        $_SESSION['subscription_plan_type'] = $result['subscription_plan_type'];
        
        debugLog("Session validation result", [
            'isValid' => true,
            'subscriptionActive' => $isSubscriptionActive,
            'planType' => $result['subscription_plan_type']
        ]);
        
        return true;
    } catch (Exception $e) {
        debugLog("Error during session validation", ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Checks if authentication is required and handles redirects
 * Includes subscription validation for protected pages,
 * then checks whether user has accepted the latest TOS (if available).
 */
function requireAuth() {
    // Get current script name
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    
    // List of pages that don't require subscription check
    // or TOS acceptance:
    $publicPages = [
        'login.php',
        'verify_email.php',
        'start_verification.php',
        'feedback-confirmation.php',
        'stakeholder-content-review.php',
        // Add accept_tos.php so user can actually accept TOS 
        // without infinite redirect
        'accept_tos.php'
    ];
    
    // Skip auth check for public pages
    if (in_array($currentScript, $publicPages)) {
        debugLog("Skipping auth check for public page", ['page' => $currentScript]);
        return;
    }
    
    debugLog("Checking authentication", ['script' => $currentScript]);
    
    // Validate session
    if (!validateSession()) {
        debugLog("Authentication failed, clearing session");
        
        // Clear session data
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time()-3600, '/');
            }
            @session_destroy();
        }
        
        // Redirect to login
        header('Location: /login.php');
        exit;
    }
    
    // For protected pages, check subscription status
    if (!in_array($currentScript, $publicPages) && !$_SESSION['subscription_active']) {
        debugLog("Valid session but inactive subscription, redirecting to subscription page");
        header('Location: /subscription.php');
        exit;
    }
    
    // Now check if user has accepted the latest TOS
    debugLog("Subscription active, checking TOS acceptance");
    global $pdo;
    if (!checkUserAcceptedTOS($pdo, $_SESSION['user_id'])) {
        debugLog("User has not accepted the TOS, redirecting to /accept_tos.php");
        header('Location: /accept_tos.php');
        exit;
    }
    
    debugLog("All checks passed, user can access the page");
}

/**
 * Checks subscription status for a specific user
 * @param int $userId The user ID to check
 * @return bool True if subscription is active
 */
function checkSubscriptionStatus($userId) {
    try {
        global $pdo;
        date_default_timezone_set('MST');
        $now = new DateTime('now');
        $stmt = $pdo->prepare('
            SELECT subscription_id, subscription_active, subscription_plan_type, trial_end_date 
            FROM ui_users 
            WHERE id = ?
        ');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return false;
        }
        // If subscription_active is explicitly set to 0, return false
        if (!$result['subscription_active']) {
            return false;
        }
        // For day pass
        if ($result['subscription_plan_type'] === 'day') {
            $endDate = new DateTime($result['trial_end_date']);
            return $now < $endDate;
        }
        // For regular subscriptions
        if ($result['subscription_id']) {
            // Double check with Stripe for current status
            try {
                $subscription = \Stripe\Subscription::retrieve($result['subscription_id']);
                $validStatuses = ['active', 'trialing'];
                return in_array($subscription->status, $validStatuses);
            } catch (\Exception $e) {
                error_log("Error checking Stripe subscription: " . $e->getMessage());
                // If we can't check Stripe, fall back to database status
                return (bool)$result['subscription_active'];
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error checking subscription status: " . $e->getMessage());
        return false;
    }
}

/**
 * Clears expired sessions from the database
 */
function clearExpiredSessions() {
    try {
        global $pdo;
        if (!$pdo) {
            throw new Exception('Database connection not available');
        }
        
        $stmt = $pdo->prepare('DELETE FROM ui_user_sessions WHERE expires_at < NOW()');
        $stmt->execute();
        
        debugLog("Cleared expired sessions");
    } catch (Exception $e) {
        debugLog("Error clearing expired sessions", ['error' => $e->getMessage()]);
    }
}

/**
 * Updates user's subscription status
 * @param int $userId The user ID to update
 * @param bool $status The new subscription status
 * @return bool True if update was successful
 */
function updateSubscriptionStatus($userId, $status) {
    try {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE ui_users SET subscription_active = ? WHERE id = ?');
        $result = $stmt->execute([(int)$status, $userId]);
        
        if ($result) {
            // Update session if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId) {
                $_SESSION['subscription_active'] = (bool)$status;
            }
        }
        
        return $result;
    } catch (Exception $e) {
        debugLog("Error updating subscription status", ['error' => $e->getMessage()]);
        return false;
    }
}

// Clear expired sessions periodically (1% chance on each request)
if (rand(1, 100) === 1) {
    clearExpiredSessions();
}

// Initialize session handling
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Set secure flag if using HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    
    session_start();
}

/**
 * Checks if the currently logged in user has accepted
 * the active version of the TOS (doc_key='tos').
 *
 * Returns true if:
 *   - No TOS document or no active version in `documents`, or
 *   - The user has an acceptance record for that active version.
 */
function checkUserAcceptedTOS($pdo, $userId) {
    // 1) Find the TOS document row
    $stmtDoc = $pdo->prepare("
        SELECT id, active_version_id
        FROM documents
        WHERE doc_key = 'tos'
        LIMIT 1
    ");
    $stmtDoc->execute();
    $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc || !$doc['active_version_id']) {
        // No TOS doc or no active version -> skip requirement
        return true;
    }
    
    // 2) Check if user has accepted that version
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_agreement_acceptances
        WHERE user_id = ?
          AND document_versions_id = ?
    ");
    $stmtCheck->execute([$userId, $doc['active_version_id']]);
    $count = $stmtCheck->fetchColumn();
    return ($count > 0);
}
?>
