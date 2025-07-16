<?php
/**
 * Generic PDO connector for the Axialy **UI** database
 * ----------------------------------------------------
 * • First choice – values injected by Docker/Ansible:
 *     UI_DB_HOST · UI_DB_PORT · UI_DB_NAME
 *     UI_DB_USER · UI_DB_PASSWORD
 * • Local-dev fallback (non-Docker) – read the first
 *   readable ".env" file one or two levels above repo root.
 *
 * After inclusion a PDO instance is available as **$pdo**.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ──────────────────────────────────────────────────────────
 * 1) Grab everything we can from environment variables
 * ────────────────────────────────────────────────────────── */
$host = getenv('UI_DB_HOST') ?: '';
$port = getenv('UI_DB_PORT') ?: '3306';
$db   = getenv('UI_DB_NAME') ?: 'Axialy_UI';
$user = getenv('UI_DB_USER') ?: '';
$pass = getenv('UI_DB_PASSWORD') ?: '';

/* ──────────────────────────────────────────────────────────
 * 2) Local-dev fallback – first readable .env
 *    (project root or one level higher)
 * ────────────────────────────────────────────────────────── */
if ($host === '' || $user === '' || $pass === '') {
    $candidates = [
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__, 3) . '/.env',
    ];
    
    foreach ($candidates as $file) {
        if (!is_readable($file)) {
            continue;
        }
        
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) {
                putenv("$k=$v");
            }
        }
        
        // reread after importing the file
        $host = getenv('UI_DB_HOST') ?: getenv('DB_HOST') ?: $host;
        $port = getenv('UI_DB_PORT') ?: getenv('DB_PORT') ?: $port;
        $db   = getenv('UI_DB_NAME') ?: getenv('DB_NAME') ?: $db;
        $user = getenv('UI_DB_USER') ?: getenv('DB_USER') ?: $user;
        $pass = getenv('UI_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: $pass;
        break;
    }
}

/* ──────────────────────────────────────────────────────────
 * 3) Sanity-check and create PDO
 * ────────────────────────────────────────────────────────── */
if ($host === '' || $user === '' || $pass === '') {
    throw new RuntimeException(
        'UI DB credentials are missing – make sure UI_DB_* variables are set '
      . 'or a readable .env file exists.'
    );
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
$pdo = new PDO(
    $dsn,
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
