<?php
// webhooks/stripe.php

$config = require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';// <<<clide>>> Load configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Set up error logging
ini_set('log_errors', 1);
error_log('Stripe webhook received');

// Configure SSL certificates if needed
putenv("CURL_CA_BUNDLE=/home/i17z4s936h3j/public_html/certs/cacert.pem");
ini_set('curl.cainfo', '/home/i17z4s936h3j/public_html/certs/cacert.pem');
ini_set('openssl.cafile', '/home/i17z4s936h3j/public_html/certs/cacert.pem');

// Use Stripe API key from config
\Stripe\Stripe::setApiKey($config['stripe_api_key']);

// Use webhook secret from config
$endpoint_secret = $config['stripe_webhook_secret'];

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
    
    error_log('Webhook event received: ' . $event->type);
    switch ($event->type) {
        case 'customer.subscription.created':
            $subscription = $event->data->object;
            error_log('Subscription created: ' . $subscription->id);
            break;
            
        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            error_log('Subscription deleted: ' . $subscription->id);
            $stmt = $pdo->prepare('
                UPDATE ui_users 
                SET subscription_active = 0,
                    subscription_id = NULL,
                    trial_end_date = NULL 
                WHERE subscription_id = ?
            ');
            $stmt->execute([$subscription->id]);
            break;

        case 'customer.subscription.updated':
            $subscription = $event->data->object;
            error_log('Subscription updated: ' . $subscription->id . ' Status: ' . $subscription->status);
            
            $active_statuses = ['active', 'trialing'];
            $subscription_active = in_array($subscription->status, $active_statuses) ? 1 : 0;
            
            $stmt = $pdo->prepare('
                UPDATE ui_users 
                SET subscription_active = ?
                WHERE subscription_id = ?
            ');
            $stmt->execute([$subscription_active, $subscription->id]);
            break;
            
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $subscription_id = $invoice->subscription;
            error_log('Payment failed for subscription: ' . $subscription_id);
            
            $stmt = $pdo->prepare('
                UPDATE ui_users 
                SET subscription_active = 0
                WHERE subscription_id = ?
            ');
            $stmt->execute([$subscription_id]);
            break;

        default:
            error_log('Unhandled event type: ' . $event->type);
            break;
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);
} catch(\UnexpectedValueException $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Webhook signature verification failed']);
    exit();
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit();
}
