<?php
// /store_analysis_package_header.php
//
// Creates a new row in analysis_package_headers for the new schema.
// The old "[redacted]" concept is removed.

include_once './includes/validation.php';
include_once __DIR__ . '/includes/db_connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Validate
$validationResult = validateAnalysisPackageHeader($data);
if (!$validationResult['is_valid']) {
    echo json_encode([
        'status' => 'error',
        'message' => $validationResult['message']
    ]);
    exit;
}

try {
    // Insert a row into analysis_package_headers, ignoring any legacy [redacted]
    $stmt = $pdo->prepare("
        INSERT INTO analysis_package_headers
        (
            package_name,
            short_summary,
            long_description,
            created_at
        )
        VALUES
        (
            :package_name,
            :short_summary,
            :long_description,
            NOW()
        )
    ");
    $stmt->execute([
        ':package_name'   => $data['Header Title'],
        ':short_summary'  => $data['Short Summary'],
        ':long_description' => $data['Long Description']
    ]);
    $analysisPackageHeadersId = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'analysis_package_headers_id' => $analysisPackageHeadersId
    ]);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred.'
    ]);
}
