<?php
// get-refine-activities.php

header('Content-Type: application/json');

// Path to refine-activities.json
$refineActivitiesJsonPath = __DIR__ . '/config/refine-activities.json';

// Function to load and decode a JSON file
function loadJsonConfig($path) {
    if (file_exists($path)) {
        $jsonContent = file_get_contents($path);
        $data = json_decode($jsonContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error parsing JSON: ' . json_last_error_msg()]);
            exit;
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'JSON file not found.']);
        exit;
    }
}

// Load refine activities
$refineData = loadJsonConfig($refineActivitiesJsonPath);
$activeRefineActivities = [];

// Filter active refine activities
if (isset($refineData['refineActivities']) && is_array($refineData['refineActivities'])) {
    foreach ($refineData['refineActivities'] as $activity) {
        if (isset($activity['active']) && $activity['active'] === true) {
            // Only include necessary fields
            $activeRefineActivities[] = [
                'label' => $activity['label'],
                'actionType' => $activity['actionType'],
                'action' => $activity['action']
            ];
        }
    }
}

echo json_encode(['refineActivities' => $activeRefineActivities]);
?>
