<?php
// /api/feedback_requests/organization.php

// Set response type to JSON
header('Content-Type: application/json');

// Include necessary files
require_once '../../includes/db_connection.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/debug_utils.php';

// Authenticate API access
validateApiAccess();

// Retrieve query parameters if any
$status = isset($_GET['status']) ? trim($_GET['status']) : null;

// Validate status parameter if provided
$validStatuses = ['Sent', 'Responded'];
if ($status && !in_array($status, $validStatuses)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid status parameter. Allowed values are Sent or Responded.']);
    exit;
}

try {
    if ($status) {
        // Fetch counts grouped by organization for a specific status
        $stmt = $pdo->prepare("
            SELECT 
                co.custom_organization_name AS organization,
                COUNT(*) AS count
            FROM stakeholder_feedback_headers sfh
            INNER JOIN analysis_package_headers aph ON sfh.analysis_package_headers_id = aph.id
            INNER JOIN custom_organizations co ON aph.custom_organization_id = co.id
            WHERE 
                (:status = 'Responded' AND sfh.responded_at IS NOT NULL) OR
                (:status = 'Sent' AND sfh.responded_at IS NULL)
            GROUP BY co.custom_organization_name
        ");
        $stmt->execute([':status' => $status]);
    } else {
        // Fetch counts grouped by organization for all statuses
        $stmt = $pdo->prepare("
            SELECT 
                co.custom_organization_name AS organization,
                COUNT(*) AS count
            FROM stakeholder_feedback_headers sfh
            INNER JOIN analysis_package_headers aph ON sfh.analysis_package_headers_id = aph.id
            INNER JOIN custom_organizations co ON aph.custom_organization_id = co.id
            GROUP BY co.custom_organization_name
        ");
        $stmt->execute();
    }

    $results = $stmt->fetchAll();

    // Prepare the response
    $labels = [];
    $values = [];

    foreach ($results as $row) {
        $labels[] = $row['organization'];
        $values[] = (int)$row['count'];
    }

    echo json_encode([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    debugLog("Error in /api/feedback_requests/organization.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred while fetching organization data.']);
    exit;
}
?>
