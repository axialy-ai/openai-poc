<?php
// /api/feedback_requests/status.php

// Set response type to JSON
header('Content-Type: application/json');

// Include necessary files
require_once '../../includes/db_connection.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/debug_utils.php';

// Authenticate API access
validateApiAccess();

// Retrieve query parameters if any
$organization = isset($_GET['organization']) ? trim($_GET['organization']) : null;

try {
    if ($organization) {
        // Fetch counts grouped by status for a specific organization
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN responded_at IS NOT NULL THEN 'Responded' 
                    ELSE 'Sent' 
                END AS status,
                COUNT(*) AS count
            FROM stakeholder_feedback_headers sfh
            INNER JOIN analysis_package_headers aph ON sfh.analysis_package_headers_id = aph.id
            INNER JOIN custom_organizations co ON aph.custom_organization_id = co.id
            WHERE co.custom_organization_name = :organization
            GROUP BY status
        ");
        $stmt->execute([':organization' => $organization]);
    } else {
        // Fetch counts grouped by status for all organizations
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN responded_at IS NOT NULL THEN 'Responded' 
                    ELSE 'Sent' 
                END AS status,
                COUNT(*) AS count
            FROM stakeholder_feedback_headers
            GROUP BY status
        ");
        $stmt->execute();
    }

    $results = $stmt->fetchAll();

    // Prepare the response
    $labels = [];
    $values = [];

    foreach ($results as $row) {
        $labels[] = $row['status'];
        $values[] = (int)$row['count'];
    }

    echo json_encode([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    debugLog("Error in /api/feedback_requests/status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred while fetching status data.']);
    exit;
}
?>
