<?php
// /accept_tos.php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth.php';

// We must have a valid session to know who the user is.
// But we skip "subscription active" checks so that the user can accept the ToS 
// even if subscription is inactive or in a trial. So we do only "session" check here.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in at all, go to login.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header('Location: login.php');
    exit;
}

/**
 * 1) Look up the active version of doc_key='tos'.
 */
$stmtDoc = $pdo->prepare("
    SELECT id, active_version_id
    FROM documents
    WHERE doc_key = 'tos'
    LIMIT 1
");
$stmtDoc->execute();
$doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

if (!$doc || !$doc['active_version_id']) {
    // If there is NO TOS document or no active version, 
    // we can't require acceptance. Let the user proceed.
    header('Location: index.php');
    exit;
}

// For convenience:
$activeVersionId = $doc['active_version_id'];

/**
 * 2) Check if user already accepted this version.
 */
$stmtCheck = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_agreement_acceptances
    WHERE user_id = ? 
      AND document_versions_id = ?
");
$stmtCheck->execute([$_SESSION['user_id'], $activeVersionId]);
$alreadyAccepted = ($stmtCheck->fetchColumn() > 0);

// If user has already accepted, go straight to the main app.
if ($alreadyAccepted) {
    header('Location: index.php');
    exit;
}

// 3) If form POSTed with "accept_tos = 1", insert acceptance row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_tos'])) {
    if (isset($_POST['accept_checkbox']) && $_POST['accept_checkbox'] === '1') {
        // Insert row into user_agreement_acceptances
        $stmtInsert = $pdo->prepare("
            INSERT INTO user_agreement_acceptances (user_id, document_versions_id, accepted_at)
            VALUES (?, ?, NOW())
        ");
        $stmtInsert->execute([$_SESSION['user_id'], $activeVersionId]);
        
        // Now the user is good to go:
        header('Location: index.php');
        exit;
    } else {
        // They pressed the button but didn't check the box
        $error = "You must check the box to accept the Terms of Service.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Accept AxiaBA Terms of Service</title>
  <link rel="stylesheet" href="/assets/css/desktop.css">
  <style>
      body {
          max-width: 600px;
          margin: 60px auto;
          padding: 10px;
          font-family: Arial, sans-serif;
      }
      .tos-container {
          margin-top: 30px;
          padding: 20px;
          border: 1px solid #ccc;
          background: #fff;
          border-radius: 4px;
      }
      .error-message {
          background: #f8d7da;
          color: #721c24;
          border: 1px solid #f5c6cb;
          border-radius: 4px;
          padding: 10px;
          margin-bottom: 20px;
      }
      .tos-title {
          font-size: 1.4em;
          margin-bottom: 10px;
          font-weight: bold;
          color: #333;
      }
      .accept-section {
          margin-top: 20px;
      }
      .button-submit {
          background: #007bff;
          color: white;
          border: none;
          padding: 12px 24px;
          border-radius: 4px;
          cursor: pointer;
          font-size: 16px;
          width: 100%;
          margin-top: 20px;
      }
      .accept-label {
          display: flex;
          align-items: center;
      }
      .accept-label input[type="checkbox"] {
          margin-right: 8px;
      }
  </style>
</head>
<body>
  <div class="tos-container">
    <h1 class="tos-title">AxiaBA Terms of Service</h1>
    
    <?php if (!empty($error)): ?>
      <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <p>
      Please review our Terms of Service. You must accept the most recent version before continuing to use AxiaBA.
    </p>
    <p>
      <a href="/docs/view_document.php?key=tos" target="_blank">
        View Full Terms of Service
      </a>
    </p>
    
    <form method="POST">
      <div class="accept-section">
        <label class="accept-label">
          <input type="checkbox" name="accept_checkbox" value="1">
          I have read and agree to the Terms of Service
        </label>
      </div>
      <input type="hidden" name="accept_tos" value="1">
      <button type="submit" class="button-submit">Continue</button>
    </form>
  </div>
</body>
</html>
