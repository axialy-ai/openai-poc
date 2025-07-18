<?php
/**
 * fetch_active_packages_for_save.php
 *
 * Returns a JSON list of all active (non-deleted) analysis packages.
 * Used so the user can pick an existing package to update.
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// Make sure we output JSON
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/db_connection.php';

// Only show packages that are not is_deleted=1
$sql = "
  SELECT
    id AS package_id,
    package_name,
    short_summary,
    updated_at
  FROM analysis_package_headers
  WHERE is_deleted = 0
  ORDER BY updated_at DESC
";
try {
    $stmt = $pdo->query($sql);
    $packages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $packages[] = [
            'package_id'    => (int)$row['package_id'],
            'package_name'  => $row['package_name'],
            'short_summary' => $row['short_summary'] ?? '',
            'updated_at'    => $row['updated_at']
        ];
    }
    echo json_encode([
        'status'   => 'success',
        'packages' => $packages
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error: ' . $ex->getMessage()
    ]);
}
