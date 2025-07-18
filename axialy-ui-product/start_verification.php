<?php
/**
 * /app.axialy.ai/start_verification.php
 *
 * Starts the e-mail–verification flow for creating a new AxiaBA account.
 * Expects: POST { email=<address> }
 * Returns: JSON { status: "success" | "error", message: "<text>" }
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/account_creation.php';

header('Content-Type: application/json; charset=utf-8');

/*-------------------------------------------------
 | 1. Only allow POST
 *------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);               // Method Not Allowed
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

/*-------------------------------------------------
 | 2. Basic validation of the e-mail field
 *------------------------------------------------*/
$email = trim($_POST['email'] ?? '');
$accountCreation = new AccountCreation($pdo);

try {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please provide a valid e-mail address.');
    }

    if ($accountCreation->checkEmailExists($email)) {
        throw new RuntimeException('This e-mail address is already registered.');
    }

    /*---------------------------------------------
     | 3. Create token & attempt to send e-mail
     *-------------------------------------------*/
    $token = $accountCreation->createVerificationToken($email);

    if (!$accountCreation->sendVerificationEmail($email, $token)) {
        /* 3a.  If sending failed, delete the token so the
         *       user can retry immediately, then raise error.
         */
        $stmt = $pdo->prepare('DELETE FROM email_verifications WHERE token = ?');
        $stmt->execute([$token]);

        throw new RuntimeException('Failed to send verification e-mail. Please try again later.');
    }

    /*---------------------------------------------
     | 4. All good – return success JSON
     *-------------------------------------------*/
    echo json_encode([
        'status'  => 'success',
        'message' => 'Verification e-mail sent. Please check your inbox.'
    ]);
    http_response_code(200);
    exit;

} catch (Throwable $e) {
    /*-------------------------------------------------
     | 5. Error handling & logging
     *------------------------------------------------*/
    error_log('[AxiaBA] start_verification error: ' . $e->getMessage());

    // 400 = Bad Request is fine for all user-triggered errors here
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
