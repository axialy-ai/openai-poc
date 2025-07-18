<?php
// webhook.php

$config = require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php'; // <<<clide>>> Load configuration

require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey($config['stripe_api_key']);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

// If desired, you can also store the webhook secret in config
// For now, if you want to reference config, uncomment below and remove the hard-coded secret
// $webhook_secret = $config['stripe_webhook_secret'];
$webhook_secret = $config['stripe_webhook_secret'];

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
    if ($event->type === 'invoice.payment_succeeded') {
        $subscription = $event->data->object->subscription;
        // Update your database to mark the subscription as active
    }
} catch (Exception $e) {
    error_log('Webhook Error: ' . $e->getMessage());
    http_response_code(400);
    exit();
}

http_response_code(200);
?>
