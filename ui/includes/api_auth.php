<?php
// /includes/api_auth.php
require_once __DIR__ . '/auth.php';
function validateApiAccess() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // First check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'error' => 'not_authenticated',
            'message' => 'User not authenticated',
            'redirect' => '/login.php'
        ]);
        exit;
    }
    try {
        global $pdo;
        
        // Get user's subscription details
        $stmt = $pdo->prepare('
            SELECT 
                u.subscription_active,
                u.subscription_plan_type,
                u.trial_end_date
            FROM ui_users u
            WHERE u.id = :user_id
        ');
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subscriptionData) {
            throw new Exception('User data not found');
        }
        date_default_timezone_set('MST');
        $now = new DateTime();
        // Check subscription based on plan type
        if ($subscriptionData['subscription_plan_type'] === 'day') {
            // For day pass, check if within valid period
            $endDate = new DateTime($subscriptionData['trial_end_date']);
            if ($now > $endDate) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode([
                    'error' => 'subscription_expired',
                    'message' => 'Your day pass has expired',
                    'redirect' => '/subscription.php'
                ]);
                exit;
            }
        } else {
            // For regular subscriptions
            if (!$subscriptionData['subscription_active']) {
                // Check if in trial period
                if ($subscriptionData['trial_end_date'] !== null) {
                    $trialEnd = new DateTime($subscriptionData['trial_end_date']);
                    if ($now > $trialEnd) {
                        header('Content-Type: application/json');
                        http_response_code(401);
                        echo json_encode([
                            'error' => 'subscription_expired',
                            'message' => 'Your trial period has expired',
                            'redirect' => '/subscription.php'
                        ]);
                        exit;
                    }
                } else {
                    header('Content-Type: application/json');
                    http_response_code(401);
                    echo json_encode([
                        'error' => 'subscription_inactive',
                        'message' => 'Your subscription is not active',
                        'redirect' => '/subscription.php'
                    ]);
                    exit;
                }
            }
        }
        return true;
    } catch (Exception $e) {
        error_log('API Authentication Error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'server_error',
            'message' => 'An error occurred while validating access',
            'redirect' => '/login.php'
        ]);
        exit;
    }
}
?>
