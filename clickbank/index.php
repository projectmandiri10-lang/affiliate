<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:,">
    <title>ClickBank Pinterest Pin Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen py-10 px-4">

    <div class="max-w-3xl mx-auto">
        
        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-slate-800 mb-2">Pinterest Pin Generator (ClickBank)</h1>
            <p class="text-slate-500">Generate Pinterest-ready SEO copy and a watermarked image in seconds.</p>
            <div class="mt-3 flex justify-center gap-2">
                <a href="pins.php" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 transition text-sm font-semibold">Uploads Gallery</a>
            </div>
        </div>

        <!-- Form Input -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 md:p-8 mb-8">
            <form id="generateForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Product / Offer Name</label>
                    <input type="text" id="productName" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="Example: At-Home Mobility Routine" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                    <select id="category" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                        <option value="Health & Fitness">Health & Fitness</option>
                        <option value="Self-Help">Self-Help</option>
                        <option value="Beauty">Beauty</option>
                        <option value="Relationships">Relationships</option>
                        <option value="Business & Marketing">Business & Marketing</option>
                        <option value="Personal Finance">Personal Finance</option>
                        <option value="Food & Cooking">Food & Cooking</option>
                        <option value="Home & Garden">Home & Garden</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Offer Description (paste from sales page)</label>
                    <textarea id="description" rows="5" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="Paste the offer description here..." required></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">ClickBank HopLink (Required)</label>
                        <input type="url" id="affiliateLink" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="https://example.hop.clickbank.net/?tid=..." required>
                        <p class="text-xs text-slate-500 mt-1">This link is shown on the preview page and used for the redirect.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Upload Offer Image (Required)</label>
                        <input type="file" id="productImage" accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition bg-white" required>
                        <p class="text-xs text-slate-500 mt-1">Formats: JPG, PNG, WebP (max 10MB). A “PROMO” watermark is added automatically.</p>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-4 rounded-lg transition shadow-md flex justify-center items-center gap-2">
                    <span>Generate Pinterest Copy & Watermark</span>
                </button>
            </form>
        </div>

        <!-- Loading State -->
        <div id="loading" class="hidden text-center py-10">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto mb-4"></div>
            <p class="text-slate-600 animate-pulse">Generating SEO copy and processing your image...</p>
        </div>

        <!-- Result Section -->
        <div id="result" class="hidden space-y-8">
            
            <!-- Source Badge -->
            <div id="sourceBadge" class="hidden bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded w-fit mx-auto">
                Content loaded from database cache
            </div>

            <!-- Image Result -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6 items-center">
                <div class="w-full md:w-1/2">
                    <h3 class="font-semibold text-slate-700 mb-3">Watermarked Image</h3>
                    <div class="w-full rounded-lg overflow-hidden border border-slate-200">
                        <img id="resultImage" src="" alt="Generated Pin" class="w-full h-auto object-cover">
                    </div>
                </div>
                <div class="w-full md:w-1/2 space-y-4">
                    <a id="downloadBtn" href="#" download class="block w-full text-center bg-slate-800 text-white py-3 rounded-lg hover:bg-slate-900 transition font-medium">
                        Download Image
                    </a>
                    
                    <div id="previewWrap" class="hidden">
                        <a id="previewBtn" href="#" target="_blank" rel="noopener" class="block w-full text-center bg-red-600 text-white py-3 rounded-lg hover:bg-red-700 transition font-medium">
                            Open Preview Page (Pin this URL)
                        </a>
                        <p class="text-xs text-slate-500 mt-2">Pinterest often performs better when you pin the preview page URL (not just the image).</p>
                    </div>

                    <div class="border-t border-slate-100 pt-4">
                        <h4 class="text-sm font-semibold text-slate-600 mb-2">ClickBank HopLink</h4>
                        <div class="flex gap-2">
                            <input type="text" id="resultLink" readonly class="text-sm w-full bg-slate-50 border border-slate-200 rounded px-3 py-2 text-slate-600">
                            <button onclick="copyTextValue('resultLink')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded text-sm font-medium transition">Copy</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-semibold text-slate-700">📌 Judul Pinterest</h3>
                        <button onclick="copyText('resultTitle')" class="text-xs text-red-600 hover:text-red-700 font-medium">Copy</button>
                    </div>
                    <div class="p-6">
                        <p id="resultTitle" class="text-slate-800 text-lg font-medium select-all"></p>
                        <p class="text-xs text-slate-400 mt-2">Length: <span id="titleCount">0</span> characters</p>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-semibold text-slate-700">SEO Description</h3>
                        <button onclick="copyText('resultDesc')" class="text-xs text-red-600 hover:text-red-700 font-medium">Copy</button>
                    </div>
                    <div class="p-6">
                        <p id="resultDesc" class="text-slate-600 leading-relaxed whitespace-pre-wrap select-all text-sm"></p>
                        <p class="text-xs text-slate-400 mt-2">Length: <span id="descCount">0</span> characters</p>
                    </div>
                </div>
            </div>

            <!-- Keywords & Boards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-200">
                        <h3 class="font-semibold text-slate-700">🔑 Keywords</h3>
                    </div>
                    <div class="p-6">
                        <div id="resultKeywords" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-200">
                        <h3 class="font-semibold text-slate-700">Recommended Boards</h3>
                    </div>
                    <div class="p-6">
                        <ul id="resultBoards" class="list-disc list-inside text-slate-600 space-y-1"></ul>
                    </div>
                </div>
            </div>

            <!-- Strategy -->
            <div class="bg-blue-50 rounded-xl border border-blue-100 p-6">
                <h3 class="text-blue-800 font-semibold mb-2">Content Strategy</h3>
                <p id="resultStrategy" class="text-blue-700 text-sm leading-relaxed"></p>
            </div>

        </div>

    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-5 right-5 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        Copied to clipboard!
    </div>

    <script>
        document.getElementById('generateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // UI States
            const btn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const resultDiv = document.getElementById('result');
            
            // Reset
            resultDiv.classList.add('hidden');
            loading.classList.remove('hidden');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            // Prepare Upload Data
            const formData = new FormData();
            formData.append('product_name', document.getElementById('productName').value);
            formData.append('category', document.getElementById('category').value);
            formData.append('description', document.getElementById('description').value);
            formData.append('affiliate_link', document.getElementById('affiliateLink').value);
            
            const imageFile = document.getElementById('productImage').files[0];
            if (imageFile) {
                formData.append('product_image', imageFile);
            }

            try {
                const qs = new URLSearchParams(window.location.search);
                const debug = qs.get('debug') === '1';
                const apiUrl = debug ? 'generate_pin_api.php?debug=1' : 'generate_pin_api.php';

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData // Fetch automatically sets Content-Type to multipart/form-data
                });

                // Some hosting errors return HTML (not JSON). Read text first for better debugging.
                const rawText = await response.text();
                let json;
                try {
                    json = JSON.parse(rawText);
                } catch (e) {
                    throw new Error('Server returned non-JSON response (HTTP ' + response.status + '):\n' + rawText);
                }

                if (response.ok && json.success) {
                    displayResult(json);
                } else {
                    const extra = json && json.phase ? ('\nPhase: ' + json.phase) : '';
                    alert('Error: ' + (json.error || ('HTTP ' + response.status)) + extra);
                    console.error('API error payload:', json);
                }

            } catch (error) {
                alert('Failed to reach the server: ' + error.message);
                console.error(error);
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });

        function displayResult(data) {
            const resultDiv = document.getElementById('result');
            const content = data.data;

            // Source Badge
            const badge = document.getElementById('sourceBadge');
            if (data.source.includes('database')) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }

            // Image Result
            const imgElement = document.getElementById('resultImage');
            const downloadBtn = document.getElementById('downloadBtn');
            if (data.image_url) {
                imgElement.src = data.image_url;
                downloadBtn.href = data.image_url;
                imgElement.parentElement.classList.remove('hidden');
            } else {
                imgElement.parentElement.classList.add('hidden'); // Hide if no image
            }

            // Preview Page Link (for pinning)
            const previewWrap = document.getElementById('previewWrap');
            const previewBtn = document.getElementById('previewBtn');
            if (data.preview_url) {
                previewBtn.href = data.preview_url;
                previewBtn.textContent = 'Open Preview Page (Pin this URL)';
                previewWrap.classList.remove('hidden');
            } else {
                previewWrap.classList.add('hidden');
            }

            // Affiliate Link
            document.getElementById('resultLink').value = data.affiliate_link || '';

            // Fill Content Text
            document.getElementById('resultTitle').textContent = content.pinterest_title;
            document.getElementById('titleCount').textContent = content.pinterest_title.length;

            document.getElementById('resultDesc').textContent = content.pinterest_description;
            document.getElementById('descCount').textContent = content.pinterest_description.length;

            // Keywords
            const keywordsContainer = document.getElementById('resultKeywords');
            keywordsContainer.innerHTML = '';
            content.keywords.forEach(kw => {
                const span = document.createElement('span');
                span.className = 'bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-sm border border-slate-200';
                span.textContent = kw;
                keywordsContainer.appendChild(span);
            });

            // Boards
            const boardsContainer = document.getElementById('resultBoards');
            boardsContainer.innerHTML = '';
            content.recommended_boards.forEach(board => {
                const li = document.createElement('li');
                li.textContent = board;
                boardsContainer.appendChild(li);
            });

            document.getElementById('resultStrategy').textContent = content.content_strategy;

            // Show Result
            resultDiv.classList.remove('hidden');
            resultDiv.scrollIntoView({ behavior: 'smooth' });
        }

        function copyText(elementId) {
            const text = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(text).then(() => {
                showToast();
            });
        }

        function copyTextValue(elementId) {
            const text = document.getElementById(elementId).value;
            navigator.clipboard.writeText(text).then(() => {
                showToast();
            });
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 2000);
        }
    </script>
</body>
</html>
