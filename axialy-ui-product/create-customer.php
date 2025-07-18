<?php
// create-customer.php
echo 'Script reached successfully.';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load new config
require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';
use AxiaBA\Config\Config;
$config = Config::getInstance();

require_once __DIR__ . '/vendor/autoload.php';

// Use Stripe API key from config
\Stripe\Stripe::setApiKey($config['stripe_api_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address provided.']);
        exit;
    }

    try {
        $customer = \Stripe\Customer::create([
            'email' => $data['email']
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'customerId' => $customer->id]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Stripe API error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method. Use POST.']);
}
