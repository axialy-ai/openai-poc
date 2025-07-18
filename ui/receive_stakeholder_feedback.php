<?php
// /receive_stakeholder_feedback.php
require_once 'includes/db_connection.php';
session_start();

// Check if token is provided via GET or session
if (!isset($_GET['token']) && !isset($_SESSION['stakeholder_feedback'])) {
    echo 'Invalid access. No token provided.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $pin   = isset($_POST['pin'])   ? $_POST['pin']   : '';

    if (empty($pin) || !preg_match('/^\d{4}$/', $pin)) {
        $error = 'Please enter a valid 4-digit PIN.';
    } else {
        $sql = "SELECT id, feedback_target, stakeholder_email
                FROM stakeholder_feedback_headers
                WHERE token = :token
                  AND pin = :pin
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token, ':pin' => $pin]);
        $feedbackHeader = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($feedbackHeader) {
            // Create stakeholder session
            $_SESSION['stakeholder_feedback'] = [
                'stakeholder_feedback_headers_id' => $feedbackHeader['id'],
                'session_type'                    => 'stakeholder_feedback'
            ];
            // Also store stakeholder_email so the /stakeholder-feedback/feedback-confirmation.php page can be accessed
            if (!empty($feedbackHeader['stakeholder_email'])) {
                $_SESSION['stakeholder_email'] = $feedbackHeader['stakeholder_email'];
            }

            // Insert into stakeholder_sessions table
            $sessionToken = bin2hex(random_bytes(16));
            $sql = "INSERT INTO stakeholder_sessions (stakeholder_feedback_headers_id, session_token, created_at)
                    VALUES (:header_id, :session_token, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':header_id'     => $feedbackHeader['id'],
                ':session_token' => $sessionToken
            ]);

            // Redirect
            $feedbackTarget = '/stakeholder-feedback/stakeholder-content-review.php';
            header('Location: ' . $feedbackTarget);
            exit;
        } else {
            // Log the failed attempt
            error_log("Invalid token or PIN attempt: Token={$token}, PIN={$pin}");
            $error = 'Invalid PIN. Please try again.';
        }
    }
} else {
    // First time
    $token = isset($_GET['token']) ? $_GET['token'] : '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stakeholder Feedback - PIN Entry</title>
    <style>
        .container { max-width: 400px; margin: 50px auto; text-align: center; }
        input[type="text"], input[type="submit"] {
            padding: 10px; margin: 10px; width: 80%;
        }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h1>Enter Your 4-Digit PIN</h1>
    <?php if (isset($error)) { echo '<p class="error">' . htmlspecialchars($error) . '</p>'; } ?>
    <form method="post" action="">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="text" name="pin" placeholder="4-Digit PIN" maxlength="4" pattern="\d{4}" required>
        <br>
        <input type="submit" value="Go">
    </form>
</div>
</body>
</html>
