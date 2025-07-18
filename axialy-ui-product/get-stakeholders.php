<?php
// /get-stakeholders.php
//
// Loads a list of stakeholder email/handle pairs from a JSON file
// named, for example, "stakeholders_<packageId>.json" or similar.
// All references to old terms have been removed.

header('Content-Type: application/json');

if (!isset($_GET['package_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing package_id']);
    exit;
}
$packageId = $_GET['package_id'];

// Suppose we store stakeholder data in a file named "stakeholders_<ID>.json"
$jsonFile = __DIR__ . '/data/stakeholders_' . basename($packageId) . '.json';

function loadJson($path) {
    if (!file_exists($path)) {
        http_response_code(404);
        echo json_encode(['error' => 'No stakeholder data for that package_id.']);
        exit;
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Malformed JSON: ' . json_last_error_msg()]);
        exit;
    }
    return $decoded;
}

$data = loadJson($jsonFile);

if (isset($data['Stakeholders']) && is_array($data['Stakeholders'])) {
    $list = [];
    foreach ($data['Stakeholders'] as $st) {
        if (!empty($st['Email']) && !empty($st['Handle'])) {
            $list[] = [
                'email'  => $st['Email'],
                'handle' => $st['Handle']
            ];
        }
    }
    echo json_encode(['stakeholders' => $list]);
} else {
    echo json_encode(['stakeholders' => []]);
}
