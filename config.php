<?php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'affiliate_system'); // Silakan ganti sesuai nama database Anda
define('DB_USER', 'root');             // Silakan ganti sesuai user database Anda
define('DB_PASS', '');                 // Silakan ganti sesuai password database Anda

// Gemini API Key
define('GEMINI_API_KEY', 'AIzaSyBvrFoJFzg4Szv-2CA4ZxxXxMFcLPZgjOc');

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
        // Return null or throw custom error depending on how you want to handle it
        throw new \Exception("Database Connection Error: " . $e->getMessage());
    }
}
