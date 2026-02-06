<?php
// JSON-only API endpoint for manual image compression (protected by COMPRESS_KEY).
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

ob_start();

$__json_response_sent = false;

function jsonResponse($payload, $statusCode = 200) {
    global $__json_response_sent;

    if ($__json_response_sent) return;
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
    error_log('[compress_image_api.php] Uncaught exception: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) return;

    error_log('[compress_image_api.php] Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
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
require_once __DIR__ . '/ImageOptimizer.php';

$expectedKey = env('COMPRESS_KEY', '');
if ($expectedKey === '') {
    jsonResponse(['success' => false, 'error' => 'Server misconfiguration: set COMPRESS_KEY in .env'], 500);
}

$providedKey = '';
if (isset($_SERVER['HTTP_X_COMPRESS_KEY'])) {
    $providedKey = (string) $_SERVER['HTTP_X_COMPRESS_KEY'];
}

if ($providedKey === '' || !(function_exists('hash_equals') ? hash_equals($expectedKey, $providedKey) : ($expectedKey === $providedKey))) {
    jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
}

// Read input: allow either form-data or JSON body
$pinId = 0;
if (isset($_POST['pin_id'])) {
    $pinId = (int) $_POST['pin_id'];
} else {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['pin_id'])) {
            $pinId = (int) $decoded['pin_id'];
        }
    }
}

if ($pinId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid pin_id'], 400);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT id, image_path FROM generated_pins WHERE id = ? LIMIT 1');
$stmt->execute([$pinId]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['success' => false, 'error' => 'Pin not found'], 404);
}

$imagePath = isset($row['image_path']) ? (string) $row['image_path'] : '';
if ($imagePath === '') {
    jsonResponse(['success' => false, 'error' => 'Pin has no image_path'], 404);
}

// Security: ensure the image is inside uploads/
$uploadsReal = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
if ($uploadsReal === false) {
    jsonResponse(['success' => false, 'error' => 'Server misconfiguration: uploads/ missing'], 500);
}

$candidate = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);
$imageReal = realpath($candidate);
if ($imageReal === false || !is_file($imageReal)) {
    jsonResponse(['success' => false, 'error' => 'Image file not found'], 404);
}

$uploadsPrefix = rtrim($uploadsReal, '\\/') . DIRECTORY_SEPARATOR;
$imageNorm = rtrim($imageReal, '\\/');
if (strpos($imageNorm . DIRECTORY_SEPARATOR, $uploadsPrefix) !== 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid image path'], 400);
}

$optimizer = new ImageOptimizer(['max_long_side' => 1600, 'jpg_quality' => 82, 'webp_quality' => 80, 'png_compression' => 6]);
$stats = $optimizer->optimizeInPlace($imageReal);

$cacheBust = @filemtime($imageReal);
if ($cacheBust === false) $cacheBust = time();

$beforeBytes = (int) ($stats['before_bytes'] ?? 0);
$afterBytes = (int) ($stats['after_bytes'] ?? 0);
$savedBytes = max(0, $beforeBytes - $afterBytes);
$savedPct = 0.0;
if ($beforeBytes > 0) {
    $savedPct = round(($savedBytes / $beforeBytes) * 100, 1);
}

jsonResponse([
    'success' => true,
    'pin_id' => (int) $pinId,
    'replaced' => (bool) ($stats['replaced'] ?? false),
    'reason' => (string) ($stats['reason'] ?? ''),
    'stats' => [
        'type' => (string) ($stats['type'] ?? ''),
        'before_bytes' => $beforeBytes,
        'after_bytes' => $afterBytes,
        'saved_bytes' => $savedBytes,
        'saved_percent' => $savedPct,
        'before_width' => (int) ($stats['before_width'] ?? 0),
        'before_height' => (int) ($stats['before_height'] ?? 0),
        'after_width' => (int) ($stats['after_width'] ?? 0),
        'after_height' => (int) ($stats['after_height'] ?? 0),
    ],
    'cache_bust' => (int) $cacheBust,
]);

