<?php
// /get_stakeholder_feedback_requests.php

require_once 'includes/db_connection.php';
require_once 'includes/auth.php';
require_once 'includes/debug_utils.php';

// Enable or disable error reporting in production as needed:
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

// Log the raw request for debugging
debugLog("Raw request received in get_stakeholder_feedback_requests.php", [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'GET' => $_GET,
    'POST' => $_POST,
    'SESSION' => $_SESSION
]);

// Verify authentication
if (!validateSession()) {
    debugLog("Authentication failed in get_stakeholder_feedback_requests.php");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    debugLog("Authenticated user details in get_stakeholder_feedback_requests.php", [
        'user_id' => $userId
    ]);

    // Fetch user's default organization_id
    $stmt = $pdo->prepare("
        SELECT default_organization_id
        FROM ui_users
        WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    $userDefaultOrgId = $user['default_organization_id'];
    debugLog("User's default organization ID in get_stakeholder_feedback_requests.php", [
        'default_organization_id' => $userDefaultOrgId
    ]);

    // Retrieve filters from GET parameters
    $stakeholderEmail       = isset($_GET['stakeholder_email'])      ? trim($_GET['stakeholder_email'])      : 'all';
    $analysisPackageId      = isset($_GET['analysis_package_id'])    ? trim($_GET['analysis_package_id'])    : 'all';
    $focusAreaName          = isset($_GET['focus_area_name'])        ? trim($_GET['focus_area_name'])        : 'all';
    $focusOrganizationId    = isset($_GET['custom_organization_id']) ? trim($_GET['custom_organization_id']) : 'default';
    $responseReceived       = isset($_GET['response_received'])      ? trim($_GET['response_received'])      : 'all';

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 per page
    $page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $offset = ($page - 1) * $limit;

    // Base SQL
    $sql = "
        SELECT 
            sfh.id,
            sfh.stakeholder_email,
            ap.id AS analysis_package_id,
            ap.package_name AS analysis_package,
            sfh.focus_area_name AS focus_area,
            COALESCE(co.custom_organization_name, 'Default') AS focus_organization,
            sfh.created_at AS sent_date,
            CASE
                WHEN sfh.responded_at IS NULL THEN 'No'
                ELSE 'Yes'
            END AS response_received,
            sfh.responded_at AS response_date,
            (
                SELECT COUNT(*)
                FROM stakeholder_feedback_records sfr2
                WHERE sfr2.stakeholder_feedback_headers_id = sfh.id
                  AND sfr2.action = 'Approve'
            ) AS approve_count,
            (
                SELECT COUNT(*)
                FROM stakeholder_feedback_records sfr2
                WHERE sfr2.stakeholder_feedback_headers_id = sfh.id
                  AND sfr2.action = 'Revise'
            ) AS revise_count,
            (
                SELECT COUNT(*)
                FROM stakeholder_feedback_records sfr2
                WHERE sfr2.stakeholder_feedback_headers_id = sfh.id
                  AND sfr2.action = 'Skip'
            ) AS skip_count,
            (
                SELECT COUNT(*)
                FROM stakeholder_feedback_records sfr2
                WHERE sfr2.stakeholder_feedback_headers_id = sfh.id
            ) AS total_count
        FROM stakeholder_feedback_headers sfh
        JOIN analysis_package_headers ap ON sfh.analysis_package_headers_id = ap.id
        LEFT JOIN custom_organizations co ON ap.custom_organization_id = co.id
        WHERE ap.default_organization_id = :default_org_id
    ";

    $params = [
        ':default_org_id' => $userDefaultOrgId
    ];

    // Focus Organization filter
    if ($focusOrganizationId && $focusOrganizationId !== 'default') {
        $sql .= " AND ap.custom_organization_id = :focus_org_id";
        $params[':focus_org_id'] = $focusOrganizationId;
    }

    // Stakeholder Email filter
    if (!empty($stakeholderEmail) && $stakeholderEmail !== 'all') {
        $sql .= " AND sfh.stakeholder_email = :stakeholder_email";
        $params[':stakeholder_email'] = $stakeholderEmail;
    }

    // Analysis Package filter
    if (!empty($analysisPackageId) && $analysisPackageId !== 'all') {
        $sql .= " AND sfh.analysis_package_headers_id = :analysis_package_id";
        $params[':analysis_package_id'] = $analysisPackageId;
    }

    // Focus Area filter
    if (!empty($focusAreaName) && $focusAreaName !== 'all') {
        $sql .= " AND sfh.focus_area_name = :focus_area_name";
        $params[':focus_area_name'] = $focusAreaName;
    }

    // Response Received filter
    if ($responseReceived === 'yes') {
        $sql .= " AND sfh.responded_at IS NOT NULL";
    } elseif ($responseReceived === 'no') {
        $sql .= " AND sfh.responded_at IS NULL";
    }

    // Sort by creation date descending
    $sql .= " ORDER BY sfh.created_at DESC";

    // Pagination
    $sql .= " LIMIT :limit OFFSET :offset";
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    // Prepare and bind
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => &$value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("Feedback requests fetched successfully in get_stakeholder_feedback_requests.php", [
        'count' => count($results)
    ]);

    // Get total count for pagination
    $countSql = "
        SELECT COUNT(*)
        FROM stakeholder_feedback_headers sfh
        JOIN analysis_package_headers ap ON sfh.analysis_package_headers_id = ap.id
        LEFT JOIN custom_organizations co ON ap.custom_organization_id = co.id
        WHERE ap.default_organization_id = :default_org_id
    ";
    $countParams = [':default_org_id' => $userDefaultOrgId];

    if ($focusOrganizationId && $focusOrganizationId !== 'default') {
        $countSql .= " AND ap.custom_organization_id = :focus_org_id";
        $countParams[':focus_org_id'] = $focusOrganizationId;
    }
    if (!empty($stakeholderEmail) && $stakeholderEmail !== 'all') {
        $countSql .= " AND sfh.stakeholder_email = :stakeholder_email";
        $countParams[':stakeholder_email'] = $stakeholderEmail;
    }
    if (!empty($analysisPackageId) && $analysisPackageId !== 'all') {
        $countSql .= " AND sfh.analysis_package_headers_id = :analysis_package_id";
        $countParams[':analysis_package_id'] = $analysisPackageId;
    }
    if (!empty($focusAreaName) && $focusAreaName !== 'all') {
        $countSql .= " AND sfh.focus_area_name = :focus_area_name";
        $countParams[':focus_area_name'] = $focusAreaName;
    }
    if ($responseReceived === 'yes') {
        $countSql .= " AND sfh.responded_at IS NOT NULL";
    } elseif ($responseReceived === 'no') {
        $countSql .= " AND sfh.responded_at IS NULL";
    }

    $countStmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => &$value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

    // Return JSON
    echo json_encode([
        'status' => 'success',
        'data' => $results,
        'total_count' => (int)$totalCount
    ]);
} catch (PDOException $e) {
    debugLog("Database error in get_stakeholder_feedback_requests.php", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? 'unknown',
        'driver_error_code' => $e->errorInfo[1] ?? 'unknown',
        'driver_error_message' => $e->errorInfo[2] ?? 'unknown',
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error. Please try again later.'
    ]);
} catch (Exception $e) {
    debugLog("General error in get_stakeholder_feedback_requests.php", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error. Please try again later.'
    ]);
}

// Log completion
debugLog("Response complete in get_stakeholder_feedback_requests.php");
