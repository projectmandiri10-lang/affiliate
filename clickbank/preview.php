<?php
require_once __DIR__ . '/config.php';

function baseUrl() {
    $https = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $https = true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $https = true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $https = true;
    }

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = '/';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($dir === '') {
            $dir = '/';
        }
    }
    if ($dir !== '/' && substr($dir, -1) !== '/') {
        $dir .= '/';
    }

    return $scheme . '://' . $host . $dir;
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function safeSubstr($s, $maxLen) {
    $s = (string) $s;
    $maxLen = (int) $maxLen;
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $maxLen);
    }
    return substr($s, 0, $maxLen);
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

function safeRandomInt($min, $max) {
    $min = (int) $min;
    $max = (int) $max;
    if ($min > $max) {
        $t = $min;
        $min = $max;
        $max = $t;
    }
    if (function_exists('random_int')) {
        try {
            return random_int($min, $max);
        } catch (Throwable $e) {
            // fall through
        }
    }
    return mt_rand($min, $max);
}

function hashtagify($keywords) {
    if (!is_array($keywords)) {
        return [];
    }

    $tags = [];
    foreach ($keywords as $kw) {
        if (!is_string($kw)) {
            continue;
        }
        $kw = trim($kw);
        if ($kw === '') {
            continue;
        }

        // Keep letters/digits/underscore, remove spaces and punctuation for cleaner hashtags.
        $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', str_replace(' ', '', $kw));
        $tag = trim($tag, '_');
        if ($tag === '') {
            continue;
        }
        $tags[] = '#' . $tag;
    }

    return array_values(array_unique($tags));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing id';
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT * FROM generated_pins WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$pin = $stmt->fetch();

if (!$pin) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$base = baseUrl();
$previewUrl = $base . 'preview.php?id=' . urlencode((string) $pin['id']);

$imagePath = isset($pin['image_path']) ? (string) $pin['image_path'] : '';
$imageUrl = $imagePath !== '' ? ($base . ltrim($imagePath, '/')) : '';

$keywords = json_decode((string) ($pin['keywords'] ?? ''), true);
if (!is_array($keywords)) {
    $keywords = [];
}
$hashtags = hashtagify($keywords);

$title = (string) ($pin['pinterest_title'] ?? '');
$desc = (string) ($pin['pinterest_description'] ?? '');
$affiliate = (string) ($pin['affiliate_link'] ?? '');

$countdownStart = safeRandomInt(13, 17);

$desc = limitWords($desc, 15);
$hashtagsText = implode(' ', $hashtags);
$descWithHashtags = trim($desc . ($hashtagsText !== '' ? "\n\n" . $hashtagsText : ''));

$captionFull = trim($title . "\n\n" . $descWithHashtags);
$captionForPinterest = safeSubstr($captionFull, 650);
$pinterestDescription = $captionForPinterest;
$pinterestParams = [
    'url' => $previewUrl,
    'description' => $pinterestDescription
];
if ($imageUrl !== '') {
    $pinterestParams['media'] = $imageUrl;
}
$pinterestCreateUrl = 'https://www.pinterest.com/pin/create/button/?' . http_build_query($pinterestParams, '', '&', PHP_QUERY_RFC3986);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($title !== '' ? $title : ($pin['product_name'] ?? 'Preview')) ?></title>
    <link rel="icon" href="data:,">

    <meta name="description" content="<?= h(safeSubstr($desc, 180)) ?>">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?= h($previewUrl) ?>">

    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= h($previewUrl) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h(safeSubstr($desc, 300)) ?>">
    <?php if ($imageUrl !== ''): ?>
        <meta property="og:image" content="<?= h($imageUrl) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?= h($imageUrl) ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}</style>

</head>
<body class="bg-slate-50 min-h-screen">
    <main class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <header class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs text-slate-500">Pin Preview</p>
                    <h1 class="text-xl font-semibold text-slate-800 leading-snug"><?= h($title) ?></h1>
                </div>
                <div class="text-right space-y-2">
                    <p class="text-xs text-slate-500">Redirect in</p>
                    <p class="text-2xl font-bold text-red-600"><span id="countdown"><?= (int) $countdownStart ?></span>s</p>
                    <div class="flex flex-col items-end gap-2">
                        <a data-pin-do="buttonPin" href="<?= h($pinterestCreateUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition">
                            Create Pin on Pinterest
                        </a>
                        <button id="copyCaptionBtn" type="button" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold transition">
                            Copy Caption
                        </button>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600 leading-relaxed whitespace-pre-wrap"><?= h($descWithHashtags) ?></p>
        </header>

        <textarea id="captionText" class="sr-only" aria-hidden="true"><?= h($captionFull) ?></textarea>

        <?php if ($imageUrl !== ''): ?>
            <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <img src="<?= h($imageUrl) ?>" alt="<?= h($title) ?>" class="w-full h-auto object-cover">
            </section>
        <?php endif; ?>

        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">ClickBank Link</h2>
            <?php if ($affiliate !== ''): ?>
                <a id="affiliateBtn" href="<?= h($affiliate) ?>" rel="nofollow sponsored" class="inline-flex items-center justify-center w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition opacity-60 pointer-events-none">
                    Please wait for the countdown...
                </a>
                <p class="text-xs text-slate-500 mt-2">You will be redirected automatically when the countdown ends.</p>
            <?php else: ?>
                <p class="text-sm text-slate-600">Affiliate link is not available.</p>
            <?php endif; ?>
        </section>

        <footer class="text-center text-xs text-slate-500 py-4">
            URL: <?= h($previewUrl) ?>
        </footer>
    </main>

    <script>
    (function () {
        var remaining = <?= (int) $countdownStart ?>;
        var el = document.getElementById('countdown');
        var btn = document.getElementById('affiliateBtn');
        var copyBtn = document.getElementById('copyCaptionBtn');
        var captionEl = document.getElementById('captionText');
        if (!el) return;

        var copyCaption = function () {
            if (!captionEl) return;

            var text = captionEl.value || captionEl.textContent || '';
            var ok = false;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    ok = true;
                    if (copyBtn) {
                        var prev = copyBtn.textContent;
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function () {
                            copyBtn.textContent = prev;
                        }, 1500);
                    }
                }).catch(function () {
                    // fall back below
                });
                return;
            }

            // Fallback for older browsers
            try {
                captionEl.focus();
                captionEl.select();
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }

            if (ok && copyBtn) {
                var prev2 = copyBtn.textContent;
                copyBtn.textContent = 'Copied!';
                setTimeout(function () {
                    copyBtn.textContent = prev2;
                }, 1500);
            }
        };

        if (copyBtn) {
            copyBtn.addEventListener('click', copyCaption);
        }

        var tick = function () {
            remaining -= 1;
            if (remaining < 0) remaining = 0;
            el.textContent = String(remaining);

            if (remaining <= 0) {
                if (btn) {
                    btn.textContent = 'Open Affiliate Link';
                    btn.classList.remove('opacity-60', 'pointer-events-none');
                }
                if (btn && btn.href) {
                    window.location.href = btn.href;
                }
                return;
            }

            setTimeout(tick, 1000);
        };

        setTimeout(tick, 1000);
    })();
    </script>

    <script async defer src="https://assets.pinterest.com/js/pinit.js"></script>
</body>
</html>
