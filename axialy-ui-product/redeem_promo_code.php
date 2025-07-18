<?php
// redeem_promo_code.php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth.php';

// Must have valid session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// JSON response helper
function jsonResponse($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'You must be logged in to redeem a promo code.');
}

// Get the JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['promo_code'])) {
    jsonResponse(false, 'No promo code provided.');
}

$promoCode       = trim($input['promo_code']);
$acceptStatement = !empty($input['acceptStatement']); // bool

try {
    // 1) Look up the code in promo_codes
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? LIMIT 1");
    $stmt->execute([$promoCode]);
    $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeRow) {
        jsonResponse(false, 'Invalid promo code.');
    }

    // Check date validity
    $now = new DateTime();
    if ($codeRow['start_date'] && $now < new DateTime($codeRow['start_date'])) {
        jsonResponse(false, 'This promo code cannot be used yet.');
    }
    if ($codeRow['end_date'] && $now > new DateTime($codeRow['end_date'])) {
        jsonResponse(false, 'This promo code is expired.');
    }

    // Check usage limit
    if (!is_null($codeRow['usage_limit']) && $codeRow['usage_count'] >= $codeRow['usage_limit']) {
        jsonResponse(false, 'This promo code has reached its maximum usage limit.');
    }

    // If statement_required, ensure user has accepted
    if ($codeRow['statement_required']) {
        if (!$acceptStatement) {
            /*
             * Instead of returning "I have read the statement: ...",
             * we return ONLY the statement text from $codeRow['statement'].
             */
            jsonResponse(false, 
                'You must accept the promo code statement before applying.', 
                [
                    'statementRequired' => true,
                    'statementLabel'    => $codeRow['statement'] ?? ''  // raw statement only
                ]
            );
        }
    }

    // Check if this user has already redeemed this code (if you only allow single-use per user)
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM promo_code_redemptions
        WHERE promo_code_id = :pcId
          AND user_id = :uId
    ");
    $stmtCheck->execute([
        ':pcId' => $codeRow['id'], 
        ':uId'  => $_SESSION['user_id']
    ]);
    $alreadyUsed = $stmtCheck->fetchColumn();
    if ($alreadyUsed) {
        jsonResponse(false, 'You have already redeemed this promo code.');
    }

    // 2) Determine new subscription settings
    $userId       = $_SESSION['user_id'];
    $codeType     = $codeRow['code_type'];
    $subscriptionActive = 1;
    $subscriptionId     = null;   // bypass Stripe
    $subscriptionPlan   = null;   // or 'promo_limited'
    $trialEndDate       = null;   // if unlimited

    if ($codeType === 'unlimited') {
        // unlimited internal user account
        $subscriptionPlan = null; // or 'promo_unlimited'
    } else {
        // limited internal user account
        $days = (int)$codeRow['limited_days'];
        if ($days < 1) {
            // fallback, 7 days if none specified
            $days = 7;
        }
        $subscriptionPlan = 'promo_limited';
        $dateNow = new DateTime();
        $dateNow->modify("+{$days} days");
        $trialEndDate = $dateNow->format('Y-m-d H:i:s');
    }

    // 3) Update the user’s subscription fields
    $stmtUpdate = $pdo->prepare("
        UPDATE ui_users
        SET 
            subscription_active      = :active,
            subscription_id         = :subId,
            subscription_plan_type  = :planType,
            trial_end_date          = :trialEnd
        WHERE id = :uid
        LIMIT 1
    ");
    $stmtUpdate->execute([
        ':active'   => $subscriptionActive,
        ':subId'    => $subscriptionId,
        ':planType' => $subscriptionPlan,
        ':trialEnd' => $trialEndDate,
        ':uid'      => $userId
    ]);

    // 4) Insert into promo_code_redemptions
    $stmtRedeem = $pdo->prepare("
        INSERT INTO promo_code_redemptions (promo_code_id, user_id, redeemed_at)
        VALUES (:pcId, :uId, NOW())
    ");
    $stmtRedeem->execute([
        ':pcId' => $codeRow['id'],
        ':uId'  => $userId
    ]);

    // 5) Increment usage_count in promo_codes
    $stmtIncrement = $pdo->prepare("
        UPDATE promo_codes
        SET usage_count = usage_count + 1
        WHERE id = ?
    ");
    $stmtIncrement->execute([$codeRow['id']]);

    // Success
    jsonResponse(true, 'Promo code redeemed successfully!');
} catch (Exception $ex) {
    error_log('Promo code redemption error: ' . $ex->getMessage());
    jsonResponse(false, 'An unexpected error occurred. Please try again.');
}
