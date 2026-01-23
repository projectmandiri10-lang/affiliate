<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';
require_once 'PinterestGenerator.php';

// Ambil input JSON dari Frontend React
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validasi input
if (empty($input['product_name']) || empty($input['description'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product_name or description']);
    exit;
}

$productName = $input['product_name'];
$description = $input['description'];
$category = $input['category'] ?? 'General';

try {
    $pdo = getDbConnection();

    // 1. Cek apakah konten untuk produk ini sudah pernah digenerate?
    $stmt = $pdo->prepare("SELECT * FROM generated_pins WHERE product_name = ? LIMIT 1");
    $stmt->execute([$productName]);
    $existingPin = $stmt->fetch();

    if ($existingPin) {
        // Jika sudah ada, kembalikan data dari database (Hemat API)
        echo json_encode([
            'success' => true,
            'source' => 'database', // Info untuk frontend bahwa ini dari cache DB
            'data' => [
                'pinterest_title' => $existingPin['pinterest_title'],
                'pinterest_description' => $existingPin['pinterest_description'],
                'keywords' => json_decode($existingPin['keywords'], true),
                'recommended_boards' => json_decode($existingPin['recommended_boards'], true),
                'content_strategy' => $existingPin['strategy']
            ]
        ]);
        exit;
    }

    // 2. Jika belum ada, Generate Baru via Gemini
    if (!GEMINI_API_KEY) {
        throw new Exception("API Key missing in config");
    }

    $generator = new PinterestGenerator(GEMINI_API_KEY);
    $result = $generator->generate($productName, $description, $category);

    // 3. Simpan hasil ke Database
    $insertSql = "INSERT INTO generated_pins (product_name, product_description, category, pinterest_title, pinterest_description, keywords, recommended_boards, strategy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($insertSql);
    $stmtInsert->execute([
        $productName,
        $description,
        $category,
        $result['pinterest_title'],
        $result['pinterest_description'],
        json_encode($result['keywords']),
        json_encode($result['recommended_boards']),
        $result['content_strategy']
    ]);
    
    // Kembalikan hasil baru
    echo json_encode([
        'success' => true,
        'source' => 'api_generation',
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
