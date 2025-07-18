<?php
// create-subscription.php

require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';
use AxiaBA\Config\Config;
$config = Config::getInstance();

require_once __DIR__ . '/vendor/autoload.php';

$stripe = new \Stripe\StripeClient($config['stripe_api_key']);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

try {
    $subscription = $stripe->subscriptions->create([
        'customer' => $input['customerId'],
        'items' => [['price' => $input['priceId']]],
        'payment_behavior' => 'default_incomplete',
        'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
        'expand' => ['latest_invoice.payment_intent'],
    ]);
    echo json_encode([
        'success' => true,
        'subscriptionId' => $subscription->id,
        'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret,
    ]);
} catch (Exception $e) {
    error_log('Error creating subscription: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
