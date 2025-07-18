<?php
// /app.axialy.ai/verify_email.php
require_once 'includes/db_connection.php';
require_once 'includes/account_creation.php';

$token = $_GET['token'] ?? '';
$error = '';
$email = '';
$verified = false;

$accountCreation = new AccountCreation($pdo);

if ($token) {
    $email = $accountCreation->verifyToken($token);
    if ($email) {
        $verified = true;
    } else {
        $error = 'Invalid or expired verification token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $verified) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match. Please re-enter your password.';
    } else {
        if ($accountCreation->createAccount($email, $username, $password)) {
            header('Location: login.php?account_created=1');
            exit;
        } else {
            $error = 'Account creation failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Account Creation - AxiaBA</title>
    <link rel="stylesheet" href="/assets/css/desktop.css">
    <style>
        .verification-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px 25px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.4em;
            color: #333;
        }
        .error {
            color: #dc3545;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #f5c6cb;
            background-color: #f8d7da;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: inline-block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.2);
        }
        .toggle-visibility {
            cursor: pointer;
            color: #007bff;
            margin-left: 8px;
            font-size: 0.85em;
        }
        .toggle-visibility:hover {
            text-decoration: underline;
        }
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 123, 255, 0.3);
            transition: background-color 0.3s ease;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .not-verified {
            margin-top: 10px;
            text-align: center;
        }
        .not-verified a {
            color: #007bff;
            text-decoration: none;
        }
        .not-verified a:hover {
            text-decoration: underline;
        }
        /* Page Mask Overlay */
        #pageMask {
            display: none;
            position: fixed;
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        #maskContent {
            background: #fff;
            padding: 30px;
            border-radius: 6px;
            max-width: 400px;
            width: 80%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .maskSpinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            margin: 0 auto 15px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); }
        }
        .maskMessage {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
        }
        .maskDynamic {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
<div id="pageMask">
    <div id="maskContent">
        <div class="maskSpinner"></div>
        <div class="maskMessage" id="maskMessage">Creating your account, please wait...</div>
        <div class="maskDynamic" id="maskDynamic"></div>
    </div>
</div>

<div class="verification-container">
    <h2>Complete Account Creation</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($verified): ?>
        <p style="margin-bottom: 20px;">Email verified: <strong><?php echo htmlspecialchars($email); ?></strong></p>
        <form method="POST" action="" onsubmit="return showMaskAndSubmit();">
            <div class="form-group" style="position:relative;">
                <label for="username">Choose Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group" style="position:relative;">
                <label for="password">Choose Password</label>
                <input type="password" id="password" name="password" required>
                <span class="toggle-visibility" onclick="toggleVisibility('password')">Show</span>
            </div>
            <div class="form-group" style="position:relative;">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <span class="toggle-visibility" onclick="toggleVisibility('confirm_password')">Show</span>
            </div>
            <button type="submit">Create Account</button>
        </form>
    <?php else: ?>
        <div class="not-verified">
            <p>Please use the verification link sent to your email.</p>
            <p><a href="login.php">Return to Login</a></p>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleVisibility(fieldId) {
    var field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function showMaskAndSubmit() {
    var pw   = document.getElementById('password').value.trim();
    var conf = document.getElementById('confirm_password').value.trim();
    if (pw !== conf) {
        alert('Passwords do not match. Please re-check.');
        return false;
    }

    // Show page mask
    const pageMask = document.getElementById('pageMask');
    pageMask.style.display = 'flex';

    // Start the dynamic text cycle
    const dynamicMsgs = [
        "Setting up your new workspace...",
        "Configuring your user credentials...",
        "Creating a default organization...",
        "Finalizing your account..."
    ];
    let idx = 0;
    const dynamicElem = document.getElementById('maskDynamic');
    dynamicElem.innerText = dynamicMsgs[idx];
    const intervalId = setInterval(() => {
        idx = (idx + 1) % dynamicMsgs.length;
        dynamicElem.innerText = dynamicMsgs[idx];
    }, 1200);

    // Once the form is actually submitted to the server, the page transitions anyway
    // We just keep the mask up until the server responds.
    return true;
}
</script>

</body>
</html>
