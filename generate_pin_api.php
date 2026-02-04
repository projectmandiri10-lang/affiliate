<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';
require_once 'PinterestGenerator.php';
require_once 'ImageProcessor.php';

// Handle POST Request Only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Ambil input (Support Multipart/Form-Data)
$productName = $_POST['product_name'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? 'General';
$affiliateLink = $_POST['affiliate_link'] ?? '';
$originalProductUrl = $_POST['original_product_url'] ?? null;

$processedImagePath = null;
$imageProcessor = new ImageProcessor();

try {
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

    // 1. Handle Image Upload & Watermark
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $processedImagePath = $imageProcessor->processImage($_FILES['product_image']);
    } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Provide clear message if upload is rejected by PHP limits
        $code = (int) $_FILES['product_image']['error'];
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception('Upload gagal: ukuran file melebihi limit PHP server. Naikkan upload_max_filesize dan post_max_size minimal 10M.');
        }
        throw new Exception('Upload gagal. Kode error: ' . $code);
    }

    // Validasi input (Manual Only Now)
    if (empty($productName) || empty($description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama produk dan deskripsi wajib diisi manual.']);
        exit;
    }

    $pdo = getDbConnection();

    // 3. Generate Content (Gemini)
    if (!GEMINI_API_KEY) {
        throw new Exception("API Key missing in config");
    }

    // Check DB cache
    $stmt = $pdo->prepare("SELECT * FROM generated_pins WHERE product_name = ? LIMIT 1");
    $stmt->execute([$productName]);
    $existingPin = $stmt->fetch();
    
    $result = null;

    if ($existingPin) {
        // Reuse content text
        $result = [
            'pinterest_title' => $existingPin['pinterest_title'],
            'pinterest_description' => $existingPin['pinterest_description'],
            'keywords' => json_decode($existingPin['keywords'], true),
            'recommended_boards' => json_decode($existingPin['recommended_boards'], true),
            'content_strategy' => $existingPin['strategy']
        ];
    } else {
        // Generate new content
        // If description is still empty, use product name as base
        $descForAI = !empty($description) ? $description : "Produk: " . $productName;
        
        $model = (defined('GEMINI_MODEL') && GEMINI_MODEL) ? GEMINI_MODEL : 'gemini-2.5-flash';
        $generator = new PinterestGenerator(GEMINI_API_KEY, $model);
        $result = $generator->generate($productName, $descForAI, $category);
    }

    // 4. Save to Database
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
    
    // Return result
    echo json_encode([
        'success' => true,
        'source' => $existingPin ? 'database_text_cache' : 'new_generation',
        'data' => $result,
        'image_url' => $processedImagePath,
        'affiliate_link' => $affiliateLink,
        'scraped_data' => isset($scrapedData) ? $scrapedData : null
    ]);

} catch (Exception $e) {
    // Log full error on server (visible in hosting error logs)
    error_log('[generate_pin_api.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
