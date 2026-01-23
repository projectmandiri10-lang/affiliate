<?php

class ShopeeScraper {
    
    /**
     * Extract product info from Shopee affiliate link
     * @param string $url Shopee affiliate URL
     * @return array Product data (name, description, price, image)
     */
    public function scrapeProduct(string $url): array {
        // Resolve shortened URL first (shope.ee redirects)
        $finalUrl = $this->resolveUrl($url);
        
        if (!$finalUrl) {
            throw new Exception("Link tidak valid atau tidak dapat diakses");
        }
        
        // Get page content
        $html = $this->fetchPage($finalUrl);
        
        if (!$html) {
            throw new Exception("Gagal mengambil halaman Shopee. Coba lagi atau isi manual.");
        }

        // Try multiple extraction methods
        $productData = null;
        
        // Method 1: JSON-LD structured data
        $productData = $this->extractJsonLd($html);
        
        // Method 2: Meta tags
        if (!$productData || empty($productData['name'])) {
            $productData = $this->extractMetaTags($html);
        }
        
        // Method 3: Page title and basic extraction
        if (!$productData || empty($productData['name'])) {
            $productData = $this->extractFromTitle($html);
        }

        // Validate we got at least product name
        if (empty($productData['name'])) {
            throw new Exception("Tidak dapat menemukan data produk dari link tersebut. Pastikan link valid dan produk masih tersedia.");
        }

        return $productData;
    }

    private function resolveUrl(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        return $finalUrl ?: null;
    }

    private function fetchPage(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $response : null;
    }

    private function extractJsonLd(string $html): ?array {
        // Look for JSON-LD structured data
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            $json = json_decode($matches[1], true);
            
            if (isset($json['name'])) {
                return [
                    'name' => $json['name'] ?? '',
                    'description' => $json['description'] ?? '',
                    'price' => $json['offers']['price'] ?? '',
                    'image' => $json['image'] ?? ''
                ];
            }
        }
        
        return null;
    }

    private function extractMetaTags(string $html): array {
        $data = [
            'name' => '',
            'description' => '',
            'price' => '',
            'image' => ''
        ];

        // Extract OG tags
        if (preg_match('/<meta property="og:title" content="(.*?)"/', $html, $matches)) {
            $data['name'] = html_entity_decode($matches[1]);
        }
        
        if (preg_match('/<meta property="og:description" content="(.*?)"/', $html, $matches)) {
            $data['description'] = html_entity_decode($matches[1]);
        }
        
        if (preg_match('/<meta property="og:image" content="(.*?)"/', $html, $matches)) {
            $data['image'] = $matches[1];
        }

        // Try to get price from meta or title
        if (preg_match('/Rp\s?([\d.,]+)/', $html, $matches)) {
            $data['price'] = $matches[0];
        }

        return $data;
    }
    
    private function extractFromTitle(string $html): array {
        $data = [
            'name' => '',
            'description' => '',
            'price' => '',
            'image' => ''
        ];
        
        // Try to get title tag
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode($matches[1]);
            // Remove common suffixes
            $title = preg_replace('/\s*[\|\-]\s*(Shopee|Indonesia).*$/i', '', $title);
            $data['name'] = trim($title);
        }
        
        // Try to find any image
        if (preg_match('/<meta property="og:image" content="(.*?)"/', $html, $matches)) {
            $data['image'] = $matches[1];
        } elseif (preg_match('/<img[^>]+src="([^"]+)"/', $html, $matches)) {
            if (strpos($matches[1], 'http') === 0) {
                $data['image'] = $matches[1];
            }
        }
        
        return $data;
    }

    /**
     * Download image from URL
     * @param string $imageUrl
     * @param string $savePath
     * @return bool
     */
    public function downloadImage(string $imageUrl, string $savePath): bool {
        $ch = curl_init($imageUrl);
        $fp = fopen($savePath, 'wb');
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        
        $result = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        return $result !== false;
    }
}
