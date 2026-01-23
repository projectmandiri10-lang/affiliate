<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

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

// API Key Configuration
$apiKey = 'AIzaSyBvrFoJFzg4Szv-2CA4ZxxXxMFcLPZgjOc';

if (!$apiKey) {
    // Fallback atau error jika tidak ada key
    http_response_code(500);
    echo json_encode(['error' => 'Server Configuration Error: API Key missing']);
    exit;
}

try {
    $generator = new PinterestGenerator($apiKey);
    $result = $generator->generate(
        $input['product_name'],
        $input['description'],
        $input['category'] ?? 'General'
    );
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
