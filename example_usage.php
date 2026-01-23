<?php

require_once 'PinterestGenerator.php';

// Pastikan Anda memiliki API Key Google Gemini (AI Studio)
// Dapatkan di: https://aistudio.google.com/app/apikey
$apiKey = "GANTI_DENGAN_API_KEY_ANDA"; 

$generator = new PinterestGenerator($apiKey);

// Contoh Data Produk
$productName = "Gamis Rayon Premium Motif Bunga";
$productDesc = "Gamis bahan rayon viscose grade A yang adem banget. Busui friendly dengan resleting depan. Wudhu friendly. Cocok untuk dipakai harian atau pengajian. Ukuran All Size fit to XL.";
$category = "Fashion Muslim";

try {
    echo "Sedang memproses generation...\n";
    $result = $generator->generate($productName, $productDesc, $category);
    
    echo "\n--- HASIL GENERATE PINTEREST ---\n";
    echo "JUDUL       : " . $result['pinterest_title'] . "\n";
    echo "DESKRIPSI   : " . $result['pinterest_description'] . "\n";
    echo "KEYWORDS    : " . implode(", ", $result['keywords']) . "\n";
    echo "BOARD SARAN : " . implode(", ", $result['recommended_boards']) . "\n";
    echo "STRATEGI    : " . $result['content_strategy'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
