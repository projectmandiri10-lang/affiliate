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
        
        // Get page content
        $html = $this->fetchPage($finalUrl);
        
        if (!$html) {
            throw new Exception("Gagal mengambil data dari Shopee. Pastikan link valid.");
        }

        // Extract JSON-LD data (Shopee embeds product data in structured format)
        $productData = $this->extractJsonLd($html);
        
        if (!$productData) {
            // Fallback: Try to extract from meta tags
            $productData = $this->extractMetaTags($html);
        }

        return $productData;
    }

    private function resolveUrl(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        return $finalUrl ?: $url;
    }

    private function fetchPage(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
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
