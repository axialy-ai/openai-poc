<?php
// /fetch_stakeholder_feedback.php
//
// Aggregates both general (stakeholder_general_feedback) and itemized
// (stakeholder_itemized_feedback) unresolved feedback for a single
// focus area in a single package.
//
// Key fix: "Section D" no longer requires r.analysis_package_focus_areas_id = :faid3
// because your records for version=3 have analysis_package_focus_areas_id=NULL.
//
// So that we correctly retrieve rows 8,9,10,11,12 which have the correct version
// but a NULL analysis_package_focus_areas_id.

require_once __DIR__ . '/includes/db_connection.php';
header('Content-Type: application/json');

// 1) Parse GET
$pkgId  = isset($_GET['package_id'])      ? (int)$_GET['package_id']      : 0;
$faName = isset($_GET['focus_area_name']) ? trim($_GET['focus_area_name']) : '';

if ($pkgId <= 0 || $faName === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid parameters (package_id, focus_area_name).'
    ]);
    exit;
}

try {
    // -------------------------------------------------
    // A) Find the focus area => join to get version ID & version number
    // -------------------------------------------------
    $sqlFa = "
        SELECT
            fa.id AS focusAreaId,
            fa.current_analysis_package_focus_area_versions_id AS currentVerId,
            fav.focus_area_version_number
        FROM analysis_package_focus_areas fa
        JOIN analysis_package_focus_area_versions fav
          ON fav.id = fa.current_analysis_package_focus_area_versions_id
        WHERE fa.analysis_package_headers_id = :pkg
          AND fa.focus_area_name = :fan
          AND fa.is_deleted = 0
        LIMIT 1
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([':pkg' => $pkgId, ':fan' => $faName]);
    $faRow = $stmtFa->fetch(\PDO::FETCH_ASSOC);

    if (!$faRow) {
        // No matching focus area => return empty aggregator
        echo json_encode([
            'status'       => 'success',
            'summaryData'  => [],
            'records'      => [],
            'focusAreaRows'=> []
        ]);
        exit;
    }

    $focusAreaId         = (int)$faRow['focusAreaId'];
    $currentVerId        = (int)$faRow['currentVerId'];
    $focusAreaVersionNum = (int)$faRow['focus_area_version_number']; // user-facing version #

    // -------------------------------------------------
    // B) Unresolved GENERAL feedback (ignore version)
    // -------------------------------------------------
    $sqlGen = "
        SELECT
          sgd.id AS gfID,
          sgd.stakeholder_feedback_text,
          sgd.resolved_at,
          sfh.stakeholder_email
        FROM stakeholder_general_feedback sgd
        JOIN stakeholder_feedback_headers sfh
          ON sgd.stakeholder_feedback_headers_id = sfh.id
        WHERE sfh.analysis_package_headers_id     = :pkg
          AND sfh.analysis_package_focus_areas_id = :faid
          AND TRIM(sgd.stakeholder_feedback_text) != ''
          AND sgd.resolved_at IS NULL
    ";
    $stmtG = $pdo->prepare($sqlGen);
    $stmtG->execute([':pkg' => $pkgId, ':faid' => $focusAreaId]);

    $generalFeedback = [];
    $gfCount = 0;
    while ($rowG = $stmtG->fetch(\PDO::FETCH_ASSOC)) {
        $gfCount++;
        $generalFeedback[] = [
            'generalFeedbackRecordID' => (int)$rowG['gfID'],
            'responseNumber'          => $gfCount,
            'stakeholderEmail'        => $rowG['stakeholder_email'] ?? '',
            'properties' => [
                'Feedback'         => $rowG['stakeholder_feedback_text'],
                'StakeholderEmail' => $rowG['stakeholder_email']
            ],
            'feedbackCounts' => [
                'Reviewed'   => 0,
                'Unreviewed' => 1,
                'Pending'    => 0
            ]
        ];
    }

    // -------------------------------------------------
    // C) Unresolved ITEMIZED feedback (ignore version)
    //    left-join the current version’s record w/o requiring focus_areas_id
    // -------------------------------------------------
    $sqlItem = "
        SELECT
          sif.id AS ifID,
          sif.stakeholder_feedback_text,
          sif.resolved_at,
          sfh.stakeholder_email,
          sif.grid_index,

          apfar.id            AS chosen_apfar_id,
          apfar.display_order AS chosen_display_order

        FROM stakeholder_itemized_feedback sif
        JOIN stakeholder_feedback_headers sfh
          ON sfh.id = sif.stakeholder_feedback_headers_id

        LEFT JOIN analysis_package_focus_area_records apfar
          ON apfar.analysis_package_headers_id             = sfh.analysis_package_headers_id
         AND apfar.analysis_package_focus_area_versions_id = :curVer
         AND apfar.grid_index                              = sif.grid_index
         AND apfar.is_deleted = 0

        WHERE sfh.analysis_package_headers_id     = :pkg2
          AND sif.analysis_package_focus_areas_id = :faid2
          AND TRIM(sif.stakeholder_feedback_text) != ''
          AND sif.resolved_at IS NULL
    ";
    $stmtI = $pdo->prepare($sqlItem);
    $stmtI->execute([
        ':pkg2'   => $pkgId,
        ':faid2'  => $focusAreaId,
        ':curVer' => $currentVerId
    ]);

    $recordsMap = [];
    while ($rowI = $stmtI->fetch(\PDO::FETCH_ASSOC)) {
        $gIndex    = (int)$rowI['grid_index'];
        $apfarId   = (int)($rowI['chosen_apfar_id'] ?? 0);
        $dispOrder = (int)($rowI['chosen_display_order'] ?? 0);
        $itemID    = (int)$rowI['ifID'];

        $groupKey  = $gIndex . '-' . $apfarId;
        if (!isset($recordsMap[$groupKey])) {
            $recordsMap[$groupKey] = [
                'focusAreaRecordID'         => $apfarId,
                'itemizedFeedbackRecordIDs' => [],
                'recordNumber'              => ($dispOrder > 0 ? $dispOrder : 9999),
                'display_order'             => $dispOrder,
                'grid_index'                => $gIndex,
                'stakeholderEmail'          => '',
                'properties'                => [],
                'feedbackCounts' => [
                    'Reviewed'   => 0,
                    'Unreviewed' => 0,
                    'Pending'    => 0
                ]
            ];
        }

        $recordsMap[$groupKey]['itemizedFeedbackRecordIDs'][] = $itemID;
        $recordsMap[$groupKey]['feedbackCounts']['Unreviewed']++;

        // Merge stakeholder emails
        $existingEmail = $recordsMap[$groupKey]['stakeholderEmail'];
        $thisEmail     = $rowI['stakeholder_email'] ?? '';
        if ($thisEmail !== '' && strpos($existingEmail, $thisEmail) === false) {
            if ($existingEmail === '') {
                $recordsMap[$groupKey]['stakeholderEmail'] = $thisEmail;
            } else {
                $recordsMap[$groupKey]['stakeholderEmail'] .= ', ' . $thisEmail;
            }
        }
    }

    $itemizedRecords = [];
    $totalItemized   = 0;
    foreach ($recordsMap as $recObj) {
        $idsArr = $recObj['itemizedFeedbackRecordIDs'];
        $recObj['itemizedFeedbackRecordIDs'] = implode(',', $idsArr);

        $unrevCount = $recObj['feedbackCounts']['Unreviewed'] ?? 0;
        $totalItemized += $unrevCount;

        $itemizedRecords[] = $recObj;
    }

    // -------------------------------------------------
    // D) Retrieve the current version’s focus-area records
    //    No longer require analysis_package_focus_areas_id = :faid3
    // -------------------------------------------------
    $sqlRows = "
        SELECT
          r.id AS farID,
          r.grid_index,
          r.display_order,
          r.is_deleted,
          r.properties
        FROM analysis_package_focus_area_records r
        WHERE r.analysis_package_headers_id = :pkg3
          AND r.analysis_package_focus_area_versions_id = :curVer
          AND r.is_deleted = 0
        ORDER BY r.display_order ASC, r.grid_index ASC
    ";
    $stmtFR = $pdo->prepare($sqlRows);
    $stmtFR->execute([
        ':pkg3'  => $pkgId,
        ':curVer'=> $currentVerId
    ]);
    $focusRows = $stmtFR->fetchAll(\PDO::FETCH_ASSOC);

    $decodedFocusRows = [];
    foreach ($focusRows as $fr) {
        $props = @json_decode($fr['properties'] ?? '{}', true);
        if (!is_array($props)) {
            $props = [];
        }
        $decodedFocusRows[] = [
            'focusAreaRecordID' => (string)$fr['farID'],
            'grid_index'        => (string)$fr['grid_index'],
            'display_order'     => (string)$fr['display_order'],
            'is_deleted'        => (string)$fr['is_deleted'],
            'properties'        => $props
        ];
    }

    // Summaries
    $gfCount     = count($generalFeedback);
    $totalItems  = $gfCount + $totalItemized;

    $summaryData = [
        'packageId'             => $pkgId,
        'focusAreaName'         => $faName,
        'focusAreaVersionId'    => $currentVerId,
        'focusAreaVersionNumber'=> $focusAreaVersionNum,
        'totalFeedbackItems'    => $totalItems,
        'itemizedItemCount'     => count($itemizedRecords),
        'generalCount'          => $gfCount,
        'generalFeedback'       => $generalFeedback,
        'itemizedRecords'       => $itemizedRecords
    ];

    echo json_encode([
        'status'       => 'success',
        'summaryData'  => $summaryData,
        'records'      => [],
        'focusAreaRows'=> $decodedFocusRows
    ]);

} catch (\Exception $ex) {
    error_log('[fetch_stakeholder_feedback] Exception => ' . $ex->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to load stakeholder feedback.'
    ]);
}
