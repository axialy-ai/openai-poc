<?php
// /serve_logo.php

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/auth.php'; // Contains validateSession()
session_start();

/*
   This script displays an uploaded logo file from your /custom_organizations/logo_path.
   If the user is an AxiaBA user with a valid session => allow.
   OR if the user is a stakeholder with a valid stakeholder_feedback session
   referencing the same custom organization => allow.
   Otherwise => 403.
*/

// 1) Check ?file= in the query
$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (!$file) {
    http_response_code(400);
    echo "No file specified.";
    exit;
}

// 2) Identify the matching custom organization row (which references logo_path)
$sqlOrg = "
    SELECT id, logo_path
    FROM custom_organizations
    WHERE logo_path = :f
    LIMIT 1
";
$stmtOrg = $pdo->prepare($sqlOrg);
$stmtOrg->execute([':f' => $file]);
$orgRow = $stmtOrg->fetch(PDO::FETCH_ASSOC);

if (!$orgRow) {
    // If no match, treat as 404
    http_response_code(404);
    echo "Logo file not found.";
    exit;
}

$orgId = $orgRow['id'];

// 3) Build the absolute path to this file
//    (Adjusted path to reach private_axiaba from app.axialy.ai)
require_once __DIR__ . '/../../private_axiaba/includes/Config.php'; 
use AxiaBA\Config\Config;

$config   = Config::getInstance();
$logosDir = rtrim($config['uploads']['logos_dir'] ?? '', '/') . '/';
$fullPath = $logosDir . $file;

// If the file does not exist on disk, 404
if (!is_file($fullPath)) {
    http_response_code(404);
    echo "File not found on disk.";
    exit;
}

/*
   4) Security check:
      - If user has a valid AxiaBA session => allow.
      - Else if stakeholder session => check if that session's package references orgId => allow.
      - Otherwise => 403.
*/

$validUserSession = validateSession(); // from auth.php
if ($validUserSession) {
    // Allowed
} else {
    // Maybe a stakeholder?
    if (!empty($_SESSION['stakeholder_feedback']['stakeholder_feedback_headers_id'])) {
        $feedbackHeaderId = (int)$_SESSION['stakeholder_feedback']['stakeholder_feedback_headers_id'];
        // Identify which custom org that package uses
        $sqlPkg = "
            SELECT aph.custom_organization_id
            FROM stakeholder_feedback_headers sfh
            INNER JOIN analysis_package_headers aph ON sfh.analysis_package_headers_id = aph.id
            WHERE sfh.id = :fhId
        ";
        $stmtPkg = $pdo->prepare($sqlPkg);
        $stmtPkg->execute([':fhId' => $feedbackHeaderId]);
        $pkgRow = $stmtPkg->fetch(PDO::FETCH_ASSOC);

        if (!$pkgRow) {
            http_response_code(403);
            echo "Access denied. Invalid stakeholder session.";
            exit;
        }
        $pkgOrgId = $pkgRow['custom_organization_id'];

        // If the stakeholder’s package org == $orgId => allow
        if ((int)$pkgOrgId === (int)$orgId) {
            // Allowed
        } else {
            http_response_code(403);
            echo "Access denied. Logo does not belong to this stakeholder package.";
            exit;
        }
    } else {
        // Not a logged-in user, nor a stakeholder with an active session => 403
        http_response_code(403);
        echo "Access denied. You must be logged in or have an active stakeholder session.";
        exit;
    }
}

// 5) If we got here, we can serve the file
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
switch ($extension) {
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    default:
        header('Content-Type: application/octet-stream');
        break;
}

// Optional: Add or remove caching headers as you wish
// header('Cache-Control: no-cache, no-store, must-revalidate');
// header('Pragma: no-cache');
// header('Expires: 0');

header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($fullPath));

// 6) Output the file
readfile($fullPath);
exit;
