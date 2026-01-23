<?php

class PinterestGenerator {
    private string $apiKey;
    private string $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.0-pro:generateContent";

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Generate Pinterest SEO Content for a product
     * 
     * @param string $productName Nama produk
     * @param string $productDesc Deskripsi dasar produk
     * @param string $category Kategori produk
     * @return array Hasil generate (Judul, Deskripsi, Keywords, Board)
     */
    public function generate(string $productName, string $productDesc, string $category): array {
        $prompt = $this->buildPrompt($productName, $productDesc, $category);
        $response = $this->callApi($prompt);
        return $this->parseResponse($response);
    }

    private function buildPrompt(string $name, string $desc, string $category): string {
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

    private function callApi(string $text): string {
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

        $ch = curl_init($this->apiUrl . "?key=" . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception("Curl Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        return $response;
    }

    private function parseResponse(string $rawResponse): array {
        $json = json_decode($rawResponse, true);
        
        if (!isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid API Response or Blocked: " . $rawResponse);
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
