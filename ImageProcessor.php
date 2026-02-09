<?php

class ImageProcessor {
    private $uploadDir = 'uploads/';
    // Keep untyped for wider PHP compatibility on shared hosting
    private $maxFileSize = 10485760; // 10 MB
    
    public function __construct() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function processImage($file) {
        // Validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, atau WebP.");
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("Ukuran file terlalu besar (Max 10MB).");
        }

        // Generate Filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('pin_') . '.' . $ext;
        $targetPath = $this->uploadDir . $filename;

        // Move Uploaded File
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Gagal mengupload file.");
        }

        return $targetPath;
    }
}
