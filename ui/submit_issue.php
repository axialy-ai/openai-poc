<?php
// /app.axialy.ai/submit_issue.php
require_once __DIR__ . '/includes/auth.php';
requireAuth(); // ensure user is logged in
require_once __DIR__ . '/includes/db_connection.php';

header('Content-Type: application/json');

try {
    // read JSON payload
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!isset($data['title'], $data['description'])) {
        throw new Exception('Missing required fields.');
    }
    $title = trim($data['title']);
    $desc  = trim($data['description']);
    if ($title === '' || $desc === '') {
        throw new Exception('Empty title or description.');
    }

    // Insert into DB
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        INSERT INTO issues (user_id, issue_title, issue_description, status, created_at)
        VALUES (:uid, :title, :descr, 'Open', NOW())
    ");
    $stmt->execute([
        ':uid'   => $userId,
        ':title' => $title,
        ':descr' => $desc
    ]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message'=> $e->getMessage()
    ]);
}
