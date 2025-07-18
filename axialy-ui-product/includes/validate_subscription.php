<?php
// /includes/validate_subscription.php

require_once 'db_connection.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user is logged in and has valid session
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'isValid' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    // Get user's subscription details
    $stmt = $pdo->prepare('
        SELECT 
            subscription_active,
            subscription_plan_type,
            trial_end_date
        FROM ui_users 
        WHERE id = :user_id
    ');
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscriptionData) {
        http_response_code(401);
        echo json_encode([
            'isValid' => false,
            'message' => 'Subscription data not found'
        ]);
        exit;
    }

    // Set timezone for date comparisons
    date_default_timezone_set('MST');
    $now = new DateTime();

    // Initialize subscription status
    $isSubscriptionActive = false;

    // Check subscription based on plan type
    if ($subscriptionData['subscription_plan_type'] === 'day') {
        // For day pass, check if within valid period
        if ($subscriptionData['trial_end_date'] !== null) {
            $endDate = new DateTime($subscriptionData['trial_end_date']);
            $isSubscriptionActive = $now < $endDate;
        }
    } else {
        // For regular subscriptions
        $isSubscriptionActive = (bool)$subscriptionData['subscription_active'];
        
        // If in trial period, check trial expiration
        if ($subscriptionData['trial_end_date'] !== null) {
            $trialEnd = new DateTime($subscriptionData['trial_end_date']);
            if ($now > $trialEnd && !$subscriptionData['subscription_active']) {
                $isSubscriptionActive = false;
            }
        }
    }

    echo json_encode([
        'isValid' => $isSubscriptionActive,
        'message' => $isSubscriptionActive ? 'Subscription active' : 'Subscription expired'
    ]);

} catch (Exception $e) {
    error_log('Subscription validation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'isValid' => false,
        'message' => 'Error validating subscription'
    ]);
}