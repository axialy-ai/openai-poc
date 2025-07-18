<?php
// /app.axialy.ai/login.php
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in
if (validateSession()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    debugLog("Login attempt", ['username' => $username]);
    if (!empty($username) && !empty($password)) {
        try {
            // Clear any existing session data
            session_unset();

            // First verify the user exists and get their data
            $stmt = $pdo->prepare('SELECT id, username, password, default_organization_id FROM ui_users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                debugLog("Password verified successfully", ['userId' => $user['id']]);

                // Clean up any existing UI sessions for this user (but do not touch admin sessions!)
                $cleanup = $pdo->prepare("
                    DELETE FROM ui_user_sessions
                     WHERE user_id = ?
                       AND product = 'ui'
                ");
                $cleanup->execute([$user['id']]);

                // Generate session token
                $sessionToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Store session with product='ui'
                $stmt = $pdo->prepare('
                    INSERT INTO ui_user_sessions (user_id, session_token, product, expires_at)
                    VALUES (?, ?, "ui", ?)
                ');
                $stmt->execute([$user['id'], $sessionToken, $expiresAt]);

                // Set session variables
                $_SESSION['user_id']                 = $user['id'];
                $_SESSION['session_token']           = $sessionToken;
                $_SESSION['default_organization_id'] = $user['default_organization_id'];

                debugLog("Session created successfully", [
                    'sessionId' => session_id(),
                    'userId'    => $user['id']
                ]);
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            debugLog("Database error", ['error' => $e->getMessage()]);
            $error = 'Login failed. Please try again.';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AxiaBA Login</title>
    <link rel="stylesheet" href="/assets/css/desktop.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .success {
            color: #28a745;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 4px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        button:hover {
            background: #0056b3;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-width: 200px;
            height: auto;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .create-account, .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .create-account a, .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        .create-account a:hover, .login-link a:hover {
            text-decoration: underline;
        }
        .page-mask {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .mask-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .mask-message {
            margin-top: 15px;
            color: #333;
            font-size: 16px;
            line-height: 1.4;
        }
        /* Show/Hide password toggle link */
        .toggle-visibility {
            cursor: pointer;
            color: #007bff;
            margin-left: 8px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <!-- Page Mask -->
    <div id="pageMask" class="page-mask">
        <div class="mask-content">
            <div class="spinner"></div>
            <div class="mask-message" id="maskMessage">
                Sending verification email...<br>
                Please wait while we process your request.
            </div>
        </div>
    </div>
    <div class="login-container">
        <div class="login-logo">
            <img src="/assets/img/product_logo.png" alt="AxiaBA Logo">
        </div>
        
        <!-- Login Form -->
        <div id="loginForm">
            <h2>AxiaBA Login</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['account_created'])): ?>
                <div class="success">Account created successfully! Please log in.</div>
            <?php endif; ?>
            <?php if (isset($_GET['logged_out'])): ?>
                <div class="success">You have been successfully logged out.</div>
            <?php endif; ?>
            <?php if (isset($_GET['logout_error'])): ?>
                <div class="error">There was an issue during logout, but you have been signed out successfully.</div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password
                        <span class="toggle-visibility" onclick="toggleVisibility('password')">Show</span>
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Log In</button>
            </form>
            
            <div class="create-account">
                <p>Don't have an account? <a href="#" id="showCreateAccount">Create Account</a></p>
            </div>
        </div>
        
        <!-- Create Account Form -->
        <div id="createAccountForm" style="display: none;">
            <h2>Create AxiaBA Account</h2>
            <div class="error" id="createAccountError" style="display: none;"></div>
            
            <form id="emailVerificationForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit">Start Verification</button>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="#" id="showLogin">Log In</a></p>
            </div>
        </div>
    </div>
    <script>
    function showPageMask(message) {
        const pageMask = document.getElementById('pageMask');
        const maskMessage = document.getElementById('maskMessage');
        maskMessage.innerHTML = message;
        pageMask.style.display = 'flex';
    }
    function hidePageMask() {
        const pageMask = document.getElementById('pageMask');
        pageMask.style.display = 'none';
    }
    // Password visibility toggle
    function toggleVisibility(fieldId) {
        var field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }
    // Show/hide the create account form
    document.getElementById('showCreateAccount').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('createAccountForm').style.display = 'block';
    });
    document.getElementById('showLogin').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('createAccountForm').style.display = 'none';
        document.getElementById('loginForm').style.display = 'block';
    });
    // Create account form submit
    document.getElementById('emailVerificationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const errorDiv = document.getElementById('createAccountError');
        
        showPageMask('Sending verification email to ' + email + '...<br>Please wait while we process your request.');
        
        fetch('/start_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            hidePageMask();
            
            if (data.status === 'success') {
                document.getElementById('createAccountForm').innerHTML = `
                    <h2>Verification Email Sent</h2>
                    <div class="success" style="margin-bottom: 20px;">
                        <p>We've sent a verification email to <strong>${email}</strong></p>
                        <p>Please check your inbox and click the verification link to complete your account setup.</p>
                        <p>Don't see the email? Check your spam folder.</p>
                    </div>
                    <div class="login-link">
                        <p><a href="/login.php">Return to Login</a></p>
                    </div>
                `;
            } else {
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            hidePageMask();
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
        });
    });
    </script>
</body>
</html>
