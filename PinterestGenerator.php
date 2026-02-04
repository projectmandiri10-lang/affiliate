<?php

class PinterestGenerator {
    // Keep untyped for better compatibility on shared hosting PHP versions
    private $apiKey;
    private $model;
    private $apiUrl;

    /**
     * @param string $apiKey Gemini API key
     * @param string $model  Gemini model name (example: gemini-2.5-flash)
     */
    public function __construct($apiKey, $model = 'gemini-2.5-flash') {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->model . ":generateContent";
    }

    /**
     * Generate Pinterest SEO Content for a product
     * 
     * @param string $productName Nama produk
     * @param string $productDesc Deskripsi dasar produk
     * @param string $category Kategori produk
     * @return array Hasil generate (Judul, Deskripsi, Keywords, Board)
     */
    public function generate($productName, $productDesc, $category) {
        $prompt = $this->buildPrompt($productName, $productDesc, $category);
        $response = $this->callApi($prompt);
        return $this->parseResponse($response);
    }

    private function buildPrompt($name, $desc, $category) {
        return <<<PROMPT
Anda adalah spesialis Pinterest SEO Indonesia. Tugas anda adalah membuat konten Pinterest untuk produk berikut agar viral di Indonesia, SEO friendly, dan aman (tidak spam).

INFORMASI PRODUK:
Nama Produk: {$name}
Deskripsi: {$desc}
Kategori: {$category}

HARAP HASILKAN OUTPUT DALAM FORMAT JSON SAJA DENGAN STRUKTUR INI:
{
    "pinterest_title": "String (40-70 karakter)",
    "pinterest_description": "String (300-500 karakter)",
    "keywords": ["keyword1", "keyword2", "...", "keyword8"],
    "recommended_boards": ["Board 1", "Board 2", "Board 3"],
    "content_strategy": "Penjelasan singkat variasi hook yang digunakan"
}

ATURAN WAJIB (STRICT):
1. JUDUL:
   - Panjang WAJIB 40-70 karakter.
   - Harus mengandung keyword utama.
   - Tidak boleh clickbait (Jujur & Informatif).
   - Bahasa Indonesia yang menarik tapi sopan.

2. DESKRIPSI:
   - Panjang 300-500 karakter.
   - Paragraf 1: Hook natural & manfaat utama.
   - Paragraf 2: Detail pendukung & penggunaan keyword turunan yang mengalir (bukan stuffing).
   - Ending: Call to Action (CTA) yang HALUS (Contoh: "Cek detailnya di sini", "Simpan ide ini").
   - Tone: Santai, bersahabat, seperti rekomendasi teman.

3. KEYWORDS:
   - 5-8 keyword Bahasa Indonesia yang relevan.
   - Campuran short-tail dan long-tail.

4. BOARD:
   - 2-3 nama board yang spesifik dan relevan dengan niche.

5. UMUM:
   - SEMUA TEKS HARUS BAHASA INDONESIA.
   - TIDAK BOLEH ADA UNSUR SPAM.
   - FORMAT JSON HARUS VALID.
PROMPT;
    }

    private function callApi($text) {
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000,
            ]
        ];

        $ch = curl_init($this->apiUrl . "?key=" . urlencode($this->apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("Curl Error: " . $err);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $json = json_decode($response, true);
            $msg = isset($json['error']['message']) ? $json['error']['message'] : ('HTTP ' . $httpCode);

            // Make common permission issues friendlier
            if (stripos($msg, 'reported as leaked') !== false) {
                $msg = 'Gemini API key is blocked (reported as leaked). Create a new API key and update GEMINI_API_KEY in .env (do not commit it).';
            }

            throw new Exception('Gemini API error: ' . $msg);
        }
        
        return $response;
    }

    private function parseResponse($rawResponse) {
        $json = json_decode($rawResponse, true);

        // If API returns a structured error, surface a clean message.
        if (isset($json['error']['message'])) {
            $msg = $json['error']['message'];
            if (stripos($msg, 'reported as leaked') !== false) {
                $msg = 'Gemini API key is blocked (reported as leaked). Create a new API key and update GEMINI_API_KEY in .env.';
            }
            throw new Exception('Gemini API error: ' . $msg);
        }
        
        if (!isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid Gemini API response.');
        }

        $textContent = $json['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean markdown code blocks if present
        $textContent = preg_replace('/^```json\s*|\s*```$/', '', $textContent);
        
        $data = json_decode($textContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback if generic text is returned, simple parsing logic could go here
            // But for now, let's assume valid JSON due to strict prompt
            throw new Exception("Failed to parse JSON content: " . json_last_error_msg() . "\nRaw: " . $textContent);
        }

        return $data;
    }
}
