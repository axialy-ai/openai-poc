<?php
// /app.axialy.ai/log_errors.php (or any path you prefer)
header('Content-Type: text/plain');
// We can just read the incoming message and discard or log it:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? $_POST['message'] : 'No message received.';
    // Here you could log to a file, or simply discard
    error_log('[log_errors.php] ' . $message);
}
echo "OK";
?>
