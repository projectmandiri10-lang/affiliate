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
     * @param string $productName Product name
     * @param string $productDesc Base product/offer description
     * @param string $category    Product category
     * @return array Generated output (Title, Description, Keywords, Boards)
     */
    public function generate($productName, $productDesc, $category) {
        $productName = $this->sanitizeInputText($productName, 200);
        $category = $this->sanitizeInputText($category, 100);
        $productDesc = $this->sanitizeInputText($productDesc, 3000);

        $prompt = $this->buildPrompt($productName, $productDesc, $category);
        $response = $this->callApi($prompt);
        return $this->parseResponse($response);
    }

    private function sanitizeInputText($value, $maxLen) {
        $text = (string) $value;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Drop ASCII control characters except newline and tab.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize whitespace (including newlines) to reduce prompt weirdness.
        $text = preg_replace('/[ \t\n]+/', ' ', $text);
        $text = trim($text);

        // If the hosting produces invalid UTF-8 bytes, try to strip them.
        if ($text !== '' && !preg_match('//u', $text) && function_exists('iconv')) {
            $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($fixed) && $fixed !== '') {
                $text = $fixed;
            }
        }

        $maxLen = (int) $maxLen;
        if ($maxLen > 0) {
            if (function_exists('mb_substr')) {
                $text = mb_substr($text, 0, $maxLen);
            } else {
                $text = substr($text, 0, $maxLen);
            }
        }

        return $text;
    }

    private function buildPrompt($name, $desc, $category) {
        return <<<PROMPT
You are a Pinterest SEO specialist. Your task is to generate Pinterest-ready copy for the offer below so it is engaging, search-friendly, and compliant (not spammy).

OFFER INFORMATION:
Product/Offer Name: {$name}
Description: {$desc}
Category: {$category}

RETURN ONLY VALID JSON using this exact structure:
{
  "pinterest_title": "String (40-70 characters)",
  "pinterest_description": "String (7-15 words, no hashtags)",
  "keywords": ["keyword1", "keyword2", "...", "keyword8"],
  "recommended_boards": ["Board 1", "Board 2", "Board 3"],
  "content_strategy": "Brief explanation of the hook angle used"
}

STRICT RULES:
1) TITLE
   - MUST be 40-70 characters.
   - MUST include the primary keyword naturally.
   - Must be honest and informative (no misleading clickbait).
   - English only.

2) DESCRIPTION
   - MUST be 7-15 words (words, not characters).
   - Must be exactly one short sentence, natural and informative.
   - No hashtags (#) and no line breaks.
   - English only.

3) KEYWORDS
   - 5-8 relevant English keywords.
   - Mix short-tail and long-tail queries.

4) BOARDS
   - 2-3 board names that are specific to the niche.

5) COMPLIANCE / SAFETY
   - Avoid medical or income claims (no “cure”, “guaranteed”, “get rich quick”, etc.).
   - Do not promise results; keep it neutral and benefit-led.
   - No spammy language, excessive punctuation, or ALL CAPS.

6) OUTPUT FORMAT (MANDATORY)
   - Output JSON only (no explanations, no Markdown, no ``` fences).
   - Do not use raw newlines/control characters inside JSON strings.
     If you need paragraph breaks, use escaped \\n\\n inside a string.
PROMPT;
    }

    private function callApi($text) {
        $baseData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.25,
                'maxOutputTokens' => 1400,
                // Ask API to constrain model output to valid JSON when supported.
                'responseMimeType' => 'application/json'
            ]
        ];

        $attemptData = $baseData;
        $attempts = 0;

        while (true) {
            $attempts++;
            list($httpCode, $response) = $this->doCurlRequest($attemptData);

            if ($httpCode < 400) {
                return $response;
            }

            $json = json_decode($response, true);
            $msg = isset($json['error']['message']) ? $json['error']['message'] : ('HTTP ' . $httpCode);

            // If the API doesn't recognize responseMimeType (older backend), retry once without it.
            if ($attempts === 1 && isset($attemptData['generationConfig']['responseMimeType']) && stripos($msg, 'responseMimeType') !== false) {
                unset($attemptData['generationConfig']['responseMimeType']);
                continue;
            }

            // Make common permission issues friendlier
            if (stripos($msg, 'reported as leaked') !== false) {
                $msg = 'Gemini API key is blocked (reported as leaked). Create a new API key and update GEMINI_API_KEY in .env (do not commit it).';
            }

            throw new Exception('Gemini API error: ' . $msg);
        }
    }

    private function doCurlRequest($data) {
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

        return [$httpCode, $response];
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
            // Last resort: do a loose extraction so the UX still works.
            $loose = $this->looseParseFromText($textContent);
            if (is_array($loose) && !empty($loose)) {
                return $this->normalizeGeneratedData($loose);
            }

            $errMsg = json_last_error_msg();
            error_log('[PinterestGenerator] Failed to parse JSON content: ' . $errMsg . ' Raw: ' . $textContent);

            $public = "Failed to parse JSON content: " . $errMsg;
            if (getenv('APP_DEBUG') === '1') {
                $public .= "\nRaw: " . $textContent;
            }

            throw new Exception($public);
        }

        if (!is_array($data)) {
            throw new Exception('Invalid JSON content (expected an object).');
        }

        return $this->normalizeGeneratedData($data);
    }

    /**
     * Tries to salvage fields from a JSON-like or semi-structured text response.
     * Returns an associative array compatible with normalizeGeneratedData(), or [] if nothing useful found.
     */
    private function looseParseFromText($textContent) {
        if (!is_string($textContent) || trim($textContent) === '') {
            return [];
        }

        $out = [];

        $title = $this->extractQuotedValueForKey($textContent, 'pinterest_title');
        if ($title !== null) {
            $out['pinterest_title'] = $title;
        }

        $desc = $this->extractQuotedValueForKey($textContent, 'pinterest_description');
        if ($desc !== null) {
            $out['pinterest_description'] = $desc;
        }

        $strategy = $this->extractQuotedValueForKey($textContent, 'content_strategy');
        if ($strategy !== null) {
            $out['content_strategy'] = $strategy;
        }

        $keywords = $this->extractArrayForKey($textContent, 'keywords');
        if (!empty($keywords)) {
            $out['keywords'] = $keywords;
        }

        $boards = $this->extractArrayForKey($textContent, 'recommended_boards');
        if (!empty($boards)) {
            $out['recommended_boards'] = $boards;
        }

        // If Gemini returned a different key name by mistake, try common aliases.
        if (!isset($out['pinterest_description'])) {
            $desc2 = $this->extractQuotedValueForKey($textContent, 'description');
            if ($desc2 !== null) {
                $out['pinterest_description'] = $desc2;
            }
        }

        return $out;
    }

    private function extractQuotedValueForKey($text, $key) {
        if (!is_string($text) || !is_string($key) || $key === '') {
            return null;
        }

        $quotedKey = preg_quote($key, '/');
        $patterns = [
            '/"' . $quotedKey . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s',
            '/"' . $quotedKey . '"\s*:\s*\'((?:\\\\.|[^\'\\\\])*)\'/s',
            '/\'' . $quotedKey . '\'\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s',
            '/\'' . $quotedKey . '\'\s*:\s*\'((?:\\\\.|[^\'\\\\])*)\'/s',
            '/\b' . $quotedKey . '\b\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s',
            '/\b' . $quotedKey . '\b\s*:\s*\'((?:\\\\.|[^\'\\\\])*)\'/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return $this->unescapeLooseString($m[1]);
            }
        }

        // Fallback scanner: handles missing closing quote / broken JSON.
        $scanned = $this->scanStringValueAfterKey($text, $key);
        if ($scanned === null) {
            return null;
        }

        return $this->unescapeLooseString($scanned);
    }

    private function extractArrayForKey($text, $key) {
        if (!is_string($text) || !is_string($key) || $key === '') {
            return [];
        }

        $inner = $this->scanArrayValueAfterKey($text, $key);
        if ($inner === null) {
            // Regex fallback for well-formed arrays
            $pattern = '/(?:\"|\')' . preg_quote($key, '/') . '(?:\"|\')\s*:\s*\[(.*?)\]/s';
            if (!preg_match($pattern, $text, $m)) {
                return [];
            }
            $inner = $m[1];
        }

        $items = [];

        // Prefer quoted items (double and single quotes).
        if (preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/s', $inner, $mm)) {
            foreach ($mm[1] as $raw) {
                $val = trim($this->unescapeLooseString($raw));
                if ($val !== '') {
                    $items[] = $val;
                }
            }
        }
        if (preg_match_all('/\'((?:\\\\.|[^\'\\\\])*)\'/s', $inner, $mm2)) {
            foreach ($mm2[1] as $raw) {
                $val = trim($this->unescapeLooseString($raw));
                if ($val !== '') {
                    $items[] = $val;
                }
            }
        }

        // If still empty, try comma-separated unquoted items.
        if (empty($items)) {
            $parts = preg_split('/\s*,\s*/', trim($inner));
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    $p = trim($p);
                    $p = trim($p, " \t\n\r\0\x0B\"'");
                    if ($p !== '') {
                        $items[] = $p;
                    }
                }
            }
        }

        return array_values($items);
    }

    private function scanStringValueAfterKey($text, $key) {
        if (!is_string($text) || !is_string($key) || $key === '') {
            return null;
        }

        $pos = stripos($text, $key);
        if ($pos === false) {
            return null;
        }

        $colon = strpos($text, ':', $pos);
        if ($colon === false) {
            return null;
        }

        $i = $colon + 1;
        $len = strlen($text);
        while ($i < $len && ctype_space($text[$i])) {
            $i++;
        }

        if ($i >= $len) {
            return null;
        }

        $quote = $text[$i];
        if ($quote !== '"' && $quote !== "'") {
            return null;
        }
        $i++;

        $out = '';
        $escape = false;
        for (; $i < $len; $i++) {
            $ch = $text[$i];

            // If the string is broken and a new key likely starts on a new line, stop here.
            if (($ch === "\n" || $ch === "\r") && !$escape) {
                $probe = substr($text, $i + 1, 120);
                if (preg_match('/^\s*(?:["\']?[A-Za-z_][A-Za-z0-9_]*["\']?)\s*:/', $probe)) {
                    return $out;
                }
            }

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

            if ($ch === $quote) {
                return $out;
            }

            $out .= $ch;
        }

        // Missing closing quote: return what we have.
        return $out;
    }

    private function scanArrayValueAfterKey($text, $key) {
        if (!is_string($text) || !is_string($key) || $key === '') {
            return null;
        }

        $pos = stripos($text, $key);
        if ($pos === false) {
            return null;
        }

        $bracket = strpos($text, '[', $pos);
        if ($bracket === false) {
            return null;
        }

        $i = $bracket + 1;
        $len = strlen($text);
        $inString = false;
        $quote = '';
        $escape = false;
        $out = '';

        for (; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                $out .= $ch;

                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === $quote) {
                    $inString = false;
                    $quote = '';
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $quote = $ch;
                $out .= $ch;
                continue;
            }

            if ($ch === ']') {
                return $out;
            }

            $out .= $ch;
        }

        // Missing closing bracket: return what we have.
        return $out !== '' ? $out : null;
    }

    private function unescapeLooseString($s) {
        if (!is_string($s)) {
            return '';
        }

        // Replace common JSON escapes.
        $s = str_replace(['\\\\n', '\\\\r', '\\\\t', '\\\\"', '\\\\/'], ["\n", "\r", "\t", '"', '/'], $s);
        $s = str_replace('\\\\\\\\', '\\', $s);

        // Convert unicode escapes (\uXXXX) when present.
        $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            $code = hexdec($m[1]);
            if ($code < 0x80) {
                return chr($code);
            }
            if ($code < 0x800) {
                return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
            }
            return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
        }, $s);

        // Drop remaining control chars.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);

        return $s;
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
        $len = strlen($jsonText);

        for ($i = 0; $i < $len; $i++) {
            $ch = $jsonText[$i];
            $ord = ord($ch);

            if (!$inString) {
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
                continue;
            }

            // Inside strings
            if ($ch === '"') {
                $out .= $ch;
                $inString = false;
                continue;
            }

            if ($ch === '\\') {
                // Validate escape sequence. If invalid, treat backslash as literal (escape it).
                if ($i + 1 >= $len) {
                    $out .= '\\\\';
                    continue;
                }

                $next = $jsonText[$i + 1];
                $nextOrd = ord($next);

                // Valid 2-char escapes
                if (strpos("\"\\\\/bfnrt", $next) !== false) {
                    $out .= '\\' . $next;
                    $i++;
                    continue;
                }

                // Unicode escape
                if ($next === 'u') {
                    $hex = substr($jsonText, $i + 2, 4);
                    if (strlen($hex) === 4 && ctype_xdigit($hex)) {
                        $out .= '\\u' . $hex;
                        $i += 5;
                        continue;
                    }

                    // Invalid \uXXXX, escape the backslash itself
                    $out .= '\\\\';
                    continue;
                }

                // Backslash followed by a control character (common model formatting bug)
                if ($nextOrd < 32) {
                    if ($next === "\n") {
                        $out .= '\\n';
                    } elseif ($next === "\r") {
                        $out .= '\\r';
                    } elseif ($next === "\t") {
                        $out .= '\\t';
                    } else {
                        $out .= sprintf('\\u%04x', $nextOrd);
                    }
                    $i++;
                    continue;
                }

                // Invalid escape like "\p" -> make it literal "\\p"
                $out .= '\\\\';
                continue;
            }

            // Escape control characters inside strings.
            if ($ord < 32) {
                if ($ch === "\n") {
                    $out .= '\\n';
                } elseif ($ch === "\r") {
                    $out .= '\\r';
                } elseif ($ch === "\t") {
                    $out .= '\\t';
                } else {
                    $out .= sprintf('\\u%04x', $ord);
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
            'pinterest_description' => $this->normalizeShortDescription($desc),
            'keywords' => $keywords,
            'recommended_boards' => $boards,
            'content_strategy' => trim($strategy)
        ];
    }

    private function normalizeShortDescription($desc) {
        $text = (string) $desc;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        // Remove hashtags if the model included them anyway.
        $text = preg_replace('/#\S+/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Enforce max 15 words.
        $words = preg_split('/\s+/', $text);
        if (!is_array($words)) {
            return $text;
        }
        $words = array_values(array_filter($words, function ($w) {
            return is_string($w) && trim($w) !== '';
        }));
        if (count($words) > 15) {
            $words = array_slice($words, 0, 15);
            $text = trim(implode(' ', $words));
        }

        return $text;
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
