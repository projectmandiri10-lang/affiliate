<?php
// Ensure this endpoint always responds with JSON (even on fatal errors).
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

ob_start();

$__json_response_sent = false;
$__phase = 'boot';
$__debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

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
        header('Access-Control-Allow-Headers: Content-Type');
    }

    $options = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $options |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    echo json_encode($payload, $options);
    exit;
}

set_exception_handler(function ($e) {
    global $__phase, $__debug;

    error_log('[generate_pin_api.php] Uncaught exception: ' . $e->getMessage());

    $payload = [
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'phase' => $__phase
    ];

    if ($__debug) {
        $payload['type'] = is_object($e) ? get_class($e) : gettype($e);
        if (is_object($e) && method_exists($e, 'getFile')) {
            $payload['file'] = $e->getFile();
        }
        if (is_object($e) && method_exists($e, 'getLine')) {
            $payload['line'] = $e->getLine();
        }
        if (is_object($e) && method_exists($e, 'getTraceAsString')) {
            $payload['trace'] = $e->getTraceAsString();
        }
    }

    jsonResponse($payload, 500);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    global $__phase, $__debug;

    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }

    error_log('[generate_pin_api.php] Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);

    $payload = [
        'success' => false,
        'error' => 'Server error: ' . $err['message'],
        'phase' => $__phase
    ];

    if ($__debug) {
        $payload['type'] = 'fatal';
        $payload['file'] = $err['file'];
        $payload['line'] = $err['line'];
    }

    jsonResponse($payload, 500);
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
        header('Access-Control-Allow-Headers: Content-Type');
    }
    exit;
}

$configPath = __DIR__ . '/config.php';
$generatorPath = __DIR__ . '/PinterestGenerator.php';
$imageProcessorPath = __DIR__ . '/ImageProcessor.php';

if (!is_file($configPath) || !is_file($generatorPath) || !is_file($imageProcessorPath)) {
    jsonResponse(['success' => false, 'error' => 'Server misconfiguration: required PHP files are missing.'], 500);
}

$__phase = 'require_files';
require_once $configPath;
require_once $generatorPath;
require_once $imageProcessorPath;
$__phase = 'init';

function limitWords($text, $maxWords) {
    $text = (string) $text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    $maxWords = (int) $maxWords;
    if ($maxWords <= 0 || $text === '') {
        return $text;
    }
    $words = preg_split('/\s+/', $text);
    if (!is_array($words) || count($words) <= $maxWords) {
        return $text;
    }
    return trim(implode(' ', array_slice($words, 0, $maxWords)));
}

// Enable extra debug output when needed:
// - add ?debug=1 to the request URL, OR
// - set APP_DEBUG=1 in .env (after config.php loads .env)
$__debug = $__debug || (getenv('APP_DEBUG') === '1');

// Handle POST Request Only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

// Ambil input (Support Multipart/Form-Data)
$__phase = 'read_input';
$productName = $_POST['product_name'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? 'General';
$affiliateLink = $_POST['affiliate_link'] ?? '';
$originalProductUrl = $_POST['original_product_url'] ?? null;

$processedImagePath = null;

try {
    $__phase = 'capability_checks';
    // Basic server capability checks (helps diagnose 500 on hosting)
    if (!class_exists('PDO')) {
        throw new Exception('PDO is not available. Enable PHP PDO extension.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new Exception('pdo_mysql extension is not enabled. Enable PHP pdo_mysql.');
    }
    if (!function_exists('curl_init')) {
        throw new Exception('cURL is not available. Enable PHP cURL extension.');
    }
    if (!function_exists('imagecreatefromjpeg')) {
        throw new Exception('GD is not available. Enable PHP GD extension for image processing.');
    }

    $imageProcessor = new ImageProcessor();

    // 1. Handle Image Upload & Watermark
    $__phase = 'image_processing';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $processedImagePath = $imageProcessor->processImage($_FILES['product_image']);
    } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Provide clear message if upload is rejected by PHP limits
        $code = (int) $_FILES['product_image']['error'];
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception('Upload failed: file size exceeds the PHP server limit. Increase upload_max_filesize and post_max_size to at least 10M.');
        }
        throw new Exception('Upload failed. Error code: ' . $code);
    }

    // Validasi input (Manual Only Now)
    if (empty($productName) || empty($description)) {
        jsonResponse(['success' => false, 'error' => 'Product name and description are required.'], 400);
    }

    $affiliateLink = trim((string) $affiliateLink);
    if ($affiliateLink === '') {
        jsonResponse(['success' => false, 'error' => 'ClickBank affiliate (HopLink) URL is required.'], 400);
    }
    if (filter_var($affiliateLink, FILTER_VALIDATE_URL) === false) {
        jsonResponse(['success' => false, 'error' => 'Affiliate URL must be a valid URL (include https://).'], 400);
    }

    if ($originalProductUrl !== null) {
        $originalProductUrl = trim((string) $originalProductUrl);
        if ($originalProductUrl === '') {
            $originalProductUrl = null;
        } elseif (filter_var($originalProductUrl, FILTER_VALIDATE_URL) === false) {
            jsonResponse(['success' => false, 'error' => 'Offer page URL must be a valid URL (include https://).'], 400);
        }
    }

    $__phase = 'db_connect';
    $pdo = getDbConnection();

    // 3. Generate Content (Gemini)
    if (!GEMINI_API_KEY) {
        throw new Exception("API Key missing in config");
    }

    // Check DB cache
    $__phase = 'db_cache_lookup';
    $stmt = $pdo->prepare("SELECT * FROM generated_pins WHERE product_name = ? LIMIT 1");
    $stmt->execute([$productName]);
    $existingPin = $stmt->fetch();
    
    $result = null;

    if ($existingPin) {
        // Reuse content text
        $__phase = 'db_cache_hit';
        $result = [
            'pinterest_title' => $existingPin['pinterest_title'],
            'pinterest_description' => limitWords($existingPin['pinterest_description'], 15),
            'keywords' => json_decode($existingPin['keywords'], true),
            'recommended_boards' => json_decode($existingPin['recommended_boards'], true),
            'content_strategy' => $existingPin['strategy']
        ];
    } else {
        // Generate new content
        // If description is still empty, use product name as base
        $__phase = 'gemini_generate';
        $descForAI = !empty($description) ? $description : "Product: " . $productName;
        
        $model = (defined('GEMINI_MODEL') && GEMINI_MODEL) ? GEMINI_MODEL : 'gemini-2.5-flash';
        $generator = new PinterestGenerator(GEMINI_API_KEY, $model);
        $result = $generator->generate($productName, $descForAI, $category);
        if (isset($result['pinterest_description'])) {
            $result['pinterest_description'] = limitWords($result['pinterest_description'], 15);
        }
    }

    // 4. Save to Database
    $__phase = 'db_insert';
    $insertSql = "INSERT INTO generated_pins (product_name, product_description, category, pinterest_title, pinterest_description, keywords, recommended_boards, strategy, affiliate_link, original_product_url, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($insertSql);
    $stmtInsert->execute([
        $productName,
        $description,
        $category,
        $result['pinterest_title'],
        $result['pinterest_description'],
        json_encode($result['keywords']),
        json_encode($result['recommended_boards']),
        $result['content_strategy'],
        $affiliateLink,
        $originalProductUrl,
        $processedImagePath
    ]);

    $insertedId = (int) $pdo->lastInsertId();

    $baseUrl = (function () {
        $https = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $https = true;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $https = true;
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') $https = true;
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = '/';
        if (isset($_SERVER['SCRIPT_NAME'])) {
            $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            if ($dir === '') $dir = '/';
        }
        if ($dir !== '/' && substr($dir, -1) !== '/') $dir .= '/';
        return $scheme . '://' . $host . $dir;
    })();

    $absoluteImageUrl = null;
    if (is_string($processedImagePath) && $processedImagePath !== '') {
        // If it's already absolute, keep it.
        if (preg_match('/^https?:\/\//i', $processedImagePath)) {
            $absoluteImageUrl = $processedImagePath;
        } else {
            $absoluteImageUrl = $baseUrl . ltrim($processedImagePath, '/');
        }
    }
    
    // Return result
    jsonResponse([
        'success' => true,
        'source' => $existingPin ? 'database_text_cache' : 'new_generation',
        'data' => $result,
        'image_url' => $absoluteImageUrl,
        'image_path' => $processedImagePath,
        'affiliate_link' => $affiliateLink,
        'pin_id' => $insertedId,
        'preview_url' => (function () use ($insertedId) {
            $https = false;
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $https = true;
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $https = true;
            $scheme = $https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir = '/';
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
                if ($dir === '') $dir = '/';
            }
            if ($dir !== '/' && substr($dir, -1) !== '/') $dir .= '/';
            return $scheme . '://' . $host . $dir . 'preview.php?id=' . urlencode((string) $insertedId);
        })(),
        'scraped_data' => isset($scrapedData) ? $scrapedData : null
    ], 200);

} catch (Throwable $e) {
    // Log full error on server (visible in hosting error logs)
    error_log('[generate_pin_api.php] ' . $e->getMessage());

    $publicMessage = $e->getMessage();
    if (stripos($publicMessage, 'reported as leaked') !== false) {
        $publicMessage = 'Gemini API key blocked (reported as leaked). Please create a new key and update GEMINI_API_KEY in .env on the server.';
    }

    $payload = ['success' => false, 'error' => $publicMessage, 'phase' => $__phase];

    if ($__debug) {
        $payload['type'] = get_class($e);
        $payload['file'] = $e->getFile();
        $payload['line'] = $e->getLine();
        $payload['trace'] = $e->getTraceAsString();
    }

    jsonResponse($payload, 500);
}
