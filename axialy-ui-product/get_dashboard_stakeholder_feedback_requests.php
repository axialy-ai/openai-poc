<?php
// /get_dashboard_stakeholder_feedback_requests.php
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request
debugLog("Raw request received in get_dashboard_stakeholder_feedback_requests.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'GET'            => $_GET,
    'POST'           => $_POST,
    'SESSION'        => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_dashboard_stakeholder_feedback_requests.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    debugLog("Authenticated user details", ['user_id' => $userId]);

    // 1) Get the user’s default org ID
    $stmtUser = $pdo->prepare("
        SELECT default_organization_id
        FROM ui_users
        WHERE id = :user_id
    ");
    $stmtUser->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        throw new Exception("User not found.");
    }
    $defaultOrgId = (int) $userRow['default_organization_id'];
    debugLog("User's default organization ID", [
        'default_organization_id' => $defaultOrgId
    ]);

    // 2) Gather filters
    $stakeholderEmail    = isset($_GET['stakeholder_email'])    ? trim($_GET['stakeholder_email'])    : 'all';
    $analysisPackageId   = isset($_GET['analysis_package_id'])  ? trim($_GET['analysis_package_id'])  : 'all';
    $focusAreaName       = isset($_GET['focus_area_name'])      ? trim($_GET['focus_area_name'])      : 'all';
    $customOrgParam      = isset($_GET['custom_organization_id']) ? trim($_GET['custom_organization_id']) : 'default';
    $responseReceived    = isset($_GET['response_received'])    ? trim($_GET['response_received'])    : 'all';

    // 3) Pagination
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $offset = ($page - 1) * $limit;

    // 4) Start building the main SQL
    $sql = "
        SELECT
            sfh.id,
            sfh.stakeholder_email,
            ap.id AS analysis_package_id,
            ap.package_name AS analysis_package,
            apf.focus_area_name AS focus_area,
            COALESCE(co.custom_organization_name, 'Default') AS focus_organization,
            sfh.created_at AS sent_date,
            CASE WHEN sfh.responded_at IS NULL THEN 'No' ELSE 'Yes' END AS response_received,
            sfh.responded_at AS response_date,

            -- Subselect: Approve
            (
                SELECT COUNT(*)
                FROM stakeholder_itemized_feedback sif
                WHERE sif.stakeholder_feedback_headers_id = sfh.id
                  AND sif.feedback_item_response = 'Approve'
            ) AS approve_count,

            -- Subselect: Revise
            (
                SELECT COUNT(*)
                FROM stakeholder_itemized_feedback sif
                WHERE sif.stakeholder_feedback_headers_id = sfh.id
                  AND sif.feedback_item_response = 'Revise'
            ) AS revise_count,

            -- Subselect: Skip
            (
                SELECT COUNT(*)
                FROM stakeholder_itemized_feedback sif
                WHERE sif.stakeholder_feedback_headers_id = sfh.id
                  AND sif.feedback_item_response = 'Skip'
            ) AS skip_count,

            -- Subselect: total
            (
                SELECT COUNT(*)
                FROM stakeholder_itemized_feedback sif
                WHERE sif.stakeholder_feedback_headers_id = sfh.id
            ) AS total_count

        FROM stakeholder_feedback_headers sfh
        JOIN analysis_package_headers ap
            ON sfh.analysis_package_headers_id = ap.id
        JOIN analysis_package_focus_areas apf
            ON sfh.analysis_package_focus_areas_id = apf.id
        LEFT JOIN custom_organizations co
            ON ap.custom_organization_id = co.id
        WHERE ap.default_organization_id = :default_org_id
    ";

    // Build an array of parameters
    $params = [':default_org_id' => $defaultOrgId];

    // 5) Handle custom org filter
    //    If custom_organization_id != 'default', filter by that integer
    if ($customOrgParam !== 'default') {
        $sql .= " AND ap.custom_organization_id = :focus_org_id";
        $params[':focus_org_id'] = (int)$customOrgParam;
    }

    // 6) Handle stakeholder_email filter
    if ($stakeholderEmail !== 'all') {
        $sql .= " AND sfh.stakeholder_email = :stakeholder_email";
        $params[':stakeholder_email'] = $stakeholderEmail;
    }

    // 7) Handle analysis_package_id filter
    if ($analysisPackageId !== 'all') {
        $sql .= " AND sfh.analysis_package_headers_id = :analysis_package_id";
        $params[':analysis_package_id'] = (int)$analysisPackageId;
    }

    // 8) Handle focus_area_name filter
    if ($focusAreaName !== 'all') {
        $sql .= " AND apf.focus_area_name = :focus_area_name";
        $params[':focus_area_name'] = $focusAreaName;
    }

    // 9) Handle response_received filter
    if ($responseReceived === 'yes') {
        $sql .= " AND sfh.responded_at IS NOT NULL";
    } elseif ($responseReceived === 'no') {
        $sql .= " AND sfh.responded_at IS NULL";
    }

    // 10) Add ORDER, LIMIT, OFFSET
    $sql .= "
        ORDER BY sfh.created_at DESC
        LIMIT :limit
        OFFSET :offset
    ";

    // 11) Prepare main statement
    $stmt = $pdo->prepare($sql);

    // Bind the known parameters
    foreach ($params as $k => $v) {
        // If it's obviously int, do PARAM_INT
        if (is_int($v)) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }

    // Bind the limit/offset as well
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Execute
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 12) Build a parallel COUNT(*) query for total_count
    $countSql = "
        SELECT COUNT(*) AS total_count
        FROM stakeholder_feedback_headers sfh
        JOIN analysis_package_headers ap
            ON sfh.analysis_package_headers_id = ap.id
        JOIN analysis_package_focus_areas apf
            ON sfh.analysis_package_focus_areas_id = apf.id
        LEFT JOIN custom_organizations co
            ON ap.custom_organization_id = co.id
        WHERE ap.default_organization_id = :default_org_id
    ";

    $countParams = [':default_org_id' => $defaultOrgId];

    if ($customOrgParam !== 'default') {
        $countSql .= " AND ap.custom_organization_id = :focus_org_id";
        $countParams[':focus_org_id'] = (int)$customOrgParam;
    }
    if ($stakeholderEmail !== 'all') {
        $countSql .= " AND sfh.stakeholder_email = :stakeholder_email";
        $countParams[':stakeholder_email'] = $stakeholderEmail;
    }
    if ($analysisPackageId !== 'all') {
        $countSql .= " AND sfh.analysis_package_headers_id = :analysis_package_id";
        $countParams[':analysis_package_id'] = (int)$analysisPackageId;
    }
    if ($focusAreaName !== 'all') {
        $countSql .= " AND apf.focus_area_name = :focus_area_name";
        $countParams[':focus_area_name'] = $focusAreaName;
    }
    if ($responseReceived === 'yes') {
        $countSql .= " AND sfh.responded_at IS NOT NULL";
    } elseif ($responseReceived === 'no') {
        $countSql .= " AND sfh.responded_at IS NULL";
    }

    // 13) Execute the count query
    $countStmt = $pdo->prepare($countSql);
    foreach ($countParams as $k => $v) {
        if (is_int($v)) {
            $countStmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $countStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }
    $countStmt->execute();
    $totalCount = (int) $countStmt->fetchColumn();

    // 14) Return JSON
    echo json_encode([
        'status'      => 'success',
        'data'        => $results,
        'total_count' => $totalCount
    ]);

} catch (PDOException $e) {
    debugLog("Database error in get_dashboard_stakeholder_feedback_requests.php", [
        'error_message' => $e->getMessage(),
        'error_code'    => $e->getCode(),
        'trace'         => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal Server Error. Please try again later.'
    ]);
} catch (Exception $e) {
    debugLog("General error in get_dashboard_stakeholder_feedback_requests.php", [
        'error_message' => $e->getMessage(),
        'trace'         => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal Server Error. Please try again later.'
    ]);
}

debugLog("Response complete in get_dashboard_stakeholder_feedback_requests.php");
?>
