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

function safeSubstr($s, $maxLen) {
    $s = (string) $s;
    $maxLen = (int) $maxLen;
    if ($maxLen <= 0) return '';
    if (function_exists('mb_substr')) return mb_substr($s, 0, $maxLen);
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

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

$pdo = getDbConnection();
$countStmt = $pdo->query("SELECT COUNT(*) AS c FROM generated_pins WHERE image_path IS NOT NULL AND image_path <> ''");
$total = (int) ($countStmt ? ($countStmt->fetch()['c'] ?? 0) : 0);

$stmt = $pdo->prepare("SELECT id, product_name, pinterest_title, pinterest_description, keywords, affiliate_link, image_path, created_at FROM generated_pins WHERE image_path IS NOT NULL AND image_path <> '' ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$base = baseUrl();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pins Gallery</title>
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <main class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Uploads Gallery</h1>
                <p class="text-sm text-slate-500">Open Preview to see the pin page (countdown + caption).</p>
            </div>
            <div class="flex gap-2 items-center">
                <a href="index.php" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 transition text-sm font-semibold">Generator</a>
            </div>
        </header>

        <section class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="text-sm font-semibold text-slate-800">Admin Key (Compress)</div>
                <div class="text-xs text-slate-500">Enter the key from `.env` (`COMPRESS_KEY`) to enable Compress and Delete buttons.</div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                <input id="compressKeyInput" type="password" placeholder="COMPRESS_KEY" class="w-full sm:w-72 px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-red-200">
                <div class="flex gap-2">
                    <button id="unlockCompressBtn" type="button" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition">Unlock</button>
                    <button id="lockCompressBtn" type="button" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-100 transition">Lock</button>
                </div>
                <div id="compressKeyStatus" class="text-xs text-slate-500 sm:text-right">Locked</div>
            </div>
        </section>

        <?php if (empty($rows)): ?>
            <div class="bg-white border border-slate-200 rounded-xl p-6 text-slate-600">Belum ada gambar yang diupload.</div>
        <?php else: ?>
            <section class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($rows as $r): ?>
                    <?php
                        $id = (int) $r['id'];
                        $imagePath = (string) $r['image_path'];
                        $imageUrl = $base . ltrim($imagePath, '/');
                        $previewUrl = $base . 'preview.php?id=' . urlencode((string) $id);
                        $editUrl = $base . 'edit_pin.php?id=' . urlencode((string) $id);

                        $title = (string) ($r['pinterest_title'] ?: $r['product_name']);
                        $desc = limitWords((string) ($r['pinterest_description'] ?? ''), 15);
                        $keywords = json_decode((string) ($r['keywords'] ?? ''), true);
                        if (!is_array($keywords)) $keywords = [];
                        $hashtags = hashtagify($keywords);

                        $captionFull = trim($title . "\n\n" . trim($desc . "\n\n" . implode(' ', $hashtags)));
                        $captionForPinterest = safeSubstr($captionFull, 650);
                        $params = [
                            'url' => $previewUrl,
                            'media' => $imageUrl,
                            'description' => $captionForPinterest
                        ];
                        $pinUrl = 'https://www.pinterest.com/pin/create/button/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
                    ?>
                    <article class="bg-white border border-slate-200 rounded-xl overflow-hidden" data-card-pin-id="<?= (int) $id ?>">
                        <a href="<?= h($previewUrl) ?>" target="_blank" rel="noopener">
                            <img id="thumb-<?= (int) $id ?>" src="<?= h($imageUrl) ?>" alt="<?= h($title) ?>" class="w-full aspect-square object-cover" loading="lazy">
                        </a>
                        <div class="p-2 space-y-2">
                            <div class="text-xs font-semibold text-slate-700 truncate" title="<?= h($title) ?>"><?= h($title) ?></div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="<?= h($editUrl) ?>" class="text-center px-2 py-1 rounded-lg bg-slate-800 text-white text-xs font-semibold hover:bg-slate-900 transition">Edit</a>
                                <a href="<?= h($previewUrl) ?>" target="_blank" rel="noopener" class="text-center px-2 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition">Preview</a>
                            </div>
                            <a data-pin-do="buttonPin" href="<?= h($pinUrl) ?>" target="_blank" rel="noopener noreferrer" class="block text-center px-2 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition">
                                Pin
                            </a>
                            <button type="button" data-pin-id="<?= (int) $id ?>" data-thumb-id="thumb-<?= (int) $id ?>" class="compressBtn block w-full text-center px-2 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Compress
                            </button>
                            <button type="button" data-pin-id="<?= (int) $id ?>" class="deleteBtn block w-full text-center px-2 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Delete
                            </button>
                            <div class="compressStatus text-[10px] leading-snug text-slate-500 min-h-[14px]"></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php
                $totalPages = (int) ceil($total / $perPage);
                if ($totalPages < 1) $totalPages = 1;
            ?>
            <nav class="flex items-center justify-between">
                <div class="text-xs text-slate-500">Page <?= (int) $page ?> / <?= (int) $totalPages ?> (<?= (int) $total ?> items)</div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm hover:bg-slate-100 transition" href="pins.php?page=<?= (int) ($page - 1) ?>">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm hover:bg-slate-100 transition" href="pins.php?page=<?= (int) ($page + 1) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </main>

    <script async defer src="https://assets.pinterest.com/js/pinit.js"></script>
    <script>
    (function(){
        function getKey() {
            try { return (sessionStorage.getItem('compress_key') || '').trim(); } catch (e) { return ''; }
        }
        function setKey(v) {
            try { sessionStorage.setItem('compress_key', (v || '').trim()); } catch (e) {}
        }
        function clearKey() {
            try { sessionStorage.removeItem('compress_key'); } catch (e) {}
        }
        function setStatus(text, ok) {
            var el = document.getElementById('compressKeyStatus');
            if (!el) return;
            el.textContent = text;
            el.className = 'text-xs ' + (ok ? 'text-emerald-600' : 'text-slate-500') + ' sm:text-right';
        }
        function refreshButtons() {
            var key = getKey();
            var buttons = document.querySelectorAll('.compressBtn');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = !key;
            }
            var delButtons = document.querySelectorAll('.deleteBtn');
            for (var j = 0; j < delButtons.length; j++) {
                delButtons[j].disabled = !key;
            }
            setStatus(key ? 'Unlocked' : 'Locked', !!key);
        }

        var unlockBtn = document.getElementById('unlockCompressBtn');
        var lockBtn = document.getElementById('lockCompressBtn');
        var keyInput = document.getElementById('compressKeyInput');

        if (unlockBtn && keyInput) {
            unlockBtn.addEventListener('click', function(){
                var v = (keyInput.value || '').trim();
                if (!v) {
                    setStatus('Key is empty', false);
                    return;
                }
                setKey(v);
                keyInput.value = '';
                refreshButtons();
            });
        }
        if (lockBtn) {
            lockBtn.addEventListener('click', function(){
                clearKey();
                refreshButtons();
            });
        }

        async function compressPin(pinId, thumbId, btn, statusEl) {
            var key = getKey();
            if (!key) {
                if (statusEl) statusEl.textContent = 'Please unlock first.';
                return;
            }

            btn.disabled = true;
            var oldText = btn.textContent;
            btn.textContent = 'Compressing...';
            if (statusEl) statusEl.textContent = '';

            try {
                var res = await fetch('compress_image_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Compress-Key': key
                    },
                    body: JSON.stringify({ pin_id: pinId })
                });

                var txt = await res.text();
                var data = null;
                try { data = JSON.parse(txt); } catch (e) {}

                if (!data || typeof data !== 'object') {
                    throw new Error('Non-JSON response (HTTP ' + res.status + ')');
                }

                if (!data.success) {
                    throw new Error(data.error || 'Compress failed');
                }

                var replaced = !!data.replaced;
                var stats = data.stats || {};
                var savedPct = (typeof stats.saved_percent === 'number') ? stats.saved_percent : null;
                var savedBytes = (typeof stats.saved_bytes === 'number') ? stats.saved_bytes : null;
                var reason = data.reason || '';

                var msg = '';
                if (replaced) {
                    msg = 'Saved ' + formatBytes(savedBytes) + ' (' + savedPct + '%)';
                } else {
                    msg = reason ? ('No change: ' + reason) : 'No change';
                }
                if (statusEl) statusEl.textContent = msg;

                if (replaced && thumbId) {
                    var img = document.getElementById(thumbId);
                    if (img) {
                        var baseSrc = (img.getAttribute('src') || '').split('?')[0];
                        var v = (data.cache_bust || Date.now());
                        img.setAttribute('src', baseSrc + '?v=' + encodeURIComponent(String(v)));
                    }
                }
            } catch (err) {
                if (statusEl) statusEl.textContent = (err && err.message) ? err.message : 'Error';
            } finally {
                btn.textContent = oldText;
                refreshButtons();
            }
        }

        async function deletePin(pinId, btn, statusEl) {
            var key = getKey();
            if (!key) {
                if (statusEl) statusEl.textContent = 'Please unlock first.';
                return;
            }

            var ok = confirm("Delete this pin?\nThe preview page will 404 and the hosted image will be deleted.");
            if (!ok) return;

            btn.disabled = true;
            var oldText = btn.textContent;
            btn.textContent = 'Deleting...';
            if (statusEl) statusEl.textContent = '';

            try {
                var res = await fetch('delete_pin_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Compress-Key': key
                    },
                    body: JSON.stringify({ pin_id: pinId })
                });

                var txt = await res.text();
                var data = null;
                try { data = JSON.parse(txt); } catch (e) {}

                if (!data || typeof data !== 'object') {
                    throw new Error('Non-JSON response (HTTP ' + res.status + ')');
                }
                if (!data.success) {
                    throw new Error(data.error || 'Delete failed');
                }

                var card = document.querySelector('[data-card-pin-id=\"' + String(pinId) + '\"]');
                if (card && card.parentNode) {
                    card.parentNode.removeChild(card);
                }
            } catch (err) {
                btn.textContent = oldText;
                refreshButtons();
                if (statusEl) statusEl.textContent = (err && err.message) ? err.message : 'Error';
                return;
            }
        }

        function formatBytes(b) {
            b = (typeof b === 'number') ? b : 0;
            if (b <= 0) return '0B';
            var units = ['B','KB','MB','GB'];
            var i = 0;
            while (b >= 1024 && i < units.length - 1) {
                b = b / 1024;
                i++;
            }
            var n = (i === 0) ? Math.round(b) : Math.round(b * 10) / 10;
            return String(n) + units[i];
        }

        document.addEventListener('click', function(e){
            var t = e.target;
            if (!t || !t.classList) return;

            if (t.classList.contains('compressBtn')) {
                var pinId = parseInt(t.getAttribute('data-pin-id') || '0', 10);
                var thumbId = t.getAttribute('data-thumb-id') || '';
                var statusEl = t.parentNode ? t.parentNode.querySelector('.compressStatus') : null;
                if (pinId > 0) compressPin(pinId, thumbId, t, statusEl);
                return;
            }

            if (t.classList.contains('deleteBtn')) {
                var delId = parseInt(t.getAttribute('data-pin-id') || '0', 10);
                var statusEl2 = t.parentNode ? t.parentNode.querySelector('.compressStatus') : null;
                if (delId > 0) deletePin(delId, t, statusEl2);
                return;
            }
        });

        refreshButtons();
    })();
    </script>
</body>
</html>
