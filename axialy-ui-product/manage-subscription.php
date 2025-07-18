<?php
// manage-subscription.php

require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';
use AxiaBA\Config\Config;
$config = Config::getInstance();

require_once __DIR__ . '/vendor/autoload.php';
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
// ...
// Remainder of the file is unchanged
// (Your day pass logic, subscription cancellation, etc.)


// Set timezone to match server
date_default_timezone_set('MST');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
    exit;
}

// Configure SSL certificates if needed
putenv("CURL_CA_BUNDLE=/home/i17z4s936h3j/public_html/certs/cacert.pem");
ini_set('curl.cainfo', '/home/i17z4s936h3j/public_html/certs/cacert.pem');
ini_set('openssl.cafile', '/home/i17z4s936h3j/public_html/certs/cacert.pem');

// Use Stripe API key from config
\Stripe\Stripe::setApiKey($config['stripe_api_key']);

try {
    // Fetch the subscription_id, plan type, and trial_end_date
    $stmt = $pdo->prepare('SELECT subscription_id, subscription_plan_type, trial_end_date FROM ui_users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user has no subscription_id, redirect them to subscription page
    if (empty($userData['subscription_id'])) {
        header('Location: subscription.php');
        exit;
    }

    // Handle cancellation POST for regular (non-day) subscriptions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
        // Only do Stripe cancellation if subscription_id is not a day pass
        if (strpos($userData['subscription_id'], 'day_') === false) {
            $subscription = \Stripe\Subscription::retrieve($userData['subscription_id']);
            $subscription->cancel();

            // Mark user as unsubscribed in DB
            $stmt = $pdo->prepare('
                UPDATE ui_users 
                SET subscription_active = 0,
                    subscription_id = NULL,
                    trial_end_date = NULL
                WHERE id = ?
            ');
            $stmt->execute([$_SESSION['user_id']]);

            header('Location: manage-subscription.php?cancelled=true');
            exit;
        }
    }

    $timeZone = new DateTimeZone('MST');
    $now = new DateTime('now', $timeZone);

    /**
     * If subscription_id starts with "day_", treat as day pass
     * Otherwise, treat as a standard Stripe subscription
     */
    if (strpos($userData['subscription_id'], 'day_') === 0) {
        // Day Pass Logic
        $endDate = new DateTime($userData['trial_end_date'], $timeZone);
        $interval = $now->diff($endDate);

        // Calculate total minutes left
        $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        $isExpired = ($endDate <= $now);

        if ($isExpired) {
            // Day Pass Expired
            $subscriptionDetails = [
                'type' => '24-Hour Day Pass',
                'status' => 'expired',
                'price' => '$9.95',
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ];
            $actionButton = '<a href="subscription.php" class="action-button purchase-new">Purchase Another Day Pass</a>';
        } else {
            // Day Pass Active (not yet expired)
            // Display up to 24 hours
            $hours = min(23, floor($totalMinutes / 60));
            $minutes = $totalMinutes % 60;

            $timeDisplay = '';
            if ($hours > 0) {
                $timeDisplay .= $hours . ' hour' . ($hours !== 1 ? 's' : '');
            }
            if ($minutes > 0) {
                if ($timeDisplay) {
                    $timeDisplay .= ' and ';
                }
                $timeDisplay .= $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
            }

            $subscriptionDetails = [
                'type' => '24-Hour Day Pass',
                'status' => 'active',
                'price' => '$9.95',
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ];
            $actionButton = "<div class='info-message'>Your Day Pass will expire in {$timeDisplay}</div>";
        }

    } else {
        // Regular Subscription Logic
        $subscription = \Stripe\Subscription::retrieve($userData['subscription_id']);

        // current_period_end from Stripe subscription
        $endDate = new DateTime('@' . $subscription->current_period_end);
        $endDate->setTimezone($timeZone);

        // Check if subscription is in trial
        $isTrialing = ($subscription->status === 'trialing');
        $trialEnd = null;
        if ($isTrialing && $subscription->trial_end) {
            $trialEnd = new DateTime('@' . $subscription->trial_end);
            $trialEnd->setTimezone($timeZone);
        }

        // Display price based on subscription_plan_type
        // (these are the known plan types from subscription.php)
        if ($userData['subscription_plan_type'] === 'monthly') {
            $price = '$65.00/month';
        } elseif ($userData['subscription_plan_type'] === 'yearly') {
            $price = '$663.00/year';
        } else {
            // Fallback in case plan_type is something else
            $price = '$9.95'; 
        }

        $subscriptionDetails = [
            'type'               => ucfirst($userData['subscription_plan_type']) . ' Subscription',
            'price'              => $price,
            'status'             => $isTrialing ? 'trialing' : $subscription->status,
            'current_period_end' => $endDate->format('Y-m-d H:i:s')
        ];

        // If user is in trial, show trial time left
        // Otherwise also display a cancel subscription button
        $actionHtml = '<div class="subscription-actions">';
        if ($isTrialing && $trialEnd !== null) {
            $trialTimeLeft = $now->diff($trialEnd);
            $trialDaysLeft = $trialTimeLeft->days;
            $subscriptionDetails['trial_end'] = $trialEnd->format('Y-m-d H:i:s');
            $subscriptionDetails['trial_days_left'] = $trialDaysLeft;
            $actionHtml .= "<div class='info-message'>Your trial period will end in {$trialDaysLeft} days</div>";
        }

        // Cancel subscription button
        $actionHtml .= '<button class="cancel-button" onclick="showCancelModal()">Cancel Subscription</button>';
        $actionHtml .= '</div>';
        $actionButton = $actionHtml;
    }

} catch (Exception $e) {
    error_log('Error managing subscription: ' . $e->getMessage());
    $error = 'An error occurred while managing your subscription. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Your Subscription - AxiaBA</title>
    <link rel="stylesheet" href="/assets/css/desktop.css">
    <style>
        /* Original inline styles retained */
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .subscription-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .subscription-type {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .subscription-price {
            font-size: 20px;
            color: #007bff;
        }
        .subscription-details {
            margin-bottom: 30px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .detail-label {
            color: #666;
        }
        .detail-value {
            font-weight: 500;
        }
        .subscription-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .cancel-button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .cancel-button:hover {
            background: #c82333;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-trialing {
            background: #cce5ff;
            color: #004085;
        }
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-button {
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .confirm-cancel {
            background: #dc3545;
            color: white;
        }
        .cancel-cancel {
            background: #6c757d;
            color: white;
        }
        .info-message {
            background: #e2e3e5;
            border: 1px solid #d6d8db;
            color: #383d41;
            padding: 12px;
            border-radius: 4px;
            text-align: center;
            margin-top: 20px;
        }
        .action-button.purchase-new {
            display: block;
            background: #007bff;
            color: white;
            text-align: center;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
        }
        .action-button.purchase-new:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="success-message">
                Your subscription has been successfully cancelled.
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="subscription-card">
            <div class="subscription-header">
                <div class="subscription-type">
                    <?php echo htmlspecialchars($subscriptionDetails['type'] ?? ''); ?>
                </div>
                <div class="subscription-price">
                    <?php echo htmlspecialchars($subscriptionDetails['price'] ?? ''); ?>
                </div>
            </div>

            <div class="subscription-details">
                <div class="detail-row">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <?php
                            $statusClass = 'status-' . ($subscriptionDetails['status'] ?? 'unknown');
                            $statusLabel = ucfirst($subscriptionDetails['status'] ?? 'unknown');
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Current Period Ends</div>
                    <div class="detail-value">
                        <?php
                            // We store day pass in 'end_date', otherwise 'current_period_end'
                            echo htmlspecialchars($subscriptionDetails['end_date'] ??
                                                 $subscriptionDetails['current_period_end'] ?? '');
                        ?>
                    </div>
                </div>
            </div>

            <?php echo $actionButton ?? ''; ?>
        </div>
    </div>

    <?php 
    // Show cancel modal only for standard subscriptions (not day pass)
    if (strpos($userData['subscription_id'], 'day_') === false): 
    ?>
    <!-- Cancel Confirmation Modal - Only for regular subscriptions -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h2>Cancel Subscription?</h2>
            <p>Are you sure you want to cancel your subscription? This action cannot be undone.</p>
            <div class="modal-buttons">
                <form method="POST">
                    <input type="hidden" name="cancel_subscription" value="1">
                    <button type="submit" class="modal-button confirm-cancel">Yes, Cancel</button>
                </form>
                <button type="button" onclick="hideCancelModal()" class="modal-button cancel-cancel">
                    No, Keep It
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function showCancelModal() {
            document.getElementById('cancelModal').style.display = 'flex';
        }

        function hideCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                hideCancelModal();
            }
        }
    </script>
</body>
</html>
