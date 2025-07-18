<?php
// /stakeholder-feedback/feedback-confirmation.php

session_start();
if (!isset($_SESSION['stakeholder_email'])) {
    echo 'Access denied. Missing stakeholder email.';
    exit;
}

$stakeholderEmail   = $_SESSION['stakeholder_email'];
$feedbackDetailsId  = $_SESSION['stakeholder_feedback_details_id'] ?? null;

require_once '../includes/db_connection.php';

// 1) Count how many *pending* feedback requests for this email
//    "Pending" means responded_at IS NULL.
$sqlPendingCount = "
    SELECT COUNT(*) AS pending_count
      FROM stakeholder_feedback_headers sfh
     WHERE sfh.stakeholder_email = :email
       AND sfh.responded_at IS NULL
";
$stmtCount = $pdo->prepare($sqlPendingCount);
$stmtCount->execute([':email' => $stakeholderEmail]);
$resultCount  = $stmtCount->fetch(PDO::FETCH_ASSOC);
$pendingCount = $resultCount['pending_count'] ?? 0;

// 2) Fetch pending feedback requests in detail
//    Updated to use `analysis_package_focus_areas_id` from the new schema.
$sqlPending = "
    SELECT
      sfh.id,
      sfh.analysis_package_headers_id,
      sfh.pin,
      sfh.form_type,
      sfh.created_at AS request_time,
      sfh.token,
      aph.package_name,
      fa.focus_area_name,
      fav.focus_area_version_number AS version
    FROM stakeholder_feedback_headers sfh
    INNER JOIN analysis_package_headers aph
      ON sfh.analysis_package_headers_id = aph.id
    INNER JOIN analysis_package_focus_areas fa
      ON sfh.analysis_package_focus_areas_id = fa.id
    INNER JOIN analysis_package_focus_area_versions fav
      ON sfh.analysis_package_focus_area_versions_id = fav.id
    WHERE sfh.stakeholder_email = :email
      AND sfh.responded_at IS NULL
    ORDER BY sfh.created_at DESC
";
$stmtPending = $pdo->prepare($sqlPending);
$stmtPending->execute([':email' => $stakeholderEmail]);
$pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// 3) If user posts user_experience_feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_experience_feedback'])) {
    $userExperienceFeedback = trim($_POST['user_experience_feedback']);
    if (!empty($userExperienceFeedback)) {
        if ($feedbackDetailsId === null) {
            $feedbackError = 'Feedback details not available.';
        } else {
            $sqlInsertUx = "
                INSERT INTO stakeholder_experience_feedback
                (stakeholder_feedback_details_id, feedback_text, created_at)
                VALUES (:details_id, :feedback_text, NOW())
            ";
            $stmtUx = $pdo->prepare($sqlInsertUx);
            $stmtUx->execute([
                ':details_id'   => $feedbackDetailsId,
                ':feedback_text'=> $userExperienceFeedback
            ]);
            $feedbackMessage = 'Thank you for your feedback!';
        }
    } else {
        $feedbackError = 'Feedback cannot be empty.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feedback Confirmation</title>
    <link rel="stylesheet" type="text/css" href="/assets/css/feedback-confirmation.css">
</head>
<body>
    <div style="text-align: center; margin: 20px 0;">
      <a href="https://axialy.ai" target="_blank" rel="noopener noreferrer">
        <img src="/assets/img/product_logo.png" alt="AxiaBA Logo" style="max-height:50px;">
      </a>
    </div>

    <div class="container">
        <h1>Feedback Submitted</h1>
        <p>Thank you for your feedback! A confirmation email has been sent to your inbox.</p>

        <?php if (isset($feedbackMessage)) { ?>
            <p class="message"><?php echo htmlspecialchars($feedbackMessage); ?></p>
        <?php } ?>

        <?php if (isset($feedbackError)) { ?>
            <p class="error"><?php echo htmlspecialchars($feedbackError); ?></p>
        <?php } ?>

        <h2>We value your experience!</h2>
        <p>We would love to hear about your user experience providing this feedback.</p>
        <button id="provide-experience-feedback-btn">Provide Feedback</button>

        <h2>You have <?php echo (int)$pendingCount; ?> pending feedback requests</h2>
        <button id="view-pending-requests-btn">View Pending Requests</button>
        <button onclick="window.close();">Close Page</button>
    </div>

    <!-- Overlay for user_experience_feedback -->
    <div class="overlay" id="experience-feedback-overlay">
        <div class="overlay-content">
            <span class="close-overlay" id="close-experience-overlay">&times;</span>
            <h2>Share Your Experience</h2>
            <p>Please let us know how we can improve.</p>
            <form method="post" action="">
                <textarea name="user_experience_feedback" required placeholder="Enter your feedback here..."></textarea>
                <br>
                <button type="submit">Submit Feedback</button>
            </form>
        </div>
    </div>

    <!-- Overlay for pending feedback requests -->
    <div class="overlay" id="pending-requests-overlay">
        <div class="overlay-content">
            <span class="close-overlay" id="close-pending-overlay">&times;</span>
            <h2>Pending Feedback Requests</h2>

            <?php if ($pendingCount > 0) { ?>
                <table>
                    <tr>
                        <th>Package ID</th>
                        <th>Package Name</th>
                        <th>Focus Area</th>
                        <th>Version</th>
                        <th>Request Type</th>
                        <th>Request Time</th>
                        <th>PIN</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($pendingRequests as $request) { ?>
                        <tr>
                            <td><?php echo (int)$request['analysis_package_headers_id']; ?></td>
                            <td><?php echo htmlspecialchars($request['package_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['focus_area_name']); ?></td>
                            <td><?php echo (int)$request['version']; ?></td>
                            <td><?php echo htmlspecialchars($request['form_type']); ?></td>
                            <td><?php echo htmlspecialchars($request['request_time']); ?></td>
                            <td><?php echo htmlspecialchars($request['pin']); ?></td>
                            <td>
                              <!-- This link currently uses the `id` field for the token param. 
                                   If you'd prefer to use the real token, replace $request['id'] with $request['token']. -->
                              <a href="stakeholder-content-review.php?token=<?php echo urlencode($request['token']); ?>">
                                Provide Feedback
                              </a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <p>You have no pending feedback requests.</p>
            <?php } ?>
        </div>
    </div>

    <script src="/js/feedback-confirmation.js"></script>
</body>
</html>
