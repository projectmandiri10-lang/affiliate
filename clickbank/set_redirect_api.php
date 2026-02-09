<?php
// JSON-only API endpoint for toggling redirect_enabled per pin (protected by COMPRESS_KEY).
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

ob_start();

$__json_response_sent = false;

function jsonResponse($payload, $statusCode = 200) {
    global $__json_response_sent;
    if ($__json_response_sent) {
        return;
    }
    $__json_response_sent = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Compress-Key');
    }

    $options = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $options |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $options);
    exit;
}

set_exception_handler(function ($e) {
    error_log('[clickbank/set_redirect_api.php] Uncaught exception: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }

    error_log('[clickbank/set_redirect_api.php] Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $err['message']], 500);
});

// CORS preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(204);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Compress-Key');
    }
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

require_once __DIR__ . '/config.php';

// Auth: prefer ADMIN_PASSWORD, fallback to COMPRESS_KEY for backwards compatibility.
$expectedAdmin = env('ADMIN_PASSWORD', '');
$expectedLegacy = env('COMPRESS_KEY', '');
if ($expectedAdmin === '' && $expectedLegacy === '') {
    jsonResponse(['success' => false, 'error' => 'Server misconfiguration: set ADMIN_PASSWORD (or COMPRESS_KEY) in .env'], 500);
}

$providedKey = '';
if (isset($_SERVER['HTTP_X_COMPRESS_KEY'])) {
    $providedKey = (string) $_SERVER['HTTP_X_COMPRESS_KEY'];
}

$ok = false;
if ($expectedAdmin !== '') {
    $ok = ($providedKey !== '' && (function_exists('hash_equals') ? hash_equals($expectedAdmin, $providedKey) : ($expectedAdmin === $providedKey)));
} elseif ($expectedLegacy !== '') {
    $ok = ($providedKey !== '' && (function_exists('hash_equals') ? hash_equals($expectedLegacy, $providedKey) : ($expectedLegacy === $providedKey)));
}

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
}

// Read input: allow either form-data or JSON body
$pinId = 0;
$enabledRaw = null;

if (isset($_POST['pin_id'])) {
    $pinId = (int) $_POST['pin_id'];
}
if (isset($_POST['redirect_enabled'])) {
    $enabledRaw = $_POST['redirect_enabled'];
}

if ($pinId <= 0 || $enabledRaw === null) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if ($pinId <= 0 && isset($decoded['pin_id'])) {
                $pinId = (int) $decoded['pin_id'];
            }
            if ($enabledRaw === null && array_key_exists('redirect_enabled', $decoded)) {
                $enabledRaw = $decoded['redirect_enabled'];
            }
        }
    }
}

if ($pinId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid pin_id'], 400);
}

$enabled = null;
if (is_bool($enabledRaw)) {
    $enabled = $enabledRaw ? 1 : 0;
} elseif (is_numeric($enabledRaw)) {
    $enabled = ((int) $enabledRaw) ? 1 : 0;
} elseif (is_string($enabledRaw)) {
    $v = strtolower(trim($enabledRaw));
    if ($v === '1' || $v === 'true' || $v === 'on' || $v === 'yes') {
        $enabled = 1;
    } elseif ($v === '0' || $v === 'false' || $v === 'off' || $v === 'no') {
        $enabled = 0;
    }
}

if ($enabled === null) {
    jsonResponse(['success' => false, 'error' => 'Invalid redirect_enabled (use 0/1)'], 400);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('UPDATE generated_pins SET redirect_enabled = ? WHERE id = ? LIMIT 1');
$stmt->execute([(int) $enabled, (int) $pinId]);
$updated = ($stmt->rowCount() > 0);

if (!$updated) {
    // Could be unchanged value or pin not found.
    $chk = $pdo->prepare('SELECT id, redirect_enabled FROM generated_pins WHERE id = ? LIMIT 1');
    $chk->execute([(int) $pinId]);
    $row = $chk->fetch();
    if (!$row) {
        jsonResponse(['success' => false, 'error' => 'Pin not found'], 404);
    }
    // unchanged, but still success
    $enabled = (int) ($row['redirect_enabled'] ?? $enabled);
}

jsonResponse([
    'success' => true,
    'pin_id' => (int) $pinId,
    'redirect_enabled' => (int) $enabled,
    'updated' => (bool) $updated,
]);

