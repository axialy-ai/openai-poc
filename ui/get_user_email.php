<?php
// /app.axialy.ai/get_user_email.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php'; 
// or whichever file ensures session_start() and loads $pdo

header('Content-Type: application/json');

// Make sure user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in'
    ]);
    exit;
}

// We have a valid user_id
$userId = (int)$_SESSION['user_id'];

try {
    // Fetch the user's real email from ui_users
    $stmt = $pdo->prepare("SELECT user_email FROM ui_users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $email = $stmt->fetchColumn();

    if ($email) {
        echo json_encode([
            'status' => 'success',
            'email' => $email
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email not found for user_id=' . $userId
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
