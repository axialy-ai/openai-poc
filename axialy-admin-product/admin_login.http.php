<?php
/*********************************************************************
 *  SESSION SET-UP (with cookie persistence handling)
 *********************************************************************/
$isSecure = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    ($_SERVER['SERVER_PORT'] == 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
);

// Resurrect session via hidden sid field if cookie doesn't return
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && empty($_COOKIE['axialy_admin_session']) 
    && !empty($_POST['sid'])) {
    $sid = preg_replace('/[^A-Za-z0-9]/', '', $_POST['sid']);
    if ($sid) {
        session_id($sid);
    }
}

// Configure session parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isSecure,
    'httponly' => true,
    'samesite' => $isSecure ? 'Strict' : 'Lax'
]);

session_name('axialy_admin_session');
session_start();

// Force cookie to be sent if browser didn't send one
if (empty($_COOKIE[session_name()])) {
    setcookie(session_name(), session_id(), [
        'expires'  => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => $isSecure ? 'Strict' : 'Lax'
    ]);
}

require_once __DIR__ . '/includes/AdminDBConfig.php';
use Axialy\AdminConfig\AdminDBConfig;

/** ------------------------------------------------------------------
 *  CSRF Token helper (generate but don't validate on login)
 *  ----------------------------------------------------------------*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$errorMessage = '';

/** ------------------------------------------------------------------
 *  Handle POST (login attempt) - NO CSRF CHECK
 *  ----------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // NO CSRF validation on login page to avoid session issues
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $errorMessage = 'Please enter your username and password.';
    } else {
        try {
            // Connect to axialy_admin
            $adminDB = AdminDBConfig::getInstance()->getPdo();

            // Lookup admin user
            $stmt = $adminDB->prepare(
                "SELECT * FROM admin_users WHERE username = :u LIMIT 1"
            );
            $stmt->execute([':u' => $username]);
            $adminUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$adminUser) {
                $errorMessage = 'Invalid credentials.';
            } elseif ((int)$adminUser['is_active'] !== 1) {
                $errorMessage = 'This admin account is disabled.';
            } elseif (!password_verify($password, $adminUser['password'])) {
                $errorMessage = 'Invalid credentials.';
            } else {
                /* ---- Success: remove old sessions & create new -----*/
                $del = $adminDB->prepare(
                    "DELETE FROM admin_user_sessions WHERE admin_user_id = :uid"
                );
                $del->execute([':uid' => $adminUser['id']]);

                $sessionToken = bin2hex(random_bytes(32));
                $expiresAt    = date('Y-m-d H:i:s', strtotime('+4 hours'));

                $ins = $adminDB->prepare(
                    "INSERT INTO admin_user_sessions
                         (admin_user_id, session_token, created_at, expires_at)
                     VALUES (:uid, :tok, NOW(), :exp)"
                );
                $ins->execute([
                    ':uid' => $adminUser['id'],
                    ':tok' => $sessionToken,
                    ':exp' => $expiresAt,
                ]);

                // Persist identifiers in PHP session
                $_SESSION['admin_user_id']       = $adminUser['id'];
                $_SESSION['admin_session_token'] = $sessionToken;

                header('Location: index.php');
                exit;
            }
        } catch (\Exception $ex) {
            error_log("Admin login error: " . $ex->getMessage());
            $errorMessage = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Axialy Admin Login</title>
  <style>
    body {
      font-family: sans-serif;
      background: #f4f4f4;
      margin: 0;
      padding: 0;
    }
    .header {
      background: #fff;
      padding: 15px;
      border-bottom: 1px solid #ddd;
      text-align: center;
    }
    .header img {
      height: 50px;
    }
    .login-container {
      max-width: 400px;
      margin: 40px auto;
      background: #fff;
      padding: 20px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    h2 {
      margin-top: 0;
      text-align: center;
    }
    .error {
      color: red;
      margin-bottom: 1em;
      text-align: center;
    }
    label {
      display: block;
      margin-top: 1em;
      font-weight: bold;
    }
    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 8px;
      box-sizing: border-box;
      margin-top: 4px;
    }
    button {
      margin-top: 1.5em;
      padding: 10px 20px;
      cursor: pointer;
      width: 100%;
      background: #007BFF;
      color: #fff;
      border: none;
      border-radius: 4px;
      font-size: 16px;
    }
    button:hover {
      background: #0056b3;
    }
    /* Responsive breakpoint */
    @media (max-width: 768px) {
      body { flex-direction: column; height: auto; }
      .login-container { margin: 20px; max-width: none; }
    }
  </style>
</head>
<body>
  <div class="header">
    <img src="https://axiaba.com/assets/img/SOI.png" alt="Axialy Logo">
  </div>

  <div class="login-container">
    <h2>Admin Login</h2>

    <?php if ($errorMessage): ?>
      <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
      <!-- Keep session ID alive even if cookie is dropped -->
      <input type="hidden" name="sid" value="<?php echo htmlspecialchars(session_id()); ?>">
      
      <!-- Keep CSRF token for other pages but don't validate here -->
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

      <label>Username:
        <input type="text" name="username" autofocus required>
      </label>

      <label>Password:
        <input type="password" name="password" required>
      </label>

      <button type="submit">Log In</button>
    </form>
  </div>
</body>
</html>
