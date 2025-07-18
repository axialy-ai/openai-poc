<?php
require_once 'includes/db_connection.php';

$config = require '/home/i17z4s936h3j/private_axiaba/includes/Config.php';// <<<clide>>>

header('Content-Type: application/json');

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}
function sendEmail($to, $link) {
    $subject = "Content Review Request";
    $message = "Hello,\n\nYou have been requested to review the content in AxiaBA. Please click the link below to perform the content review:\n\n$link\n\nThank you.";
    $headers = "From: no-reply@axialy.ai\r\n" .
               "Reply-To: no-reply@axialy.ai\r\n" .
               "X-Mailer: PHP/" . phpversion();
    return mail($to, $subject, $message, $headers);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['emails']) || !is_array($data['emails']) || empty($data['emails'])) {
    echo json_encode(['success' => false, 'message' => 'No emails provided.']);
    exit;
}
$emails = array_map('trim', $data['emails']);
$feedback = isset($data['feedback']) ? trim($data['feedback']) : '';
$package_id = isset($data['package_id']) ? intval($data['package_id']) : 0;
if ($package_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid package ID.']);
    exit;
}
if (strlen($feedback) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Feedback exceeds maximum allowed length of 1000 characters.']);
    exit;
}

$failedEmails = [];
foreach ($emails as $email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $failedEmails[] = $email;
        continue;
    }
    $token = generateToken();
    $review_link = rtrim($config['app_base_url'], '/') . "/content_review_form.php?token=$token";
    try {
        $stmt = $pdo->prepare("INSERT INTO content_reviews (package_id, email, token, feedback, created_at, completed) VALUES (:package_id, :email, :token, :feedback, NOW(), 0)");
        $stmt->execute([
            ':package_id' => $package_id,
            ':email' => $email,
            ':token' => $token,
            ':feedback' => $feedback
        ]);
    } catch (PDOException $e) {
        error_log('Database insert failed for email ' . $email . ': ' . $e->getMessage());
        $failedEmails[] = $email;
        continue;
    }
    if (!sendEmail($email, $review_link)) {
        $failedEmails[] = $email;
    }
}

if (empty($failedEmails)) {
    echo json_encode(['success' => true]);
} else {
    $message = 'Failed to send emails to: ' . implode(', ', $failedEmails);
    echo json_encode(['success' => false, 'message' => $message]);
}
?>
