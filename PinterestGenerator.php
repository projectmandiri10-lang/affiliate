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
   - FORMAT JSON HARUS VALID (WAJIB):
     * Output JSON saja (tanpa penjelasan, tanpa ```).
     * Jangan gunakan line break / karakter kontrol di dalam string JSON.
       Jika butuh paragraf baru di deskripsi, gunakan \\n\\n (escape) di dalam string.
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

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            throw new Exception('Invalid Gemini API response (not JSON).');
        }

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
        $textContent = $this->stripCodeFences($textContent);

        // Gemini sometimes returns extra text around JSON. Extract the first JSON value if possible.
        $jsonCandidate = $this->extractFirstJsonValue($textContent);
        if ($jsonCandidate === null) {
            $jsonCandidate = trim($textContent);
        }

        $data = json_decode($jsonCandidate, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Common failure: model puts literal newlines inside quoted strings, producing JSON_ERROR_CTRL_CHAR.
            // Repair the JSON and retry.
            $repaired = $this->repairJsonCandidate($jsonCandidate);
            $data = json_decode($repaired, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON content: " . json_last_error_msg() . "\nRaw: " . $textContent);
        }

        if (!is_array($data)) {
            throw new Exception('Invalid JSON content (expected an object).');
        }

        return $this->normalizeGeneratedData($data);
    }

    private function stripCodeFences($text) {
        if (!is_string($text)) {
            return '';
        }

        // Remove surrounding ```json ... ``` if present
        $text = preg_replace('/^\s*```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/', '', $text);
        return trim($text);
    }

    /**
     * Extract the first complete JSON value (object/array) from a larger text blob.
     * Returns null if no JSON boundaries are found.
     */
    private function extractFirstJsonValue($text) {
        if (!is_string($text) || $text === '') {
            return null;
        }

        $len = strlen($text);
        $inString = false;
        $escape = false;
        $stack = [];
        $start = null;

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($start === null) {
                if ($ch === '{' || $ch === '[') {
                    $start = $i;
                    $stack[] = $ch;
                }
                continue;
            }

            if ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
                continue;
            }

            if ($ch === '}' || $ch === ']') {
                if (empty($stack)) {
                    break;
                }

                $open = $stack[count($stack) - 1];
                $isMatch = ($open === '{' && $ch === '}') || ($open === '[' && $ch === ']');
                if ($isMatch) {
                    array_pop($stack);
                    if (empty($stack)) {
                        return trim(substr($text, $start, $i - $start + 1));
                    }
                }
            }
        }

        return null;
    }

    private function repairJsonCandidate($jsonText) {
        if (!is_string($jsonText)) {
            return '';
        }

        $jsonText = $this->stripUtf8Bom($jsonText);

        // Normalize "smart quotes" sometimes produced by copy/paste or models.
        $jsonText = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xE2\x80\x9F"],
            '"',
            $jsonText
        );

        // Escape control characters inside quoted strings (fixes JSON_ERROR_CTRL_CHAR).
        $jsonText = $this->escapeControlCharsInJsonStrings($jsonText);

        // Remove trailing commas like {"a":1,} or [1,2,]
        $jsonText = preg_replace('/,\\s*([}\\]])/', '$1', $jsonText);

        return trim($jsonText);
    }

    private function stripUtf8Bom($text) {
        if (!is_string($text) || $text === '') {
            return $text;
        }

        // UTF-8 BOM
        if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
            return substr($text, 3);
        }

        return $text;
    }

    private function escapeControlCharsInJsonStrings($jsonText) {
        $out = '';
        $inString = false;
        $escape = false;
        $len = strlen($jsonText);

        for ($i = 0; $i < $len; $i++) {
            $ch = $jsonText[$i];
            $ord = ord($ch);

            if ($inString) {
                if ($escape) {
                    $out .= $ch;
                    $escape = false;
                    continue;
                }

                if ($ch === '\\') {
                    $out .= $ch;
                    $escape = true;
                    continue;
                }

                if ($ch === '"') {
                    $out .= $ch;
                    $inString = false;
                    continue;
                }

                if ($ord < 32) {
                    if ($ch === "\n") {
                        $out .= '\\\\n';
                    } elseif ($ch === "\r") {
                        $out .= '\\\\r';
                    } elseif ($ch === "\t") {
                        $out .= '\\\\t';
                    } else {
                        $out .= sprintf('\\\\u%04x', $ord);
                    }
                    continue;
                }

                $out .= $ch;
                continue;
            }

            if ($ch === '"') {
                $out .= $ch;
                $inString = true;
                continue;
            }

            // Outside strings, JSON only allows standard whitespace controls.
            if ($ord < 32) {
                if ($ch === "\n" || $ch === "\r" || $ch === "\t") {
                    $out .= $ch;
                }
                continue;
            }

            $out .= $ch;
        }

        return $out;
    }

    private function normalizeGeneratedData($data) {
        if (!is_array($data)) {
            return [
                'pinterest_title' => '',
                'pinterest_description' => '',
                'keywords' => [],
                'recommended_boards' => [],
                'content_strategy' => ''
            ];
        }

        $title = isset($data['pinterest_title']) ? (string) $data['pinterest_title'] : '';
        $desc = isset($data['pinterest_description']) ? (string) $data['pinterest_description'] : '';
        $strategy = isset($data['content_strategy']) ? (string) $data['content_strategy'] : '';

        $keywords = $this->normalizeStringArray(isset($data['keywords']) ? $data['keywords'] : []);
        $boards = $this->normalizeStringArray(isset($data['recommended_boards']) ? $data['recommended_boards'] : []);

        return [
            'pinterest_title' => trim($title),
            'pinterest_description' => trim($desc),
            'keywords' => $keywords,
            'recommended_boards' => $boards,
            'content_strategy' => trim($strategy)
        ];
    }

    private function normalizeStringArray($value) {
        if (is_string($value)) {
            $maybeJson = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybeJson)) {
                $value = $maybeJson;
            } else {
                $value = preg_split('/\\s*,\\s*/', $value);
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $out[] = $item;
        }

        return array_values($out);
    }
}
