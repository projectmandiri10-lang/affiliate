<?php
require_once __DIR__ . '/config.php';

function baseUrl() {
    $https = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $https = true;
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') $https = true;
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') $https = true;

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = '/';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($dir === '') $dir = '/';
    }
    if ($dir !== '/' && substr($dir, -1) !== '/') $dir .= '/';

    return $scheme . '://' . $host . $dir;
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function sanitizeText($s, $maxLen) {
    $text = (string) $s;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    $text = trim($text);

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

function parseList($s, $maxItems) {
    $s = (string) $s;
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $parts = preg_split('/[\n,]+/', $s);
    if (!is_array($parts)) return [];

    $out = [];
    foreach ($parts as $p) {
        $p = trim((string) $p);
        if ($p === '') continue;
        $out[] = $p;
        if (count($out) >= (int) $maxItems) break;
    }

    // unique, preserve order
    $seen = [];
    $uniq = [];
    foreach ($out as $p) {
        $k = function_exists('mb_strtolower') ? mb_strtolower($p) : strtolower($p);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $uniq[] = $p;
    }

    return $uniq;
}

function limitWords($text, $maxWords) {
    $text = (string) $text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    $maxWords = (int) $maxWords;
    if ($maxWords <= 0 || $text === '') {
        return $text;
    }
    $words = preg_split('/\s+/', $text);
    if (!is_array($words) || count($words) <= $maxWords) {
        return $text;
    }
    return trim(implode(' ', array_slice($words, 0, $maxWords)));
}

function hashtagify($keywords) {
    if (!is_array($keywords)) return [];
    $tags = [];
    foreach ($keywords as $kw) {
        if (!is_string($kw)) continue;
        $kw = trim($kw);
        if ($kw === '') continue;
        $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', str_replace(' ', '', $kw));
        $tag = trim($tag, '_');
        if ($tag === '') continue;
        $tags[] = '#' . $tag;
    }
    return array_values(array_unique($tags));
}

$pdo = getDbConnection();
$base = baseUrl();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing id';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeText($_POST['pinterest_title'] ?? '', 120);
    $desc = limitWords(sanitizeText($_POST['pinterest_description'] ?? '', 2000), 15);
    $affiliate = sanitizeText($_POST['affiliate_link'] ?? '', 500);
    $keywordsArr = parseList($_POST['keywords'] ?? '', 12);
    $boardsArr = parseList($_POST['recommended_boards'] ?? '', 6);
    $strategy = sanitizeText($_POST['content_strategy'] ?? '', 500);

    $sql = "UPDATE generated_pins SET pinterest_title = ?, pinterest_description = ?, keywords = ?, recommended_boards = ?, strategy = ?, affiliate_link = ? WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $title,
        $desc,
        json_encode($keywordsArr, JSON_UNESCAPED_UNICODE),
        json_encode($boardsArr, JSON_UNESCAPED_UNICODE),
        $strategy,
        $affiliate,
        $id
    ]);

    header('Location: preview.php?id=' . urlencode((string) $id));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM generated_pins WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$pin = $stmt->fetch();
if (!$pin) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$imagePath = (string) ($pin['image_path'] ?? '');
$imageUrl = $imagePath !== '' ? ($base . ltrim($imagePath, '/')) : '';
$previewUrl = $base . 'preview.php?id=' . urlencode((string) $id);

$keywords = json_decode((string) ($pin['keywords'] ?? ''), true);
if (!is_array($keywords)) $keywords = [];
$boards = json_decode((string) ($pin['recommended_boards'] ?? ''), true);
if (!is_array($boards)) $boards = [];
$hashtags = hashtagify($keywords);

$title = (string) ($pin['pinterest_title'] ?? '');
$desc = limitWords((string) ($pin['pinterest_description'] ?? ''), 15);
$affiliate = (string) ($pin['affiliate_link'] ?? '');
$strategy = (string) ($pin['strategy'] ?? '');

$captionFull = trim($title . "\n\n" . $desc . "\n\n" . implode(' ', $hashtags));
$captionForPinterest = function_exists('mb_substr') ? mb_substr($captionFull, 0, 650) : substr($captionFull, 0, 650);
$params = [
    'url' => $previewUrl,
    'description' => $captionForPinterest
];
if ($imageUrl !== '') $params['media'] = $imageUrl;
$pinUrl = 'https://www.pinterest.com/pin/create/button/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Pin</title>
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <main class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Edit Pin</h1>
                <p class="text-sm text-slate-500">Update caption/keyword, lalu pin lewat halaman preview.</p>
            </div>
            <div class="flex gap-2">
                <a href="pins.php" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 transition text-sm font-semibold">Galeri</a>
                <a href="preview.php?id=<?= (int) $id ?>" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 transition text-sm font-semibold">Preview</a>
            </div>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <?php if ($imageUrl !== ''): ?>
                    <img src="<?= h($imageUrl) ?>" alt="<?= h($title) ?>" class="w-full h-auto object-cover">
                <?php else: ?>
                    <div class="p-6 text-slate-600">Tidak ada gambar.</div>
                <?php endif; ?>

                <div class="p-4 space-y-3 border-t border-slate-100">
                    <a data-pin-do="buttonPin" href="<?= h($pinUrl) ?>" target="_blank" rel="noopener noreferrer" class="block text-center px-4 py-3 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 transition">
                        Pin ke Pinterest
                    </a>
                    <a href="<?= h($previewUrl) ?>" target="_blank" rel="noopener" class="block text-center px-4 py-3 rounded-lg bg-slate-800 text-white font-semibold hover:bg-slate-900 transition">
                        Buka Halaman Preview
                    </a>
                </div>
            </div>

            <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Judul Pinterest</label>
                    <input name="pinterest_title" value="<?= h($title) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi</label>
                    <textarea name="pinterest_description" rows="7" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" required><?= h($desc) ?></textarea>
                    <p class="text-xs text-slate-500 mt-1">Deskripsi disimpan max 15 kata. Hashtag otomatis ditaruh di bawah deskripsi pada halaman preview/copy caption.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Keywords (pisahkan dengan koma / baris baru)</label>
                    <textarea name="keywords" rows="4" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition"><?= h(implode(", ", $keywords)) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Recommended Boards</label>
                    <textarea name="recommended_boards" rows="3" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition"><?= h(implode(", ", $boards)) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Strategy</label>
                    <input name="content_strategy" value="<?= h($strategy) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Affiliate Link</label>
                    <input name="affiliate_link" value="<?= h($affiliate) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                </div>

                <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition">Simpan</button>
            </form>
        </section>
    </main>

    <script async defer src="https://assets.pinterest.com/js/pinit.js"></script>
</body>
</html>
