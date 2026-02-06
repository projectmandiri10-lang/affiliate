<?php

// Database Configuration Template
// Recommended: use .env so secrets (DB password / Gemini key) are not stored in code.

/**
 * Minimal .env loader (no external dependency).
 * Reads KEY=VALUE lines and sets them into getenv()/$_ENV.
 */
function loadEnv($path) {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Strip quotes if present
        if ($val !== '') {
            $first = substr($val, 0, 1);
            $last = substr($val, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

function env($key, $default = '') {
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

loadEnv(__DIR__ . '/.env');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'your_database_name'));
define('DB_USER', env('DB_USER', 'your_username'));
define('DB_PASS', env('DB_PASS', 'your_password'));

// Gemini API Key (store in .env: GEMINI_API_KEY=xxxx)
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));

// Optional: configurable model name (Gemini 2.5 Flash)
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash'));

// Protect manual image compression API (compress_image_api.php)
// and delete pin API (delete_pin_api.php)
// Store in .env: COMPRESS_KEY=your-secret-key

function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        throw new \Exception("Database Connection Error: " . $e->getMessage());
    }
}
