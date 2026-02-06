<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PinterestGenerator.php';

if (!defined('GEMINI_API_KEY') || !GEMINI_API_KEY) {
    echo "Missing GEMINI_API_KEY. Set it in .env (see .env.example).\n";
    exit(1);
}

$model = (defined('GEMINI_MODEL') && GEMINI_MODEL) ? GEMINI_MODEL : 'gemini-2.5-flash';
$generator = new PinterestGenerator(GEMINI_API_KEY, $model);

// Example offer data (ClickBank-style)
$productName = "At-Home Mobility Routine";
$productDesc = "A beginner-friendly mobility routine designed to improve flexibility and reduce stiffness with simple daily exercises.";
$category = "Health & Fitness";

try {
    echo "Generating Pinterest content...\n";
    $result = $generator->generate($productName, $productDesc, $category);
    
    echo "\n--- GENERATED PINTEREST OUTPUT ---\n";
    echo "TITLE       : " . ($result['pinterest_title'] ?? '') . "\n";
    echo "DESCRIPTION : " . ($result['pinterest_description'] ?? '') . "\n";
    echo "KEYWORDS    : " . implode(", ", (array) ($result['keywords'] ?? [])) . "\n";
    echo "BOARDS      : " . implode(", ", (array) ($result['recommended_boards'] ?? [])) . "\n";
    echo "STRATEGY    : " . ($result['content_strategy'] ?? '') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
