<?php
// /save_analysis_package.php
//
// Creates or updates an analysis package header, then (for new focus areas)
// inserts the initial version and any corresponding records.
//
// Also inserts (or updates) a row in axialy_outputs if present in the request.
// That row is then linked to analysis_package_headers.axialy_outputs_id.

include_once __DIR__ . '/includes/validation.php';
include_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/focus_org_session.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// Validate session
// --------------------------------------------------------------------------
if (!validateSession() || !isset($_SESSION['default_organization_id'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid session or missing organization ID'
    ]);
    exit;
}

// --------------------------------------------------------------------------
// Parse incoming JSON body
// --------------------------------------------------------------------------
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['headerData']) || !is_array($data['headerData'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid header data provided.'
    ]);
    exit;
}

$headerData        = $data['headerData'];
$collectedData     = isset($data['collectedData']) ? $data['collectedData'] : [];
$userSelectedOrgId = null;

// ADDED for axialy_outputs:
$axialyOutputs     = isset($data['axialyOutputs']) ? $data['axialyOutputs'] : null;
$scenarioTitle     = $axialyOutputs['scenarioTitle']  ?? '';
$outputDocument    = $axialyOutputs['outputDocument'] ?? '';

// Organization logic
if (!empty($headerData['organization_id']) && $headerData['organization_id'] !== 'default') {
    $tmpOrg = (int)$headerData['organization_id'];
    if ($tmpOrg > 0) {
        $userSelectedOrgId = $tmpOrg;
    }
}

try {
    $pdo->beginTransaction();

    $defaultOrgId = $_SESSION['default_organization_id'];
    $focusOrg     = getFocusOrganization($pdo, $_SESSION['user_id']);

    // If the user has set a custom organization as focus, use that unless the user explicitly
    // specified a different one in headerData['organization_id']
    $finalCustomOrgId = $userSelectedOrgId
        ? $userSelectedOrgId
        : (($focusOrg !== 'default') ? $focusOrg : null);

    /**
     * createFocusAreasFromCollected:
     * Inserts one or more new focus_areas from an array of items all sharing
     * the same focus_area_label. Each item can either:
     *   (a) Have arrays named focusAreaRecords / stakeholderRecords (the old style), OR
     *   (b) Have a simple structure with properties + grid_index (the new Generate tab style).
     */
    function createFocusAreasFromCollected(
        PDO $pdo,
        int $analysisPackageId,
        string $focusAreaName,
        array $faItems
    ) {
        foreach ($faItems as $faItem) {
            // Let the function handle both “old-style” and “new-style” item shapes
            $focusAreaValue        = $faItem['focus_area_value']       ?? '';
            $collaborationApproach = $faItem['collaboration_approach'] ?? '';

            // Old style: arrays named focusAreaRecords / stakeholderRecords
            // will be inserted. Otherwise, we build them from single-‘properties’ items.
            $focusAreaRecords     = isset($faItem['focusAreaRecords'])
                                  ? $faItem['focusAreaRecords']
                                  : [];
            $stakeholderRecords   = isset($faItem['stakeholderRecords'])
                                  ? $faItem['stakeholderRecords']
                                  : [];

            // -- NEW: If no sub-arrays are present, but we do have a single object with “properties”,
            //          then we create a one-record array so that it is not ignored.
            $hasAnyRecords = (!empty($focusAreaRecords) || !empty($stakeholderRecords));

            if (!$hasAnyRecords && isset($faItem['properties']) && is_array($faItem['properties'])) {
                // Decide if it goes into “stakeholderRecords” or “focusAreaRecords”
                // If the label is “Analysis Package Stakeholders,” we treat them as stakeholderRecords,
                // otherwise as normal focusAreaRecords
                if (trim($focusAreaName) === 'Analysis Package Stakeholders') {
                    $stakeholderRecords[] = [
                        'input_text_summaries_id' => $faItem['input_text_summaries_id'] ?? null,
                        'grid_index'             => $faItem['grid_index']             ?? 0,
                        'properties'             => $faItem['properties']
                    ];
                } else {
                    $focusAreaRecords[] = [
                        'input_text_summaries_id' => $faItem['input_text_summaries_id'] ?? null,
                        'grid_index'             => $faItem['grid_index']             ?? 0,
                        'properties'             => $faItem['properties']
                    ];
                }
            }

            // Now combine them if “Analysis Package Stakeholders”
            if (trim($focusAreaName) === 'Analysis Package Stakeholders') {
                $allRecords = array_merge($focusAreaRecords, $stakeholderRecords);
            } else {
                $allRecords = $focusAreaRecords; // skip stakeholderRecords for normal areas
            }

            // If there really are no records, we still create the focus area
            // but it will have zero rows in analysis_package_focus_area_records.
            // That can be useful for a label-only “container”.
            // ----------------------------------------------------------------

            // 1) Insert a new row in analysis_package_focus_areas
            $stmtFA = $pdo->prepare("
                INSERT INTO analysis_package_focus_areas
                    (analysis_package_headers_id,
                     focus_area_name,
                     focus_area_value,
                     collaboration_approach,
                     is_deleted,
                     created_at,
                     updated_at)
                VALUES
                    (:pkgid, :faname, :faval, :collab, 0, NOW(), NOW())
            ");
            $stmtFA->execute([
                ':pkgid'  => $analysisPackageId,
                ':faname' => $focusAreaName,
                ':faval'  => $focusAreaValue,
                ':collab' => $collaborationApproach,
            ]);
            $focusAreaId = (int)$pdo->lastInsertId();

            // 2) Create the initial version
            $stmtFAV = $pdo->prepare("
                INSERT INTO analysis_package_focus_area_versions
                    (analysis_package_headers_id,
                     analysis_package_focus_areas_id,
                     focus_area_version_number,
                     focus_area_revision_summary,
                     created_at)
                VALUES
                    (:pkgid, :faid, 0, 'Initial version from collectedData', NOW())
            ");
            $stmtFAV->execute([
                ':pkgid' => $analysisPackageId,
                ':faid'  => $focusAreaId
            ]);
            $favId = (int)$pdo->lastInsertId();

            // 3) Mark this version as the current version
            $stmtUpdFa = $pdo->prepare("
                UPDATE analysis_package_focus_areas
                   SET current_analysis_package_focus_area_versions_id = :fav
                 WHERE id = :faid
            ");
            $stmtUpdFa->execute([
                ':fav'  => $favId,
                ':faid' => $focusAreaId
            ]);

            // 4) Insert the focus_area_records
            $stmtRec = $pdo->prepare("
                INSERT INTO analysis_package_focus_area_records
                    (
                      analysis_package_headers_id,
                      analysis_package_focus_areas_id,
                      analysis_package_focus_area_versions_id,
                      grid_index,
                      display_order,
                      is_deleted,
                      input_text_summaries_id,
                      properties,
                      created_at
                    )
                VALUES
                    (
                      :pkgId,
                      :faId,
                      :favId,
                      :gdx,
                      :dispOrd,
                      0,
                      :itsId,
                      :props,
                      NOW()
                    )
            ");

            $gridIndex = 0;
            foreach ($allRecords as $recordObj) {
                $itsId = isset($recordObj['input_text_summaries_id'])
                         ? (int)$recordObj['input_text_summaries_id']
                         : null;
                // JSON-encode the record's properties
                $props = (isset($recordObj['properties']) && is_array($recordObj['properties']))
                         ? json_encode($recordObj['properties'])
                         : '{}';

                // If the incoming record has its own grid_index, use it
                // (otherwise fallback to the loop’s auto-increment)
                $actualGridIndex = isset($recordObj['grid_index'])
                                   ? (int)$recordObj['grid_index']
                                   : $gridIndex;

                $displayOrd = $gridIndex + 1;

                $stmtRec->execute([
                    ':pkgId'   => $analysisPackageId,
                    ':faId'    => $focusAreaId,
                    ':favId'   => $favId,
                    ':gdx'     => $actualGridIndex,
                    ':dispOrd' => $displayOrd,
                    ':itsId'   => $itsId,
                    ':props'   => $props
                ]);
                $gridIndex++;
            }
        }
    }

    // ----------------------------------------------------------------------
    // Group collectedData by focus_area_label
    // ----------------------------------------------------------------------
    $groupedFocusAreas = [];
    foreach ($collectedData as $cd) {
        $faLabel = isset($cd['focus_area_label']) ? trim($cd['focus_area_label']) : '';
        if ($faLabel === '') {
            $faLabel = 'Unnamed Focus Area';
        }
        if (!isset($groupedFocusAreas[$faLabel])) {
            $groupedFocusAreas[$faLabel] = [];
        }
        $groupedFocusAreas[$faLabel][] = $cd;
    }

    // Are we updating an existing package or creating a new one?
    $existingPackageId = !empty($data['existing_analysis_package_id'])
                         ? (int)$data['existing_analysis_package_id']
                         : 0;

    // ----------------------------------------------------------------------
    // If we are UPDATING an existing package
    // ----------------------------------------------------------------------
    if ($existingPackageId > 0) {
        $stmtUpd = $pdo->prepare("
            UPDATE analysis_package_headers
               SET package_name           = :title,
                   short_summary          = :shortSum,
                   long_description       = :longDesc,
                   custom_organization_id = :custOrg,
                   updated_at             = NOW()
             WHERE id = :pkgId
               AND default_organization_id = :defOrg
        ");
        $stmtUpd->execute([
            ':title'    => $headerData['Header Title'],
            ':shortSum' => $headerData['Short Summary'] ?? null,
            ':longDesc' => $headerData['Long Description'] ?? null,
            ':custOrg'  => $finalCustomOrgId,
            ':pkgId'    => $existingPackageId,
            ':defOrg'   => $defaultOrgId
        ]);

        // Insert new FocusAreas & records from collectedData
        foreach ($groupedFocusAreas as $faLabel => $faItems) {
            createFocusAreasFromCollected($pdo, $existingPackageId, $faLabel, $faItems);
        }

        // If we have axialy_outputs data, insert a new row or link it
        if (!empty($scenarioTitle) || !empty($outputDocument)) {
            $itsId = isset($data['input_text_summaries_id'])
                        ? (int)$data['input_text_summaries_id']
                        : 0;

            $stmtAxOut = $pdo->prepare("
                INSERT INTO axialy_outputs
                    (input_text_summaries_id,
                     analysis_package_headers_id,
                     axialy_scenario_title,
                     axialy_output_document,
                     created_at)
                VALUES
                    (:itsId, :aphId, :scen, :doc, NOW())
            ");
            $stmtAxOut->execute([
                ':itsId' => $itsId,
                ':aphId' => $existingPackageId,
                ':scen'  => $scenarioTitle,
                ':doc'   => $outputDocument
            ]);
            $axOutId = (int)$pdo->lastInsertId();

            $stmtUpdPk = $pdo->prepare("
                UPDATE analysis_package_headers
                   SET axialy_outputs_id = :aoid
                 WHERE id = :pkgId
            ");
            $stmtUpdPk->execute([
                ':aoid'  => $axOutId,
                ':pkgId' => $existingPackageId
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'status'                      => 'success',
            'analysis_package_headers_id' => $existingPackageId,
            'package_name'                => $headerData['Header Title']
        ]);
        exit;
    }

    // ----------------------------------------------------------------------
    // Otherwise, CREATE a brand-new analysis_package_headers row
    // ----------------------------------------------------------------------
    else {
        $stmtIns = $pdo->prepare("
            INSERT INTO analysis_package_headers
                (
                  package_name,
                  short_summary,
                  long_description,
                  default_organization_id,
                  custom_organization_id,
                  created_at,
                  updated_at,
                  is_deleted
                )
            VALUES
                (
                  :title,
                  :shortSum,
                  :longDesc,
                  :defOrg,
                  :custOrg,
                  NOW(),
                  NOW(),
                  0
                )
        ");
        $stmtIns->execute([
            ':title'   => $headerData['Header Title'],
            ':shortSum'=> $headerData['Short Summary'] ?? null,
            ':longDesc'=> $headerData['Long Description'] ?? null,
            ':defOrg'  => $defaultOrgId,
            ':custOrg' => $finalCustomOrgId
        ]);

        $analysisPackageId = (int)$pdo->lastInsertId();

        // Insert new focus areas & records
        foreach ($groupedFocusAreas as $faLabel => $faItems) {
            createFocusAreasFromCollected($pdo, $analysisPackageId, $faLabel, $faItems);
        }

        // If there's axialy_outputs data, save it and link it
        if (!empty($scenarioTitle) || !empty($outputDocument)) {
            $itsId = isset($data['input_text_summaries_id'])
                        ? (int)$data['input_text_summaries_id']
                        : 0;

            $stmtAxOut = $pdo->prepare("
                INSERT INTO axialy_outputs
                    (
                      input_text_summaries_id,
                      analysis_package_headers_id,
                      axialy_scenario_title,
                      axialy_output_document,
                      created_at
                    )
                VALUES
                    (
                      :itsId,
                      :aphId,
                      :scen,
                      :doc,
                      NOW()
                    )
            ");
            $stmtAxOut->execute([
                ':itsId' => $itsId,
                ':aphId' => $analysisPackageId,
                ':scen'  => $scenarioTitle,
                ':doc'   => $outputDocument
            ]);
            $axOutId = (int)$pdo->lastInsertId();

            $stmtUpdPk = $pdo->prepare("
                UPDATE analysis_package_headers
                   SET axialy_outputs_id = :aoid
                 WHERE id = :pkgId
            ");
            $stmtUpdPk->execute([
                ':aoid'  => $axOutId,
                ':pkgId' => $analysisPackageId
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'status'                      => 'success',
            'analysis_package_headers_id' => $analysisPackageId,
            'package_name'                => $headerData['Header Title']
        ]);
        exit;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in save_analysis_package.php: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database Error in save_analysis_package.php: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error occurred.'
    ]);
}
