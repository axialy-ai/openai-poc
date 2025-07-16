<?php
/**
 *  Axialy Admin – UI-database connection
 *  --------------------------------------------------------------------------
 *  This helper builds a PDO connection to the Axialy_UI database that lives
 *  in the **selected environment** (production / beta / test / …).  
 *
 *  ► Primary mechanism – container/Ansible exports
 *        UI_DB_HOST, UI_DB_PORT, UI_DB_NAME, UI_DB_USER, UI_DB_PASSWORD
 *    (see infra/ansible roles)
 *
 *  ► Fallback for local-dev – read a ".env" file that sits in the project root
 *
 *  After this file is included, `$pdoUI` is available to callers.
 */
declare(strict_types=1);

// make sure we can access $_SESSION['admin_env']
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------------------------------------------------------
 *  (1) Figure out WHICH environment was chosen in Axialy Admin.
 * ------------------------------------------------------------------------*/
$env   = $_SESSION['admin_env'] ?? 'production';
$env   = preg_replace('/[^A-Za-z0-9_\-]/', '', $env);   // simple hardening

/* --------------------------------------------------------------------------
 *  (2) Get credentials from environment variables
 * ------------------------------------------------------------------------*/
$host = getenv('UI_DB_HOST') ?: '';
$port = getenv('UI_DB_PORT') ?: '3306';
$db   = getenv('UI_DB_NAME') ?: 'Axialy_UI';
$user = getenv('UI_DB_USER') ?: '';
$pass = getenv('UI_DB_PASSWORD') ?: '';

/* --------------------------------------------------------------------------
 *  (3) Local-dev fallback – read .env file from project root if env vars empty
 * ------------------------------------------------------------------------*/
if ($host === '' || $user === '' || $pass === '') {
    $candidates = [
        dirname(__DIR__, 2) . '/.env',     // project root
        dirname(__DIR__, 3) . '/.env',     // one level higher
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
        
        // re-load after putenv()
        $host = getenv('UI_DB_HOST') ?: getenv('DB_HOST') ?: $host;
        $port = getenv('UI_DB_PORT') ?: getenv('DB_PORT') ?: $port;
        $db   = getenv('UI_DB_NAME') ?: getenv('DB_NAME') ?: $db;
        $user = getenv('UI_DB_USER') ?: getenv('DB_USER') ?: $user;
        $pass = getenv('UI_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: $pass;
        break;
    }
}

/* --------------------------------------------------------------------------
 *  (4) Sanity-check & connect
 * ------------------------------------------------------------------------*/
if (!$host || !$user || !$pass) {
    throw new RuntimeException(
        'UI DB credentials are missing. Make sure UI_DB_* environment variables are set '
        .'or a readable .env file exists in the project root.'
    );
}

$dsn   = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
$pdoUI = new PDO(
    $dsn,
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
