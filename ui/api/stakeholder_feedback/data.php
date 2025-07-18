<?php
// /api/stakeholder_feedback/data.php
header('Content-Type: application/json');
require_once '../../includes/db_connection.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/debug_utils.php';
validateApiAccess();

try {
    // Example query to fetch stakeholder feedback data
    $stmt = $pdo->prepare("
        SELECT 
            stakeholder_group, 
            COUNT(*) AS feedback_count
        FROM stakeholder_feedback_records sfr
        INNER JOIN stakeholder_feedback_headers sfh ON sfr.stakeholder_feedback_headers_id = sfh.id
        GROUP BY stakeholder_group
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $values = [];
    foreach ($results as $row) {
        $labels[] = $row['stakeholder_group'];
        $values[] = (int)$row['feedback_count'];
    }

    echo json_encode([
        'labels' => $labels,
        'values' => $values
    ]);
} catch (Exception $e) {
    debugLog("Error in /api/stakeholder_feedback/data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred while fetching stakeholder feedback data.']);
    exit;
}
?>
