<?php
// /store_summary.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Load the Config class (do NOT assign require_once to $config)
require_once '/home/i17z4s936h3j/private_axiaba/includes/Config.php';
use AxiaBA\Config\Config;

// Get the singleton config instance
$config = Config::getInstance();

header("Access-Control-Allow-Origin: " . $config['app_base_url']);
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Now $config['db_host'], etc., are valid because Config implements ArrayAccess
$host = $config['db_host'];
$db   = $config['db_name'];
$user = $config['db_user']; 
$pass = $config['db_password']; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function sendJsonResponse($status, $message, $input_text_summaries_ids = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'input_text_summaries_ids' => $input_text_summaries_ids
    ]);
    exit;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    } else {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is allowed.');
}

$rawData = file_get_contents('php://input');
if (!$rawData) {
    sendJsonResponse('error', 'No data received.');
}

$data = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse('error', 'Invalid JSON format.');
}

$isMultiple = isset($data['summaries']) && is_array($data['summaries']);

try {
    // Create a local $pdo
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->beginTransaction();
    $input_text_summaries_ids = [];

    if ($isMultiple) {
        // We have multiple summary objects
        foreach ($data['summaries'] as $index => $summary) {
            $requiredFields = ['input_text_title', 'input_text_summary', 'input_text', 'api_utc'];
            foreach ($requiredFields as $field) {
                if (!isset($summary[$field]) || empty(trim($summary[$field]))) {
                    $pdo->rollBack();
                    sendJsonResponse('error', "Missing or empty field: $field in summary index $index.");
                }
            }
            $inputTitle = sanitize($summary['input_text_title']);
            $inputSummary = sanitize($summary['input_text_summary']);
            $inputText = sanitize($summary['input_text']);
            $uiDatetime = sanitize($summary['api_utc']);

            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $uiDatetime)) {
                $pdo->rollBack();
                sendJsonResponse('error', "Invalid datetime format for ui_datetime in summary index $index. Expected YYYY-MM-DD HH:MM:SS");
            }

            $stmt = $pdo->prepare('
                INSERT INTO input_text_summaries (input_text_title, input_text_summary, input_text, ui_datetime)
                VALUES (:title, :summary, :text, :datetime)
            ');
            $stmt->execute([
                ':title' => $inputTitle,
                ':summary' => $inputSummary,
                ':text' => $inputText,
                ':datetime' => $uiDatetime
            ]);
            $input_text_summaries_id = $pdo->lastInsertId();
            $input_text_summaries_ids[] = $input_text_summaries_id;
        }

        $pdo->commit();
        sendJsonResponse('success', 'All summary data stored successfully.', $input_text_summaries_ids);

    } else {
        // Single summary object
        $requiredFields = ['input_text_title', 'input_text_summary', 'input_text', 'api_utc'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $pdo->rollBack();
                sendJsonResponse('error', "Missing or empty field: $field.");
            }
        }
        $inputTitle = sanitize($data['input_text_title']);
        $inputSummary = sanitize($data['input_text_summary']);
        $inputText = sanitize($data['input_text']);
        $uiDatetime = sanitize($data['api_utc']);

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $uiDatetime)) {
            $pdo->rollBack();
            sendJsonResponse('error', 'Invalid datetime format for ui_datetime. Expected YYYY-MM-DD HH:MM:SS');
        }

        $stmt = $pdo->prepare('
            INSERT INTO input_text_summaries (input_text_title, input_text_summary, input_text, ui_datetime)
            VALUES (:title, :summary, :text, :datetime)
        ');
        $stmt->execute([
            ':title' => $inputTitle,
            ':summary' => $inputSummary,
            ':text' => $inputText,
            ':datetime' => $uiDatetime
        ]);

        $input_text_summaries_id = $pdo->lastInsertId();
        $pdo->commit();
        sendJsonResponse('success', 'Summary data stored successfully.', $input_text_summaries_id);
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database Error: ' . $e->getMessage());
    sendJsonResponse('error', 'Failed to store summary data.');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error: ' . $e->getMessage());
    sendJsonResponse('error', $e->getMessage());
}
