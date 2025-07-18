<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

// ----------------------------------------------------------------------
// 1) Validate session for stakeholder feedback
// ----------------------------------------------------------------------
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $sql   = "
        SELECT *
          FROM stakeholder_feedback_headers
         WHERE token = :token
         LIMIT 1
    ";
    $stmt  = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);
    $feedbackDataRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($feedbackDataRow) {
        $_SESSION['stakeholder_feedback'] = [
            'session_type'                    => 'stakeholder_feedback',
            'stakeholder_feedback_headers_id' => $feedbackDataRow['id']
        ];
        // Also store stakeholder_email in session for future requests
        $_SESSION['stakeholder_email'] = $feedbackDataRow['stakeholder_email'];
    } else {
        echo 'Invalid or expired token.';
        exit;
    }
}
if (
    !isset($_SESSION['stakeholder_feedback']) ||
    $_SESSION['stakeholder_feedback']['session_type'] !== 'stakeholder_feedback'
) {
    echo 'Access denied. Missing token.';
    exit;
}
$feedbackHeaderId = (int)$_SESSION['stakeholder_feedback']['stakeholder_feedback_headers_id'];

// ----------------------------------------------------------------------
// 2) Load stakeholder_feedback_headers + package info, also load version info
// ----------------------------------------------------------------------
$sql = "
    SELECT
      sfh.*,
      aph.package_name,
      aph.short_summary,
      aph.custom_organization_id,
      fa.focus_area_name,
      sfh.form_type,
      sfh.primary_response_option,
      sfh.secondary_response_option,
      sfh.email_personal_message,
      sfh.stakeholder_request_grid_indexes,
      fav.focus_area_version_number AS focus_area_version_num,
      sfh.responded_at
    FROM stakeholder_feedback_headers sfh
    INNER JOIN analysis_package_headers aph
      ON sfh.analysis_package_headers_id = aph.id
    INNER JOIN analysis_package_focus_areas fa
      ON sfh.analysis_package_focus_areas_id = fa.id
    INNER JOIN analysis_package_focus_area_versions fav
      ON fav.id = sfh.analysis_package_focus_area_versions_id
    WHERE sfh.id = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $feedbackHeaderId]);
$feedbackData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$feedbackData) {
    echo 'Invalid feedback session.';
    exit;
}

// ----------------------------------------------------------------------
// 3) Retrieve org branding if needed
// ----------------------------------------------------------------------
$logoUrl    = '/assets/img/product_logo.png';
$orgWebsite = '';
$orgId      = $feedbackData['custom_organization_id'];
if (!empty($orgId) && $orgId !== 'default') {
    $sqlOrg  = "SELECT logo_path, website FROM custom_organizations WHERE id = :orgId LIMIT 1";
    $stmtOrg = $pdo->prepare($sqlOrg);
    $stmtOrg->execute([':orgId' => $orgId]);
    $orgRow  = $stmtOrg->fetch(\PDO::FETCH_ASSOC);
    if ($orgRow) {
        if (!empty($orgRow['logo_path'])) {
            $logoUrl = '/serve_logo.php?file=' . urlencode($orgRow['logo_path']);
        }
        if (!empty($orgRow['website'])) {
            $orgWebsite = $orgRow['website'];
        }
    }
}

$formType                = $feedbackData['form_type']                ?: 'General';
$primaryResponseOption   = $feedbackData['primary_response_option']   ?: '';
$secondaryResponseOption = $feedbackData['secondary_response_option'] ?: '';
$feedbackSubmitted       = !empty($feedbackData['responded_at']);

// ----------------------------------------------------------------------
// 4) Handle final submission (POST)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($feedbackSubmitted) {
        $error = 'Feedback already submitted.';
    } else {
        $feedbackText = trim($_POST['feedback'] ?? '');
        $actions      = $_POST['actions']      ?? [];

        if ($formType === 'General' && empty($feedbackText)) {
            $error = 'Please enter your general feedback before submitting.';
        }
        if (!isset($error)) {
            try {
                $pdo->beginTransaction();
                // Mark responded_at
                $updSql = "
                    UPDATE stakeholder_feedback_headers
                       SET responded_at = NOW()
                     WHERE id = :hid
                ";
                $updStmt = $pdo->prepare($updSql);
                $updStmt->execute([':hid' => $feedbackHeaderId]);

                $analysisPackageId = (int)$feedbackData['analysis_package_headers_id'];
                $focusAreasId      = (int)$feedbackData['analysis_package_focus_areas_id'];
                $focusAreaVersId   = (int)$feedbackData['analysis_package_focus_area_versions_id'];

                // If General
                if ($formType === 'General') {
                    $insDet = "
                        INSERT INTO stakeholder_general_feedback
                        (
                            stakeholder_feedback_headers_id,
                            analysis_package_headers_id,
                            analysis_package_focus_areas_id,
                            analysis_package_focus_area_versions_id,
                            stakeholder_feedback_text,
                            created_at
                        )
                        VALUES
                        (
                            :hid,
                            :aphId,
                            :faId,
                            :favId,
                            :ftext,
                            UTC_TIMESTAMP()
                        )
                    ";
                    $stmtDet = $pdo->prepare($insDet);
                    $stmtDet->execute([
                        ':hid'   => $feedbackHeaderId,
                        ':aphId' => $analysisPackageId,
                        ':faId'  => $focusAreasId,
                        ':favId' => $focusAreaVersId,
                        ':ftext' => $feedbackText
                    ]);
                    $feedbackDetailsId = $pdo->lastInsertId();
                    $_SESSION['stakeholder_feedback_details_id'] = $feedbackDetailsId;
                } else {
                    // "Itemized"
                    // Possibly store general comments
                    if (!empty($feedbackText)) {
                        $insDet = "
                            INSERT INTO stakeholder_general_feedback
                            (
                                stakeholder_feedback_headers_id,
                                analysis_package_headers_id,
                                analysis_package_focus_areas_id,
                                analysis_package_focus_area_versions_id,
                                stakeholder_feedback_text,
                                created_at
                            )
                            VALUES
                            (
                                :hid,
                                :aphId,
                                :faId,
                                :favId,
                                :ftext,
                                UTC_TIMESTAMP()
                            )
                        ";
                        $stmtDet = $pdo->prepare($insDet);
                        $stmtDet->execute([
                            ':hid'   => $feedbackHeaderId,
                            ':aphId' => $analysisPackageId,
                            ':faId'  => $focusAreasId,
                            ':favId' => $focusAreaVersId,
                            ':ftext' => $feedbackText
                        ]);
                        $feedbackDetailsId = $pdo->lastInsertId();
                        $_SESSION['stakeholder_feedback_details_id'] = $feedbackDetailsId;
                    }
                    // Then itemized records
                    if (!empty($actions)) {
                        $sqlInsertRecord = "
                            INSERT INTO stakeholder_itemized_feedback
                            (
                                analysis_package_headers_id,
                                analysis_package_focus_areas_id,
                                analysis_package_focus_area_versions_id,
                                grid_index,
                                stakeholder_feedback_headers_id,
                                feedback_item_response,
                                stakeholder_feedback_text,
                                created_at
                            )
                            VALUES
                            (
                                :pkgId,
                                :faId,
                                :favId,
                                :gIdx,
                                :hdrId,
                                :fItemResp,
                                :fStakeTxt,
                                UTC_TIMESTAMP()
                            )
                        ";
                        $stmtRec = $pdo->prepare($sqlInsertRecord);
                        foreach ($actions as $gridIndex => $rowData) {
                            $actionVal = trim($rowData['action'] ?? '');
                            $stakeTxt  = $rowData['stakeholder_text'] ?? '';
                            if ($actionVal === '') {
                                continue;
                            }
                            $stmtRec->execute([
                                ':pkgId'    => $analysisPackageId,
                                ':faId'     => $focusAreasId,
                                ':favId'    => $focusAreaVersId,
                                ':gIdx'     => (int)$gridIndex,
                                ':hdrId'    => $feedbackHeaderId,
                                ':fItemResp'=> $actionVal,
                                ':fStakeTxt'=> $stakeTxt
                            ]);
                        }
                    }
                }

                $pdo->commit();

                // Possibly send a confirmation email
                $to      = $feedbackData['stakeholder_email'];
                $subject = "Feedback Submitted - {$feedbackData['focus_area_name']} in Package {$feedbackData['package_name']}";
                $message = "Thank you for submitting your feedback.\n\n"
                         . "Package: {$feedbackData['package_name']}\n"
                         . "Focus Area: {$feedbackData['focus_area_name']}\n"
                         . "Submitted: " . date('Y-m-d H:i:s') . " UTC\n\n"
                         . "Your feedback has been recorded.";
                @mail($to, $subject, $message);

                // Redirect to feedback-confirmation
                $_SESSION['stakeholder_email'] = $feedbackData['stakeholder_email'];
                header("Location: feedback-confirmation.php");
                exit();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                error_log('Error processing feedback: ' . $ex->getMessage());
                $error = 'An error occurred while processing your feedback.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Content Review</title>
    <link rel="stylesheet" type="text/css" href="/assets/css/stakeholder-content-review.css">
</head>
<body>
<div style="text-align: center; margin: 20px 0;">
  <?php if (!empty($orgWebsite)): ?>
    <a href="<?= htmlspecialchars($orgWebsite, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
      <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Organization Logo" style="max-height:88px;">
    </a>
  <?php else: ?>
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Organization Logo" style="max-height:88px;">
  <?php endif; ?>
</div>
<div class="container">
    <h1>Content Review</h1>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($feedbackSubmitted): ?>
      <div class="feedback-submitted">
        <p>Your feedback has already been submitted. Thank you!</p>
        <p style="margin-top:1em;">
          <a href="/stakeholder-feedback/feedback-confirmation.php" class="btn">
            View Pending Requests
          </a>
        </p>
      </div>
    <?php else: ?>
      <div class="summary">
        <p><strong>Stakeholder Email:</strong> <?= htmlspecialchars($feedbackData['stakeholder_email']) ?></p>
        <p><strong>Package ID:</strong> <?= htmlspecialchars($feedbackData['analysis_package_headers_id']) ?></p>
        <p><strong>Package Name:</strong> <?= htmlspecialchars($feedbackData['package_name']) ?></p>
        <p><strong>Package Summary:</strong> <?= htmlspecialchars($feedbackData['short_summary'] ?? '') ?></p>
        <p><strong>Focus Area:</strong> <?= htmlspecialchars($feedbackData['focus_area_name']) ?></p>
        <p><strong>Focus Area Version:</strong> <?= (int)$feedbackData['focus_area_version_num'] ?></p>
        <?php if (!empty($feedbackData['email_personal_message'])): ?>
          <p><strong>Personal Message:</strong> <?= htmlspecialchars($feedbackData['email_personal_message']) ?></p>
        <?php endif; ?>
      </div>

      <div class="instructions">
        <?php if ($formType === 'Itemized'): ?>
          <p>
            This is an <strong>Itemized</strong> feedback form for
            <strong><?= htmlspecialchars($feedbackData['focus_area_name']) ?></strong>.<br>
            Use the <strong><?= htmlspecialchars($primaryResponseOption ?: 'Primary') ?></strong> button
            <?php if ($secondaryResponseOption): ?>
              to provide text input, or
              <strong><?= htmlspecialchars($secondaryResponseOption) ?></strong>
              as desired.
            <?php else: ?>
              to provide text input.
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p>
            This is a <strong>General</strong> feedback form for
            <strong><?= htmlspecialchars($feedbackData['focus_area_name']) ?></strong>.
            Please enter your overall feedback below.
          </p>
        <?php endif; ?>
      </div>

      <form method="post" action="" onsubmit="return handleFormSubmit('<?= htmlspecialchars($formType) ?>');">
        <h2>Data Records</h2>
        <?php
        // 5) Retrieve relevant records
        $focusAreaVersId = (int)$feedbackData['analysis_package_focus_area_versions_id'];

        // If the Analyste specified a subset of grid indexes, we parse them:
        $allowedIndexes = [];
        $rawIndexesStr = trim($feedbackData['stakeholder_request_grid_indexes'] ?? '');
        if ($rawIndexesStr !== '') {
            $allowedIndexes = array_map('intval', explode(',', $rawIndexesStr));
        }

        $sqlRecs = "
            SELECT
               r.id AS record_id,
               r.grid_index,
               r.display_order,
               r.properties
            FROM analysis_package_focus_area_records r
            WHERE r.analysis_package_focus_area_versions_id = :favId
              AND r.is_deleted = 0
            ORDER BY r.display_order
        ";
        $stmtRecs = $pdo->prepare($sqlRecs);
        $stmtRecs->execute([':favId' => $focusAreaVersId]);
        $records = $stmtRecs->fetchAll(PDO::FETCH_ASSOC);

        // Filter the records if needed
        if (!empty($allowedIndexes)) {
            $records = array_filter($records, function($rec) use ($allowedIndexes) {
                return in_array((int)$rec['grid_index'], $allowedIndexes, true);
            });
        }
        $records = array_values($records);

        if ($records) {
            echo '<div class="data-object">';
            echo '<h2>' . htmlspecialchars($feedbackData['focus_area_name']) . '</h2>';
            foreach ($records as $rec) {
                $recId        = (int)$rec['record_id'];
                $gIdx         = (int)$rec['grid_index'];
                $displayOrder = (int)$rec['display_order'];
                $props        = @json_decode($rec['properties'], true);
                if (!is_array($props)) {
                    $props = [];
                }
                echo '<div class="data-record"
                           data-focus-area-records-id="' . htmlspecialchars($recId) . '"
                           data-grid-index="' . htmlspecialchars($gIdx) . '">';
                // Label each tile using display_order or fallback to the grid_index
                $labelNumber = ($displayOrder > 0) ? $displayOrder : $gIdx;
                echo '<h4>Record ' . $labelNumber . '</h4>';
                foreach ($props as $k => $v) {
                    $safeVal = htmlspecialchars($v);
                    echo '<p class="property"><span class="property-name">'
                         . htmlspecialchars($k) . ':</span> '
                         . $safeVal . '</p>';
                }
                if ($formType === 'Itemized') {
                    echo '<div class="action-buttons">';
                    if (!empty($primaryResponseOption)) {
                        echo '<button type="button" class="primary-btn btn">'
                           . htmlspecialchars($primaryResponseOption)
                           . '</button>';
                    }
                    if (!empty($secondaryResponseOption)) {
                        echo '<button type="button" class="secondary-btn btn">'
                           . htmlspecialchars($secondaryResponseOption)
                           . '</button>';
                    }
                    echo '</div>';
                    // Hidden fields for action + text
                    echo '<input type="hidden" name="actions[' . $gIdx . '][action]"'
                         . ' class="action-input" value="">';
                    echo '<input type="hidden" name="actions[' . $gIdx . '][stakeholder_text]"'
                         . ' class="stakeholder-text-input" value="">';
                }
                echo '</div>'; // .data-record
            }
            echo '</div>'; // .data-object
        } else {
            echo '<p>No records found for this focus area.</p>';
        }
        ?>

        <?php if ($formType === 'General'): ?>
          <br>
          <textarea name="feedback" class="general-feedback-text" placeholder="Enter your overall feedback here...">
<?= isset($_POST['feedback']) ? htmlspecialchars($_POST['feedback']) : '' ?></textarea>
        <?php else: ?>
          <p style="margin-top: 1em;">
            (Optional) Provide overall feedback:
          </p>
          <textarea name="feedback" class="general-feedback-text" placeholder="Enter any overall comment...">
<?= isset($_POST['feedback']) ? htmlspecialchars($_POST['feedback']) : '' ?></textarea>
        <?php endif; ?>
        <br>
        <input type="submit" class="btn submit-feedback-btn" value="Submit Feedback">
      </form>
    <?php endif; ?>
</div>

<?php if ($formType === 'Itemized'): ?>
  <div class="overlay" id="itemized-primary-overlay">
    <div class="overlay-content">
      <span class="close-overlay" id="close-primary-overlay">&times;</span>
      <h2 id="primary-overlay-title">Primary Response</h2>
      <textarea id="primary-overlay-text" placeholder="Enter details here..."></textarea>
      <br>
      <button type="button" class="btn" id="done-primary-btn">Done</button>
    </div>
  </div>
<?php endif; ?>

<div id="page-mask">
  <div id="progress-message">Processing your feedback...</div>
  <div class="spinner"></div>
</div>
<script>
    window.FormType = <?= json_encode($formType) ?>;
    window.PrimaryResponse = <?= json_encode($primaryResponseOption) ?>;
    window.SecondaryResponse = <?= json_encode($secondaryResponseOption) ?>;
</script>
<script src="/js/stakeholder-content-review.js"></script>
<script src="/js/feedback-confirmation.js"></script>
</body>
</html>
