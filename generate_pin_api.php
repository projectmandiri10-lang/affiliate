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

// Validasi input
if (empty($productName) || empty($description)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product_name or description']);
    exit;
}

$processedImagePath = null;
$imageProcessor = new ImageProcessor();

try {
    // 1. Handle Image Upload & Watermark
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $processedImagePath = $imageProcessor->processImage($_FILES['product_image']);
    }

    $pdo = getDbConnection();

    // 2. Generate Content (Gemini)
    if (!GEMINI_API_KEY) {
        throw new Exception("API Key missing in config");
    }

    // Cek Database dulu (Optional: bisa diskip kalau user ingin selalu generate baru karena gambar mungkin beda)
    // Untuk V3 ini kita FORCE generate baru jika ada gambar baru, atau cek DB jika teks saja.
    // Tapi untuk simplifikasi dan sesuai request, kita generate terus atau cek sederhana.
    // Mari kita cek sederhana saja.
    
    $stmt = $pdo->prepare("SELECT * FROM generated_pins WHERE product_name = ? LIMIT 1");
    $stmt->execute([$productName]);
    $existingPin = $stmt->fetch();
    
    // Default result container
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
        // Generate Newly
        $generator = new PinterestGenerator(GEMINI_API_KEY);
        $result = $generator->generate($productName, $description, $category);
    }

    // 3. Simpan Transaksi Baru ke Database (Selalu Insert record baru atau Update? Agar history tercatat, Insert baru lebih aman untuk log)
    // Atau update record lama jika ada? Mari kita buat INSERT baru saja setiap kali request untuk tracking history generate.
    
    $insertSql = "INSERT INTO generated_pins (product_name, product_description, category, pinterest_title, pinterest_description, keywords, recommended_boards, strategy, affiliate_link, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
        $processedImagePath
    ]);
    
    // Kembalikan hasil
    echo json_encode([
        'success' => true,
        'source' => $existingPin ? 'database_text_cache' : 'new_generation',
        'data' => $result,
        'image_url' => $processedImagePath, // Frontend tinggal nampilin ini
        'affiliate_link' => $affiliateLink
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
