<?php
// /fetch_feedback_details.php
//
// Returns stakeholder feedback data for either:
//   - General feedback => ?general_feedback_record_id=XX  (stakeholder_general_feedback)
//   - Itemized feedback => ?itemized_feedback_record_id=XX (stakeholder_itemized_feedback)
// Also handles comma-separated itemized record IDs => ?itemized_feedback_record_id=2,3,4
//
// All version logic removed; we only return the unresolved stakeholder_feedback_text.

header('Content-Type: application/json');
require_once 'includes/db_connection.php';

// Read new param names
$generalFeedbackRecordID  = isset($_GET['general_feedback_record_id'])
  ? trim($_GET['general_feedback_record_id'])
  : '';
$itemizedFeedbackRecordID = isset($_GET['itemized_feedback_record_id'])
  ? trim($_GET['itemized_feedback_record_id'])
  : '';

if ($generalFeedbackRecordID === '' && $itemizedFeedbackRecordID === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing parameter: general_feedback_record_id or itemized_feedback_record_id.'
    ]);
    exit;
}

try {
    $feedbackResponses = [];

    // ------------------------------------------------------------------
    // 1) General feedback path => stakeholder_general_feedback
    // ------------------------------------------------------------------
    if ($generalFeedbackRecordID !== '') {
        $gfrId = (int)$generalFeedbackRecordID;
        if ($gfrId <= 0) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid general_feedback_record_id'
            ]);
            exit;
        }

        $sql = "
            SELECT
                sgd.id AS generalFeedbackRecordID,
                sgd.stakeholder_feedback_text,
                sgd.resolved_at,
                sfh.stakeholder_email
            FROM stakeholder_general_feedback sgd
            JOIN stakeholder_feedback_headers sfh
              ON sgd.stakeholder_feedback_headers_id = sfh.id
            WHERE sgd.id = :gfrId
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':gfrId' => $gfrId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $feedbackText = trim($row['stakeholder_feedback_text'] ?? '');
            if ($feedbackText !== '') {
                $feedbackResponses[] = [
                    'stakeholder_email' => $row['stakeholder_email'],
                    'stakeholder_text'  => $feedbackText
                ];
            }
        }
    }

    // ------------------------------------------------------------------
    // 2) Itemized feedback path => stakeholder_itemized_feedback
    // ------------------------------------------------------------------
    elseif ($itemizedFeedbackRecordID !== '') {
        // Could be comma-separated
        $idListRaw = explode(',', $itemizedFeedbackRecordID);  // e.g. ["2","3","4"]
        $validIds  = [];
        foreach ($idListRaw as $val) {
            $val = trim($val);
            if (ctype_digit($val)) {
                $validIds[] = (int)$val;
            }
        }

        if (empty($validIds)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid itemized_feedback_record_id parameter.'
            ]);
            exit;
        }

        // Simple WHERE id IN (...)
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $sql = "
            SELECT
                sif.id AS itemizedFeedbackRecordID,
                sif.stakeholder_feedback_text,
                sif.resolved_at,
                sfh.stakeholder_email
            FROM stakeholder_itemized_feedback sif
            JOIN stakeholder_feedback_headers sfh
              ON sfh.id = sif.stakeholder_feedback_headers_id
            WHERE sif.id IN ($placeholders)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($validIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $rr) {
            $stext = trim($rr['stakeholder_feedback_text'] ?? '');
            if ($stext === '') {
                continue;
            }
            $feedbackResponses[] = [
                'stakeholder_email' => $rr['stakeholder_email'],
                'stakeholder_text'  => $stext
            ];
        }
    }

    echo json_encode([
        'status'           => 'success',
        'feedbackResponses'=> $feedbackResponses
    ]);

} catch (\PDOException $ex) {
    error_log('Error loading feedback details: ' . $ex->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to retrieve feedback details.'
    ]);
}
