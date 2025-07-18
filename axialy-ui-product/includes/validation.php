<?php
// /includes/validation.php

function validateInput($inputData) {
    if (isset($inputData['text']) && !empty(trim($inputData['text']))) {
        return ['is_valid' => true, 'data' => $inputData];
    }
    return ['is_valid' => false, 'message' => 'Input text is required.'];
}

function validateInputForSummary($data) {
    $required_fields = ['input_text_title', 'input_text_summary', 'input_text', 'api_utc'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return ['is_valid' => false, 'message' => "Field '$field' is required."];
        }
    }
    return ['is_valid' => true];
}

/**
 * Validates input for creating or updating an analysis package header.
 * The new schema no longer uses '[redacted]', so it's omitted here.
 */
function validateAnalysisPackageHeader($data) {
    // Only check for the required textual fields now:
    $required_fields = ['Header Title', 'Short Summary', 'Long Description'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            return ['is_valid' => false, 'message' => "Field '$field' is required."];
        }
    }
    return ['is_valid' => true];
}

/**
 * Validates input for a "Content Review Request" form.
 * Expects:
 *   - stakeholders: array of emails (1+)
 *   - focus_area_name: non-empty string
 *   - package_name: non-empty string
 *   - package_id: numeric
 * Optionally can include focus_area_version_id if needed by your schema.
 */
function validateContentReviewRequest($data) {
    if (!isset($data['stakeholders']) || !is_array($data['stakeholders']) || count($data['stakeholders']) === 0) {
        return ['is_valid' => false, 'message' => 'At least one stakeholder must be selected.'];
    }
    foreach ($data['stakeholders'] as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['is_valid' => false, 'message' => 'Invalid email address: ' . htmlspecialchars($email)];
        }
    }
    if (!isset($data['focus_area_name']) || empty(trim($data['focus_area_name']))) {
        return ['is_valid' => false, 'message' => 'Focus Area is required.'];
    }
    if (!isset($data['package_name']) || empty(trim($data['package_name']))) {
        return ['is_valid' => false, 'message' => 'Package Name is required.'];
    }
    if (!isset($data['package_id']) || !is_numeric($data['package_id'])) {
        return ['is_valid' => false, 'message' => 'Package ID is required and must be numeric.'];
    }
    // Optional check for 'focus_area_version_id'
    // if (!isset($data['focus_area_version_id']) || !is_numeric($data['focus_area_version_id'])) {
    //     return ['is_valid' => false, 'message' => 'Focus Area Version ID is required and must be numeric.'];
    // }
    return ['is_valid' => true];
}
