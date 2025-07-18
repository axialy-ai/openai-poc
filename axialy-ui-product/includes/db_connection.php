<?php
/**
 * Axialy UI – database connection helper.
 *
 * Reads the five UI_DB_* parameters from real env-vars; if any are
 * missing it looks for a .env file at the project root and imports them.
 * The connection is returned as a PDO instance.
 */

declare(strict_types=1);

/* ---------- 1. Collect configuration ----------------------- */

$required = [
    'UI_DB_HOST',
    'UI_DB_PORT',
    'UI_DB_NAME',
    'UI_DB_USER',
    'UI_DB_PASSWORD',
];

/**
 * If a .env file exists (written by the GitHub Actions deploy step) parse
 * it and inject any key=value pairs that are not already present in the
 * environment.  The parser is deliberately minimal but is sufficient for
 * the simple format produced by the pipeline.
 */
$rootDir = dirname(__DIR__);          // …/var/www/axialy-ui
$envFile = $rootDir . '/.env';

if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        // skip comments / empty lines
        if ($line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
    }
}

/* ---------- 2. Ensure everything we need is present -------- */

foreach ($required as $key) {
    if (($val = getenv($key)) === false || $val === '') {
        throw new RuntimeException(
            'UI DB credentials missing – set UI_DB_* env variables or provide a .env file.'
        );
    }
}

/* ---------- 3. Create a PDO connection -------------------- */

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('UI_DB_HOST'),
    getenv('UI_DB_PORT'),
    getenv('UI_DB_NAME')
);

try {
    $pdo = new PDO(
        $dsn,
        getenv('UI_DB_USER'),
        getenv('UI_DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
        ]
    );
} catch (PDOException $e) {
    // log internally but don’t leak details to the client
    error_log('[Axialy UI] DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal server error.');
}

return $pdo;
