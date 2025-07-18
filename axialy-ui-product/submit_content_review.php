<?php
// /submit_content_review.php

header('Content-Type: application/json');

// Ensure that the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Retrieve and sanitize input data
$stakeholders = isset($_POST['stakeholders']) ? $_POST['stakeholders'] : [];

// Ensure that 'stakeholders' is an array
if (!is_array($stakeholders)) {
    $stakeholders = [$stakeholders];
}

if (empty($stakeholders)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'No stakeholders selected.']);
    exit;
}

// Prepare the log entry
$logEntry = date('Y-m-d H:i:s') . " - Content Review Request for Stakeholders: " . implode(', ', $stakeholders) . "\n";

// Log to the existing error_log file
// The third parameter '3' specifies the message type: 3 = message is appended to the file destination
// The fourth parameter is the destination file path. Since the user uses /home/i17z4s936h3j/public_html/error_log, ensure the path is correct.
$logFilePath = __DIR__ . '/error_log'; // Adjust if your error_log is located elsewhere

// Append the log entry to the error_log file
if (!error_log($logEntry, 3, $logFilePath)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to log the request.']);
    exit;
}

// Respond with success
echo json_encode(['status' => 'success', 'message' => 'Content Review Request Submitted.']);
?>
