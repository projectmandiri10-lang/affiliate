<?php

class ImageOptimizer
{
    // Keep untyped properties for wider shared-hosting compatibility.
    private $maxLongSide = 1600;
    private $jpgQuality = 82;
    private $webpQuality = 80;
    private $pngCompression = 6;

    public function __construct($options = [])
    {
        if (is_array($options)) {
            if (isset($options['max_long_side'])) $this->maxLongSide = (int) $options['max_long_side'];
            if (isset($options['jpg_quality'])) $this->jpgQuality = (int) $options['jpg_quality'];
            if (isset($options['webp_quality'])) $this->webpQuality = (int) $options['webp_quality'];
            if (isset($options['png_compression'])) $this->pngCompression = (int) $options['png_compression'];
        }
        if ($this->maxLongSide < 200) $this->maxLongSide = 200;
        if ($this->jpgQuality < 30) $this->jpgQuality = 30;
        if ($this->jpgQuality > 95) $this->jpgQuality = 95;
        if ($this->webpQuality < 30) $this->webpQuality = 30;
        if ($this->webpQuality > 95) $this->webpQuality = 95;
        if ($this->pngCompression < 0) $this->pngCompression = 0;
        if ($this->pngCompression > 9) $this->pngCompression = 9;
    }

    /**
     * Optimize an image file in place (overwrite only if smaller / acceptable).
     *
     * @param string $absolutePath Absolute path to image file.
     * @return array Stats and outcome: replaced(bool), reason(string), bytes/dims.
     * @throws Exception on hard failures.
     */
    public function optimizeInPlace($absolutePath)
    {
        $absolutePath = (string) $absolutePath;
        if ($absolutePath === '' || !is_file($absolutePath)) {
            throw new Exception('File not found.');
        }

        if (!function_exists('getimagesize')) {
            throw new Exception('GD/getimagesize not available.');
        }

        $beforeBytes = (int) @filesize($absolutePath);
        $info = @getimagesize($absolutePath);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            throw new Exception('Invalid image file.');
        }

        $beforeW = (int) $info[0];
        $beforeH = (int) $info[1];
        $mime = isset($info['mime']) ? (string) $info['mime'] : '';

        $type = $this->detectType($mime, $absolutePath);
        if ($type === 'webp' && (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp'))) {
            throw new Exception('WebP is not supported by GD on this server.');
        }

        if ($type === 'jpg' && (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg'))) {
            throw new Exception('JPEG is not supported by GD on this server.');
        }
        if ($type === 'png' && (!function_exists('imagecreatefrompng') || !function_exists('imagepng'))) {
            throw new Exception('PNG is not supported by GD on this server.');
        }

        $srcImg = $this->loadImage($absolutePath, $type);
        if (!$srcImg) {
            throw new Exception('Failed to load image.');
        }

        $longSide = max($beforeW, $beforeH);
        $needsResize = $longSide > $this->maxLongSide;

        $afterW = $beforeW;
        $afterH = $beforeH;
        $dstImg = $srcImg;

        if ($needsResize) {
            $scale = $this->maxLongSide / $longSide;
            $afterW = max(1, (int) round($beforeW * $scale));
            $afterH = max(1, (int) round($beforeH * $scale));

            $dstImg = imagecreatetruecolor($afterW, $afterH);
            if (!$dstImg) {
                imagedestroy($srcImg);
                throw new Exception('Failed to allocate resized image.');
            }

            if ($type === 'png' || $type === 'webp') {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                imagefilledrectangle($dstImg, 0, 0, $afterW, $afterH, $transparent);
            }

            $ok = imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $afterW, $afterH, $beforeW, $beforeH);
            imagedestroy($srcImg);
            if (!$ok) {
                imagedestroy($dstImg);
                throw new Exception('Failed to resize image.');
            }
        }

        $tmpPath = $absolutePath . '.tmp_' . uniqid('', true);
        $saved = $this->saveImage($dstImg, $tmpPath, $type);
        imagedestroy($dstImg);

        if (!$saved || !is_file($tmpPath)) {
            @unlink($tmpPath);
            throw new Exception('Failed to write optimized image.');
        }

        clearstatcache(true, $tmpPath);
        $afterBytes = (int) @filesize($tmpPath);
        if ($afterBytes <= 0) {
            @unlink($tmpPath);
            throw new Exception('Optimized file is invalid.');
        }

        $shouldReplace = false;
        $reason = '';

        if ($beforeBytes <= 0) {
            // Unlikely, but if we cannot measure original size, still replace after a resize.
            $shouldReplace = $needsResize;
            $reason = $shouldReplace ? 'resized' : 'unknown_original_size';
        } else {
            if ($needsResize) {
                // Allow up to +2% if resized (rare), otherwise prefer smaller.
                $shouldReplace = ($afterBytes <= (int) floor($beforeBytes * 1.02));
                $reason = $shouldReplace ? 'resized_and_optimized' : 'optimized_but_larger_than_expected';
            } else {
                $shouldReplace = ($afterBytes < $beforeBytes);
                $reason = $shouldReplace ? 'optimized_smaller' : 'no_smaller_output';
            }
        }

        $replaced = false;
        if ($shouldReplace) {
            $replaced = $this->replaceFile($tmpPath, $absolutePath);
            if (!$replaced) {
                @unlink($tmpPath);
                throw new Exception('Failed to replace original file.');
            }
        } else {
            @unlink($tmpPath);
        }

        clearstatcache(true, $absolutePath);

        return [
            'replaced' => (bool) $replaced,
            'reason' => (string) $reason,
            'before_bytes' => (int) $beforeBytes,
            'after_bytes' => (int) ($replaced ? @filesize($absolutePath) : $beforeBytes),
            'before_width' => (int) $beforeW,
            'before_height' => (int) $beforeH,
            'after_width' => (int) ($needsResize ? $afterW : $beforeW),
            'after_height' => (int) ($needsResize ? $afterH : $beforeH),
            'type' => (string) $type
        ];
    }

    private function detectType($mime, $path)
    {
        $mime = strtolower((string) $mime);
        if ($mime === 'image/jpeg') return 'jpg';
        if ($mime === 'image/png') return 'png';
        if ($mime === 'image/webp') return 'webp';

        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') return 'jpg';
        if ($ext === 'png') return 'png';
        if ($ext === 'webp') return 'webp';

        throw new Exception('Unsupported image type.');
    }

    private function loadImage($path, $type)
    {
        if ($type === 'jpg') return @imagecreatefromjpeg($path);
        if ($type === 'png') return @imagecreatefrompng($path);
        if ($type === 'webp') return @imagecreatefromwebp($path);
        return null;
    }

    private function saveImage($img, $path, $type)
    {
        if (!is_resource($img) && !(is_object($img) && get_class($img) === 'GdImage')) {
            return false;
        }

        if ($type === 'jpg') {
            if (function_exists('imageinterlace')) {
                @imageinterlace($img, true);
            }
            return @imagejpeg($img, $path, $this->jpgQuality);
        }
        if ($type === 'png') {
            // Preserve alpha already prepared for resized images; for non-resized it will keep original alpha.
            return @imagepng($img, $path, $this->pngCompression);
        }
        if ($type === 'webp') {
            return @imagewebp($img, $path, $this->webpQuality);
        }
        return false;
    }

    private function replaceFile($tmpPath, $dstPath)
    {
        // Try atomic-ish replace. Windows may not allow rename over existing file.
        if (@rename($tmpPath, $dstPath)) {
            return true;
        }

        // Fallback: copy over then delete tmp
        if (@copy($tmpPath, $dstPath)) {
            @unlink($tmpPath);
            return true;
        }

        // As last resort: remove dst then rename
        @unlink($dstPath);
        if (@rename($tmpPath, $dstPath)) {
            return true;
        }

        return false;
    }
}

