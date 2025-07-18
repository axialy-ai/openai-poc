<?php
// //aii.axialy.ai/api/get_analysis_packages_with_metrics.php
//
// Retrieves analysis packages (analysis_package_headers) by a search term
// and returns multi-faceted metrics, including both General + Itemized feedback.

require_once '../includes/api_auth.php';
validateApiAccess();
header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/auth.php';
require_once '../includes/focus_org_session.php';

// --------------------------------------------------------------------------
// Check session + default org
// --------------------------------------------------------------------------
if (!validateSession() || !isset($_SESSION['default_organization_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// --------------------------------------------------------------------------
// Read query parameters
// --------------------------------------------------------------------------
$searchTerm   = isset($_GET['search'])      ? $_GET['search'] : '';
$showDeleted  = isset($_GET['showDeleted']) ? (int)$_GET['showDeleted'] : 0;
$defaultOrgId = $_SESSION['default_organization_id'];

try {
    // Retrieve user’s focus organization
    $focusOrg = getFocusOrganization($pdo, $_SESSION['user_id']);
    if (!$focusOrg) {
        throw new Exception("Focus organization could not be retrieved.");
    }

    // Clean up the search term: remove punctuation, collapse spaces
    $cleanSearchTerm = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', trim($searchTerm));
    $cleanSearchTerm = preg_replace('/\s+/', ' ', $cleanSearchTerm);
    $words = explode(' ', $cleanSearchTerm);

    // Build WHERE clauses
    $conditions = [];
    $params     = [];
    $idx = 0;
    foreach ($words as $word) {
        $word = trim($word);
        if ($word === '') {
            continue;
        }
        $paramName = ":srch{$idx}";
        // Match package_name or package ID
        $conditions[] = "(LOWER(aph.package_name) LIKE LOWER($paramName)
                          OR CAST(aph.id AS CHAR) LIKE $paramName)";
        $params[$paramName] = '%' . $word . '%';
        $idx++;
    }

    // If showDeleted=1 => no filter on is_deleted; else exclude is_deleted=1
    $deletedCondition = ($showDeleted === 1)
        ? ''
        : 'AND aph.is_deleted = 0';

    // Prepare main SELECT from analysis_package_headers
    $sql = "
        SELECT
            aph.id,
            aph.package_name,
            aph.short_summary,
            aph.long_description,
            aph.created_at,
            aph.custom_organization_id,
            co.logo_path,
            co.custom_organization_name AS custom_org_name,
            aph.is_deleted
        FROM analysis_package_headers aph
        LEFT JOIN custom_organizations co
               ON aph.custom_organization_id = co.id
        WHERE aph.default_organization_id = :defaultOrgId
          $deletedCondition
    ";
    $params[':defaultOrgId'] = $defaultOrgId;

    // Focus org filter if user’s focus org is custom
    if ($focusOrg !== 'default') {
        $sql .= " AND aph.custom_organization_id = :custOrg";
        $params[':custOrg'] = $focusOrg;
    }

    // Add search conditions
    if (!empty($conditions)) {
        $sql .= " AND " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY aph.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // ----------------------------------------------------------------------
    // Final result array
    // ----------------------------------------------------------------------
    $result = [];

    foreach ($packages as $pkg) {
        $packageId = (int)$pkg['id'];

        // 1) Count focus_areas_count (NOT deleted)
        $sqlFocusAreas = "
            SELECT COUNT(*)
              FROM analysis_package_focus_areas fa
             WHERE fa.analysis_package_headers_id = :pid
               AND fa.is_deleted = 0
        ";
        $stmtFa = $pdo->prepare($sqlFocusAreas);
        $stmtFa->execute([':pid' => $packageId]);
        $focusAreasCount = (int)$stmtFa->fetchColumn();

        // 2) total_records_count from *current* version only
        $sqlRecords = "
            SELECT COUNT(r.id)
              FROM analysis_package_focus_area_records r
              JOIN analysis_package_focus_area_versions v
                ON v.id = r.analysis_package_focus_area_versions_id
              JOIN analysis_package_focus_areas fa
                ON fa.id = v.analysis_package_focus_areas_id
             WHERE fa.analysis_package_headers_id = :pid
               AND fa.is_deleted = 0
               AND r.is_deleted = 0
               AND fa.current_analysis_package_focus_area_versions_id = v.id
        ";
        $stmtRec = $pdo->prepare($sqlRecords);
        $stmtRec->execute([':pid' => $packageId]);
        $totalRecordsCount = (int)$stmtRec->fetchColumn();

        // 3) data_objects_count => distinct focus_area_name in not-deleted focus areas
        $sqlDataObjs = "
            SELECT COUNT(DISTINCT fa.focus_area_name) AS data_objects_count
              FROM analysis_package_focus_areas fa
             WHERE fa.analysis_package_headers_id = :pid
               AND fa.is_deleted = 0
        ";
        $stmtObj = $pdo->prepare($sqlDataObjs);
        $stmtObj->execute([':pid' => $packageId]);
        $dataObjectsCount = (int)$stmtObj->fetchColumn();

        // 4) total_inputs_count => distinct r.input_text_summaries_id from current versions only
        $sqlInputs = "
            SELECT COUNT(DISTINCT r.input_text_summaries_id)
              FROM analysis_package_focus_area_records r
              JOIN analysis_package_focus_area_versions v
                ON v.id = r.analysis_package_focus_area_versions_id
              JOIN analysis_package_focus_areas fa
                ON fa.id = v.analysis_package_focus_areas_id
             WHERE fa.analysis_package_headers_id = :pid
               AND fa.is_deleted = 0
               AND r.is_deleted = 0
               AND r.input_text_summaries_id IS NOT NULL
               AND fa.current_analysis_package_focus_area_versions_id = v.id
        ";
        $stmtInp = $pdo->prepare($sqlInputs);
        $stmtInp->execute([':pid' => $packageId]);
        $totalInputsCount = (int)$stmtInp->fetchColumn();

        // 5) feedback_requests_count => all rows in stakeholder_feedback_headers
        $sqlRequests = "
            SELECT COUNT(*)
              FROM stakeholder_feedback_headers sfh
             WHERE sfh.analysis_package_headers_id = :pid
        ";
        $stmtReq = $pdo->prepare($sqlRequests);
        $stmtReq->execute([':pid' => $packageId]);
        $feedbackRequestsCount = (int)$stmtReq->fetchColumn();

        // 6) feedback_responses_count => count stakeholder_feedback_headers w/ responded_at IS NOT NULL
        $sqlRespFix = "
            SELECT COUNT(*)
              FROM stakeholder_feedback_headers sfh
             WHERE sfh.analysis_package_headers_id = :pid
               AND sfh.responded_at IS NOT NULL
        ";
        $stmtResp = $pdo->prepare($sqlRespFix);
        $stmtResp->execute([':pid' => $packageId]);
        $feedbackResponsesCount = (int)$stmtResp->fetchColumn();

        // 7) responding_stakeholders_count => distinct stakeholder_email from both general + itemized feedback
        //    *** Updated references to new table names: stakeholder_general_feedback & stakeholder_itemized_feedback
        $sqlStake = "
            SELECT COUNT(DISTINCT t.stakeholder_email) AS responding_stakeholders_count
              FROM (
                SELECT sfh.stakeholder_email
                  FROM stakeholder_general_feedback d
                  JOIN stakeholder_feedback_headers sfh
                    ON d.stakeholder_feedback_headers_id = sfh.id
                 WHERE sfh.analysis_package_headers_id = :pid

                UNION

                SELECT sfh2.stakeholder_email
                  FROM stakeholder_itemized_feedback r
                  JOIN stakeholder_feedback_headers sfh2
                    ON r.stakeholder_feedback_headers_id = sfh2.id
                 WHERE sfh2.analysis_package_headers_id = :pid
              ) t
        ";
        $stmtSt = $pdo->prepare($sqlStake);
        $stmtSt->execute([':pid' => $packageId]);
        $respondingStakeholdersCount = (int)$stmtSt->fetchColumn();

        // 8) unreviewed_feedback_count => sum of general + itemized feedback w/ resolved_at IS NULL, text <> ''
        //    *** Updated references to new table + renamed columns
        $sqlUnreviewed = "
            SELECT (
              SELECT COUNT(*)
                FROM stakeholder_general_feedback d
                JOIN stakeholder_feedback_headers h
                  ON d.stakeholder_feedback_headers_id = h.id
               WHERE h.analysis_package_headers_id = :pid
                 AND d.stakeholder_feedback_text <> ''
                 AND d.resolved_at IS NULL
            )
            +
            (
              SELECT COUNT(*)
                FROM stakeholder_itemized_feedback r
                JOIN stakeholder_feedback_headers h2
                  ON r.stakeholder_feedback_headers_id = h2.id
               WHERE h2.analysis_package_headers_id = :pid
                 AND r.stakeholder_feedback_text <> ''
                 AND r.resolved_at IS NULL
            ) AS unreviewed_feedback_count
        ";
        $stmtUF = $pdo->prepare($sqlUnreviewed);
        $stmtUF->execute([':pid' => $packageId]);
        $rowUF = $stmtUF->fetch(\PDO::FETCH_ASSOC);
        $unreviewedFeedbackCount = $rowUF ? (int)$rowUF['unreviewed_feedback_count'] : 0;

        // ------------------------------------------------------------------
        // Populate final package data object
        // ------------------------------------------------------------------
        $pkg['focus_areas_count']             = $focusAreasCount;
        $pkg['total_records_count']           = $totalRecordsCount;
        $pkg['data_objects_count']            = $dataObjectsCount;
        $pkg['total_inputs_count']            = $totalInputsCount;
        $pkg['feedback_requests_count']       = $feedbackRequestsCount;
        $pkg['feedback_responses_count']      = $feedbackResponsesCount;
        $pkg['responding_stakeholders_count'] = $respondingStakeholdersCount;
        $pkg['unreviewed_feedback_count']     = $unreviewedFeedbackCount;
        $pkg['is_deleted']                    = (int)$pkg['is_deleted'];

        $result[] = $pkg;
    }

    // ----------------------------------------------------------------------
    // Output final JSON
    // ----------------------------------------------------------------------
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $ex) {
    error_log("Error in get_analysis_packages_with_metrics: " . $ex->getMessage());
    http_response_code(500);
    echo json_encode([
        'error'   => 'Error fetching packages',
        'message' => $ex->getMessage()
    ]);
}
