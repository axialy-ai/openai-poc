<?php
/**
 * //aii.axialy.ai/fetch_analysis_package_focus_area_records.php
 *
 * Returns JSON of focus-area records for a given package, including:
 *   - The real row ID from analysis_package_focus_area_versions as "versionId"
 *   - Optional: the focus area’s own ID in each record if needed
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// Make sure our JSON output is labeled UTF-8
header('Content-Type: application/json; charset=UTF-8');

// DB connection
require_once __DIR__ . '/includes/db_connection.php';

// IMPORTANT: ensure MySQL uses full utf8mb4
$conn->set_charset("utf8mb4");

$packageId   = isset($_GET['package_id'])   ? (int)$_GET['package_id']   : 0;
$showDeleted = isset($_GET['show_deleted']) ? (int)$_GET['show_deleted'] : 0;

// Optionally accept a focus_area_version_number if needed
$focusAreaVersionParam = isset($_GET['focus_area_version_number']) ? (int)$_GET['focus_area_version_number'] : null;

if ($packageId <= 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid or missing package_id.'
    ]);
    exit;
}

$response = [
    "status"      => "success",
    "focus_areas" => []
];

try {
    // -------------------------------------------------------------
    // 1) Fetch all focus areas for this package + their "current" version
    // -------------------------------------------------------------
    $deletedCondition = $showDeleted ? "" : "AND fa.is_deleted = 0";
    $sqlFocusAreas = "
       SELECT
           fa.id AS analysis_package_focus_areas_id,
           fa.focus_area_name,
           fa.is_deleted AS fa_deleted,
           fav.id AS current_ver_id,            -- The row ID for the version
           fav.focus_area_version_number
         FROM analysis_package_focus_areas fa
         JOIN analysis_package_headers h
           ON h.id = fa.analysis_package_headers_id
         JOIN analysis_package_focus_area_versions fav
           ON fav.id = fa.current_analysis_package_focus_area_versions_id
        WHERE h.id = ?
          $deletedCondition
        ORDER BY fa.id ASC
    ";

    $stmtFA = $conn->prepare($sqlFocusAreas);
    $stmtFA->bind_param('i', $packageId);
    $stmtFA->execute();
    $faResult = $stmtFA->get_result();

    $focusAreasData = [];
    $versionIds     = [];

    while ($rowFA = $faResult->fetch_assoc()) {
        $faId   = (int)$rowFA['analysis_package_focus_areas_id'];
        $verId  = (int)$rowFA['current_ver_id'];
        $verNum = (int)$rowFA['focus_area_version_number'];

        $focusAreasData[$faId] = [
            'focus_area_name' => $rowFA['focus_area_name'],
            'deleted'         => (int)$rowFA['fa_deleted'],
            'ver_id'          => $verId,    // the actual row ID
            'versionNum'      => $verNum,   // numeric version
        ];
        $versionIds[] = $verId;
    }
    $stmtFA->close();

    // If no focus areas, return
    if (!$focusAreasData) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // -------------------------------------------------------------
    // 2) If user explicitly specified focus_area_version_number,
    //    you could override logic here if needed.
    // -------------------------------------------------------------
    // (Omitted in this snippet; you can adapt as required.)

    // -------------------------------------------------------------
    // 3) Build placeholders for multi-IN query to fetch the actual records
    // -------------------------------------------------------------
    $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
    $deletedConditionRec = $showDeleted ? "" : "AND r.is_deleted = 0";
    $sqlRecords = "
        SELECT
            r.id AS record_id,
            r.analysis_package_focus_area_versions_id AS rec_version_id,
            r.analysis_package_focus_areas_id AS rec_fa_id,
            r.analysis_package_headers_id,
            r.grid_index,
            r.display_order,
            r.properties,
            r.is_deleted,
            r.input_text_summaries_id,
            its.input_text_title,
            its.input_text_summary,
            its.input_text
          FROM analysis_package_focus_area_records r
          LEFT JOIN input_text_summaries its
                 ON r.input_text_summaries_id = its.id
         WHERE r.analysis_package_focus_area_versions_id IN ($placeholders)
           $deletedConditionRec
         ORDER BY r.display_order ASC, r.id ASC
    ";

    // Bind the array of version IDs dynamically
    $bindTypes   = str_repeat('i', count($versionIds));
    $stmtRecords = $conn->prepare($sqlRecords);
    $stmtRecords->bind_param($bindTypes, ...$versionIds);
    $stmtRecords->execute();
    $recResult = $stmtRecords->get_result();

    // Organize records by version ID
    $recordsByVerId = [];
    while ($rowR = $recResult->fetch_assoc()) {
        $verId   = (int)$rowR['rec_version_id'];
        $props   = json_decode($rowR['properties'] ?? '{}', true);
        if (!is_array($props)) {
            $props = [];
        }

        $record = [
            'id'          => (int)$rowR['record_id'],
            'analysis_package_focus_area_versions_id' => $verId,
            'analysis_package_focus_areas_id'         => (int)$rowR['rec_fa_id'], // new
            'analysis_package_headers_id'             => (int)$rowR['analysis_package_headers_id'],
            'grid_index'   => isset($rowR['grid_index']) ? (int)$rowR['grid_index'] : null,
            'display_order'=> isset($rowR['display_order']) ? (int)$rowR['display_order'] : 1,
            'is_deleted'   => (int)$rowR['is_deleted'],
            'properties'   => $props,
            'input_text_summaries_id' => $rowR['input_text_summaries_id'] ?: null,
            'input_text_title'        => $rowR['input_text_title']   ?: '',
            'input_text_summary'      => $rowR['input_text_summary'] ?: '',
            'input_text'              => $rowR['input_text']         ?: ''
        ];

        $recordsByVerId[$verId][] = $record;
    }
    $stmtRecords->close();

    // -------------------------------------------------------------
    // 4) Build final structure => "focus_areas"
    // -------------------------------------------------------------
    $focusAreasOutput = [];
    foreach ($focusAreasData as $faId => $faInfo) {
        $faName = $faInfo['focus_area_name'];
        $verId  = $faInfo['ver_id'];
        $verNum = $faInfo['versionNum'];

        $faRecords = isset($recordsByVerId[$verId]) ? $recordsByVerId[$verId] : [];

        // The legacy code used $faName as the array key, so let's preserve that:
        $focusAreasOutput[$faName] = [
            "analysis_package_focus_areas_id" => $faId,  // new
            "version"                         => $verNum,
            "versionId"                       => $verId,  // actual row ID
            "records"                         => $faRecords
        ];
    }

    $response['focus_areas'] = $focusAreasOutput;

    // IMPORTANT: use JSON_UNESCAPED_UNICODE and JSON_INVALID_UTF8_SUBSTITUTE
    // so no error arises from special characters:
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;

} catch (Exception $ex) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Exception occurred: ' . $ex->getMessage()
    ]);
    exit;
}
