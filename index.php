<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinterest SEO Generator Indonesia</title>
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
            <h1 class="text-3xl font-bold text-slate-800 mb-2">Pinterest SEO Generator 🇮🇩</h1>
            <p class="text-slate-500">Buat konten Pinterest viral & SEO friendly dalam hitungan detik.</p>
        </div>

        <!-- Form Input -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 md:p-8 mb-8">
            <form id="generateForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Nama Produk (Opsional jika Auto-Scrape aktif)</label>
                    <input type="text" id="productName" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="Contoh: Gamis Rayon Premium">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Kategori</label>
                    <select id="category" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                        <option value="Fashion Muslim">Fashion Muslim</option>
                        <option value="Fashion Wanita">Fashion Wanita</option>
                        <option value="Fashion Pria">Fashion Pria</option>
                        <option value="Kesehatan & Kecantikan">Kesehatan & Kecantikan</option>
                        <option value="Ibu & Bayi">Ibu & Bayi</option>
                        <option value="Rumah Tangga">Rumah Tangga</option>
                        <option value="Elektronik">Elektronik</option>
                        <option value="Makanan & Minuman">Makanan & Minuman</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Deskripsi Produk (Opsional jika Auto-Scrape aktif)</label>
                    <textarea id="description" rows="5" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="Paste deskripsi produk di sini..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Link Affiliate Shopee (Wajib)</label>
                        <input type="url" id="affiliateLink" class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="https://shope.ee/..." required>
                        
                        <div class="mt-3 flex items-center gap-2">
                            <input type="checkbox" id="autoScrape" class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                            <label for="autoScrape" class="text-sm text-slate-600">
                                🤖 Auto-Scrape data produk dari Shopee (Nama, Deskripsi, Gambar)
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Upload Gambar Produk (Opsional jika Auto-Scrape aktif)</label>
                        <input type="file" id="productImage" accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition bg-white">
                        <p class="text-xs text-slate-500 mt-1">Format: JPG, PNG. Watermark PROMO akan otomatis ditambahkan.</p>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-4 rounded-lg transition shadow-md flex justify-center items-center gap-2">
                    <span>✨ Generate Konten Pinterest & Watermark</span>
                </button>
            </form>
        </div>

        <!-- Loading State -->
        <div id="loading" class="hidden text-center py-10">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto mb-4"></div>
            <p class="text-slate-600 animate-pulse">Sedang meracik konten SEO & memproses gambar...</p>
        </div>

        <!-- Result Section -->
        <div id="result" class="hidden space-y-8">
            
            <!-- Source Badge -->
            <div id="sourceBadge" class="hidden bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded w-fit mx-auto">
                Content Loaded from Database Cache ⚡
            </div>

            <!-- Image Result -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row gap-6 items-center">
                <div class="w-full md:w-1/2">
                    <h3 class="font-semibold text-slate-700 mb-3">🖼️ Hasil Gambar (Watermarked)</h3>
                    <div class="w-full rounded-lg overflow-hidden border border-slate-200">
                        <img id="resultImage" src="" alt="Generated Pin" class="w-full h-auto object-cover">
                    </div>
                </div>
                <div class="w-full md:w-1/2 space-y-4">
                    <a id="downloadBtn" href="#" download class="block w-full text-center bg-slate-800 text-white py-3 rounded-lg hover:bg-slate-900 transition font-medium">
                        ⬇️ Download Gambar
                    </a>
                    
                    <div class="border-t border-slate-100 pt-4">
                        <h4 class="text-sm font-semibold text-slate-600 mb-2">🔗 Affiliate Link</h4>
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
                        <p class="text-xs text-slate-400 mt-2">Panjang: <span id="titleCount">0</span> karakter</p>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-semibold text-slate-700">📝 Deskripsi SEO</h3>
                        <button onclick="copyText('resultDesc')" class="text-xs text-red-600 hover:text-red-700 font-medium">Copy</button>
                    </div>
                    <div class="p-6">
                        <p id="resultDesc" class="text-slate-600 leading-relaxed whitespace-pre-wrap select-all text-sm"></p>
                        <p class="text-xs text-slate-400 mt-2">Panjang: <span id="descCount">0</span> karakter</p>
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
                        <h3 class="font-semibold text-slate-700">📂 Rekomendasi Board</h3>
                    </div>
                    <div class="p-6">
                        <ul id="resultBoards" class="list-disc list-inside text-slate-600 space-y-1"></ul>
                    </div>
                </div>
            </div>

            <!-- Strategy -->
            <div class="bg-blue-50 rounded-xl border border-blue-100 p-6">
                <h3 class="text-blue-800 font-semibold mb-2">💡 Strategi Konten</h3>
                <p id="resultStrategy" class="text-blue-700 text-sm leading-relaxed"></p>
            </div>

        </div>

    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-5 right-5 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        Copied to clipboard! 📋
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
            formData.append('auto_scrape', document.getElementById('autoScrape').checked ? 'true' : 'false');
            
            const imageFile = document.getElementById('productImage').files[0];
            if (imageFile) {
                formData.append('product_image', imageFile);
            }

            try {
                const response = await fetch('generate_pin_api.php', {
                    method: 'POST',
                    body: formData // Fetch automatically sets Content-Type to multipart/form-data
                });

                const json = await response.json();

                if (json.success) {
                    displayResult(json);
                } else {
                    alert('Error: ' + (json.error || 'Terjadi kesalahan sistem'));
                }

            } catch (error) {
                alert('Gagal menghubungi server: ' + error.message);
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
