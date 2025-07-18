<?php
// /stakeholder-feedback/feedback-form-request.php

session_start();

$packageId     = isset($_GET['package_id']) ? intval($_GET['package_id']) : null;
$focusAreaName = isset($_GET['focus_area_name']) ? trim($_GET['focus_area_name']) : null;

if (!$packageId || !$focusAreaName) {
    echo 'Invalid request.';
    exit;
}

// Attempt to load the actual focus_areas_id for this package + focus area name
require_once '../includes/db_connection.php';
$sqlFa = "
    SELECT id
      FROM analysis_package_focus_areas
     WHERE analysis_package_headers_id = :pkgId
       AND focus_area_name = :faName
       AND is_deleted = 0
     LIMIT 1
";
$stmtFa = $pdo->prepare($sqlFa);
$stmtFa->execute([
    ':pkgId'  => $packageId,
    ':faName' => $focusAreaName
]);
$faRow = $stmtFa->fetch(PDO::FETCH_ASSOC);

if (!$faRow) {
    // If we fail to find an active focus area by that name, we can’t proceed
    echo 'Invalid request. Focus area name not found in database.';
    exit;
}
$focusAreasId = (int)$faRow['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionalMessage  = trim($_POST['optional_message'] ?? '');
    $stakeholderEmail = $_SESSION['stakeholder_email'] ?? null;
    if (!$stakeholderEmail) {
        echo 'Session expired. Please access the form from the confirmation email.';
        exit;
    }

    try {
        $sql = "
            INSERT INTO stakeholder_feedback_header_requests
            (
              stakeholder_email,
              analysis_package_headers_id,
              focus_areas_id,
              message,
              created_at
            )
            VALUES
            (
              :email,
              :pkgId,
              :faId,
              :msg,
              NOW()
            )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email' => $stakeholderEmail,
            ':pkgId' => $packageId,
            ':faId'  => $focusAreasId,
            ':msg'   => $optionalMessage
        ]);
        $message = 'Your request has been submitted. We will get back to you shortly.';
    } catch (Exception $ex) {
        // Log error and show a friendly message
        error_log('Error in feedback-form-request.php: ' . $ex->getMessage());
        $message = 'An unexpected error occurred submitting your request.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feedback Form Request</title>
    <!-- The new CSS file for styling -->
    <link rel="stylesheet" type="text/css" href="/assets/css/feedback-form-request.css">
</head>
<body>
    <div class="container">
        <h1>Request a New Feedback Form</h1>
        <?php if (isset($message)) { ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php } else { ?>
            <form method="post" action="">
                <p>You are requesting a new feedback form for:</p>
                <p><strong>Package ID:</strong> <?php echo htmlspecialchars($packageId); ?></p>
                <p><strong>Focus Area:</strong> <?php echo htmlspecialchars($focusAreaName); ?></p>
                <textarea
                  name="optional_message"
                  placeholder="Optional message to the AxiaBA analyst..."
                ></textarea>
                <br>
                <button type="submit">Submit Request</button>
            </form>
        <?php } ?>
    </div>
</body>
</html>
