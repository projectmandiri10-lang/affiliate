<?php

class ImageProcessor {
    private $uploadDir = 'uploads/';
    
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

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception("Ukuran file terlalu besar (Max 5MB).");
        }

        // Generate Filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('pin_') . '.' . $ext;
        $targetPath = $this->uploadDir . $filename;

        // Move Uploaded File
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Gagal mengupload file.");
        }

        // Add Watermark
        $this->addWatermark($targetPath);

        return $targetPath;
    }

    private function addWatermark($imagePath) {
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                $img = imagecreatefromjpeg($imagePath);
                break;
            case 'png':
                $img = imagecreatefrompng($imagePath);
                break;
            case 'webp':
                $img = imagecreatefromwebp($imagePath);
                break;
            default:
                return;
        }

        // Warna Watermark (Merah Background, Putih Text)
        $bgInfo = getimagesize($imagePath);
        $width = $bgInfo[0];
        $height = $bgInfo[1];

        // Buat warna
        $white = imagecolorallocate($img, 255, 255, 255);
        $red = imagecolorallocate($img, 220, 38, 38); // Red-600 like Tailwind

        // Setup Font (Built-in GD font 5 is simple, better use TTF if available, but let's stick to simple GD for portability)
        // Draw Red Rectangle Badge
        $badgeWidth = 120;
        $badgeHeight = 40;
        $margin = 20;

        // Posisi: Pojok Kanan Atas
        $x1 = $width - $badgeWidth - $margin;
        $y1 = $margin;
        $x2 = $width - $margin;
        $y2 = $margin + $badgeHeight;

        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $red);

        // Add Text "PROMO"
        $text = "PROMO";
        $font = 5; // Built-in font, biggest size
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        $textX = $x1 + ($badgeWidth - $textWidth) / 2;
        $textY = $y1 + ($badgeHeight - $textHeight) / 2;

        imagestring($img, $font, $textX, $textY, $text, $white);

        // Save Image
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($img, $imagePath, 90);
                break;
            case 'png':
                imagepng($img, $imagePath);
                break;
            case 'webp':
                imagewebp($img, $imagePath);
                break;
        }

        imagedestroy($img);
    }
}
