<?php
/**
 * //aii.axialy.ai/fetch_analysis_package_focus_areas_with_metrics.php
 *
 * Variation that uses a dynamic IN (...) clause for version IDs
 * and also returns unreviewed_feedback_count per focus area.
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
requireAuth();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/db_connection.php';
$conn->set_charset("utf8mb4");

$packageId   = isset($_GET['package_id'])   ? (int)$_GET['package_id']   : 0;
$showDeleted = isset($_GET['show_deleted']) ? (int)$_GET['show_deleted'] : 0;

if ($packageId <= 0) {
    echo json_encode(['status'=>'error','message'=>'Missing or invalid package_id']);
    exit;
}

$response = [ "status" => "success", "focus_areas" => [] ];

try {
    // 1) Focus Areas + Their Current Version
    $condFA = $showDeleted ? "" : "AND fa.is_deleted = 0";
    $sqlFA = "
      SELECT
        fa.id AS focus_area_id,
        fa.focus_area_name,
        fa.is_deleted,
        fav.id AS version_id,
        fav.focus_area_version_number
      FROM analysis_package_focus_areas fa
      JOIN analysis_package_headers h
        ON h.id = fa.analysis_package_headers_id
      JOIN analysis_package_focus_area_versions fav
        ON fav.id = fa.current_analysis_package_focus_area_versions_id
      WHERE h.id = ?
        $condFA
      ORDER BY fa.id ASC
    ";
    $stmtFA = $conn->prepare($sqlFA);
    $stmtFA->bind_param('i', $packageId);
    $stmtFA->execute();
    $faResult = $stmtFA->get_result();

    $focusAreasData = [];
    $versionIds = [];

    while ($row = $faResult->fetch_assoc()) {
        $faId   = (int)$row['focus_area_id'];
        $vId    = (int)$row['version_id'];
        $vNum   = (int)$row['focus_area_version_number'];
        $focusAreasData[$faId] = [
            'focus_area_name' => $row['focus_area_name'],
            'fa_deleted'      => (int)$row['is_deleted'],
            'versionId'       => $vId,
            'versionNum'      => $vNum
        ];
        $versionIds[] = $vId;
    }
    $stmtFA->close();

    if (!$focusAreasData) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // 2) Fetch All Records for Those versionIds Using IN (...)
    //    (Dynamically build placeholders.)
    $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
    $condRec = $showDeleted ? "" : "AND r.is_deleted=0";

    // e.g.  "SELECT ... IN (?, ?, ?)"
    $sqlRecords = "
      SELECT
        r.id AS record_id,
        r.analysis_package_focus_area_versions_id,
        r.analysis_package_focus_areas_id,
        r.analysis_package_headers_id,
        r.grid_index,
        r.display_order,
        r.is_deleted,
        r.properties,
        r.input_text_summaries_id,
        its.input_text_title,
        its.input_text_summary,
        its.input_text
      FROM analysis_package_focus_area_records r
      LEFT JOIN input_text_summaries its
        ON its.id = r.input_text_summaries_id
      WHERE r.analysis_package_focus_area_versions_id IN ($placeholders)
        $condRec
      ORDER BY r.display_order ASC, r.id ASC
    ";

    // We'll do a variadic bind_param approach for clarity:
    $stmtRec = $conn->prepare($sqlRecords);
    // Build the param string, then pass the array of IDs
    $types = str_repeat('i', count($versionIds));
    // e.g. bind_param("iii", $versionIds[0], $versionIds[1], $versionIds[2])
    $stmtRec->bind_param($types, ...$versionIds);
    $stmtRec->execute();
    $resRec = $stmtRec->get_result();

    $recordsByVerId = [];
    while ($rowR = $resRec->fetch_assoc()) {
        $vId = (int)$rowR['analysis_package_focus_area_versions_id'];
        $p   = json_decode($rowR['properties'] ?? '{}', true);
        if (!is_array($p)) $p = [];
        $record = [
            'id' => (int)$rowR['record_id'],
            'analysis_package_focus_area_versions_id' => $vId,
            'analysis_package_focus_areas_id'         => (int)$rowR['analysis_package_focus_areas_id'],
            'analysis_package_headers_id'             => (int)$rowR['analysis_package_headers_id'],
            'grid_index'       => $rowR['grid_index'] !== null ? (int)$rowR['grid_index'] : null,
            'display_order'    => (int)$rowR['display_order'],
            'is_deleted'       => (int)$rowR['is_deleted'],
            'properties'       => $p,
            'input_text_summaries_id' => $rowR['input_text_summaries_id'] ?: null,
            'input_text_title'        => $rowR['input_text_title']   ?: '',
            'input_text_summary'      => $rowR['input_text_summary'] ?: '',
            'input_text'              => $rowR['input_text']         ?: ''
        ];
        $recordsByVerId[$vId][] = $record;
    }
    $stmtRec->close();

    // 3) unreviewed_feedback_count
    //    We'll do exactly the same logic your old code used
    $faUnreviewedCounts = [];
    foreach ($focusAreasData as $faId => $faInfo) {
        // general feedback
        $sqlGen = "
          SELECT COUNT(*) AS gf_count
          FROM stakeholder_general_feedback gf
          JOIN stakeholder_feedback_headers hh
            ON gf.stakeholder_feedback_headers_id = hh.id
          WHERE hh.analysis_package_headers_id = ?
            AND gf.analysis_package_focus_areas_id = ?
            AND gf.stakeholder_feedback_text <> ''
            AND gf.resolved_at IS NULL
        ";
        $stmtG = $conn->prepare($sqlGen);
        $stmtG->bind_param('ii', $packageId, $faId);
        $stmtG->execute();
        $rg = $stmtG->get_result();
        $countG = 0;
        if ($gRow = $rg->fetch_assoc()) $countG = (int)$gRow['gf_count'];
        $stmtG->close();

        // itemized feedback
        $sqlIt = "
          SELECT COUNT(*) AS ifb_count
          FROM stakeholder_itemized_feedback ifb
          JOIN stakeholder_feedback_headers hh2
            ON ifb.stakeholder_feedback_headers_id = hh2.id
          WHERE hh2.analysis_package_headers_id = ?
            AND ifb.analysis_package_focus_areas_id = ?
            AND ifb.stakeholder_feedback_text <> ''
            AND ifb.resolved_at IS NULL
        ";
        $stmtI = $conn->prepare($sqlIt);
        $stmtI->bind_param('ii', $packageId, $faId);
        $stmtI->execute();
        $ri = $stmtI->get_result();
        $countI = 0;
        if ($iRow = $ri->fetch_assoc()) $countI = (int)$iRow['ifb_count'];
        $stmtI->close();

        $faUnreviewedCounts[$faId] = $countG + $countI;
    }

    // 4) Final Output
    $focusAreasOutput = [];
    foreach ($focusAreasData as $faId => $faInfo) {
        $faName   = $faInfo['focus_area_name'];
        $verId    = $faInfo['versionId'];
        $verNum   = $faInfo['versionNum'];

        $theseRecords = $recordsByVerId[$verId] ?? [];
        $unrevCount   = $faUnreviewedCounts[$faId] ?? 0;

        $focusAreasOutput[$faName] = [
            "analysis_package_focus_areas_id" => $faId,
            "version"         => $verNum,
            "versionId"       => $verId,
            "records"         => $theseRecords,
            "unreviewed_feedback_count" => $unrevCount
        ];
    }

    $response['focus_areas'] = $focusAreasOutput;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;

} catch (\Exception $ex) {
    echo json_encode(['status'=>'error','message'=>'Exception: '.$ex->getMessage()]);
    exit;
}
