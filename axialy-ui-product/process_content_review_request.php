<?php
/**
 * process_content_review_request.php
 *
 * Reads JSON from php://input (sent by content-review.js).
 * Creates rows in stakeholder_feedback_headers referencing:
 *   - analysis_package_headers_id
 *   - analysis_package_focus_areas_id
 *   - analysis_package_focus_area_versions_id
 * Also sets the new email_personal_message column (max 255 chars),
 * and stores stakeholder_request_grid_indexes so only those records
 * appear in the stakeholder’s form.
 */
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/debug_utils.php';
header('Content-Type: application/json; charset=UTF-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1) Parse JSON input
$rawInput  = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
if (!is_array($inputData)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid JSON input.'
    ]);
    exit;
}

// 2) Validate the content review request shape
$focusAreaName            = $inputData['focus_area_name'] ?? '';
$analysisPackageHeadersId = (int)($inputData['package_id'] ?? 0);
$focusAreaVersionId       = (int)($inputData['focus_area_version_id'] ?? 0);
$stakeholderEmails        = isset($inputData['stakeholders']) ? (array)$inputData['stakeholders'] : [];
$formType                 = $inputData['form_type'] ?? 'General';
$primaryResponseOption    = trim($inputData['primary_response_option'] ?? '');
$secondaryResponseOption  = trim($inputData['secondary_response_option'] ?? '');
$personalMessage          = trim($inputData['personal_message'] ?? '');
// New: The user’s chosen record indexes
$gridIndexesStr           = trim($inputData['stakeholder_request_grid_indexes'] ?? '');

// Basic validations
if ($analysisPackageHeadersId <= 0 || $focusAreaVersionId <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid package or focus area version ID.'
    ]);
    exit;
}
if (empty($stakeholderEmails)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'No stakeholders selected for review.'
    ]);
    exit;
}
if (strlen($personalMessage) > 1000) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Personal message exceeds 1000 characters.'
    ]);
    exit;
}
if (!in_array($formType, ['General','Itemized'], true)) {
    $formType = 'General';
}

// Clean personal message (strip HTML tags)
$personalMessage = strip_tags($personalMessage);
// The path for the stakeholder feedback form
$feedbackTarget = '/stakeholder-feedback/stakeholder-content-review.php';

$response = [
    'status'   => 'success',
    'messages' => [],
    'message'  => ''
];

try {
    // 3) Look up analysis_package_focus_areas_id from the version row
    $stmtVer = $pdo->prepare("
        SELECT analysis_package_focus_areas_id
          FROM analysis_package_focus_area_versions
         WHERE id = :favId
         LIMIT 1
    ");
    $stmtVer->execute([':favId' => $focusAreaVersionId]);
    $verRow = $stmtVer->fetch(\PDO::FETCH_ASSOC);
    if (!$verRow) {
        throw new Exception("No focus-area version found for ID = $focusAreaVersionId");
    }
    $focusAreasId = (int)$verRow['analysis_package_focus_areas_id'];

    // 4) For each stakeholder email, insert a row in stakeholder_feedback_headers
    foreach ($stakeholderEmails as $email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['messages'][] = "Invalid email format: {$email} (skipped).";
            $response['status']     = 'error';
            continue;
        }
        $token = bin2hex(random_bytes(16));
        $pin   = random_int(1000, 9999);

        $sql = "
            INSERT INTO stakeholder_feedback_headers
            (
                analysis_package_headers_id,
                analysis_package_focus_areas_id,
                analysis_package_focus_area_versions_id,
                stakeholder_email,
                email_personal_message,
                form_type,
                primary_response_option,
                secondary_response_option,
                stakeholder_request_grid_indexes,
                feedback_target,
                token,
                pin,
                created_at
            )
            VALUES
            (
                :pkgId,
                :faId,
                :favId,
                :email,
                :emailMsg,
                :formType,
                :primOpt,
                :secOpt,
                :gridIndexes,
                :target,
                :token,
                :pin,
                NOW()
            )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pkgId'       => $analysisPackageHeadersId,
            ':faId'        => $focusAreasId,
            ':favId'       => $focusAreaVersionId,
            ':email'       => $email,
            ':emailMsg'    => mb_substr($personalMessage, 0, 255),
            ':formType'    => $formType,
            ':primOpt'     => $primaryResponseOption,
            ':secOpt'      => $secondaryResponseOption,
            ':gridIndexes' => $gridIndexesStr, // store the comma-separated list
            ':target'      => $feedbackTarget,
            ':token'       => $token,
            ':pin'         => $pin
        ]);

        // 5) Basic email
        $subject = "Content Review Request for package #$analysisPackageHeadersId";
        $body    = "Hello {$email},\n\n";
        if ($personalMessage !== '') {
            $body .= "Personal Message:\n{$personalMessage}\n\n";
        }
        $body .= "You have been requested to review the focus area \"{$focusAreaName}\".\n";
        $body .= "Please click the link below to provide your feedback:\n";
        $protocol = $_SERVER['REQUEST_SCHEME'] ?? 'https';
        $host     = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
        $body    .= "{$protocol}://{$host}/receive_stakeholder_feedback.php?token={$token}\n\n";
        $body    .= "Your 4-digit PIN is: {$pin}\n\n";
        $body    .= "Thank you,\nAxiaBA Team\n";

        $headers  = "From: no-reply@axialy.ai\r\n";
        $headers .= "Reply-To: no-reply@axialy.ai\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if (mail($email, $subject, $body, $headers)) {
            $response['messages'][] = "Email sent to {$email}.";
        } else {
            $response['messages'][] = "Failed to send email to {$email}.";
            $response['status']     = 'error';
            $response['message']   .= "Failed to send email to {$email}. ";
            error_log("Mail function failed for email: {$email}");
        }
    }

} catch (Exception $e) {
    error_log('Error in process_content_review_request: ' . $e->getMessage());
    $response['status']  = 'error';
    $response['message'] = 'An error occurred processing the content review request.';
}

// Output final JSON
echo json_encode($response);
