# Affiliate Pre-Landing Page

Project Next.js untuk landing page affiliate yang aman, natural, dan compliant.

## Struktur Project

- `pages/p/[slug].js`: Halaman utama landing page. Menangani logika redirect dan tampilan konten.
- `data/products.json`: Database sederhana untuk produk.
- `next.config.js`: Konfigurasi untuk Static Site Generation (SSG).

## Cara Penggunaan

### 1. Instalasi

Pastikan Node.js sudah terinstall, lalu jalankan:

```bash
npm install
```

### 2. Development

Untuk menjalankan di local server:

```bash
npm run dev
```
Buka browser di `http://localhost:3000/p/tas-kulit-premium` (sesuaikan dengan slug di products.json).

### 3. Menambah Produk

Edit file `data/products.json`. Format:

```json
[
  {
    "slug": "url-slug-produk",
    "title": "Judul Produk",
    "description": "Deskripsi...",
    "affiliate_url": "https://link-affiliate-anda.com"
  }
]
```

### 4. Build & Export (Static HTML)

Untuk menghasilkan file statis (HTML/CSS/JS) di folder `out`:

```bash
npm run build
```
Karena `next.config.js` sudah diset `output: 'export'`, perintah ini akan otomatis menghasilkan static export.

### 5. Deploy ke Vercel

Project ini siap deploy ke Vercel.

1. Push kode ke GitHub/GitLab/Bitbucket.
2. Import project di dashboard Vercel.
3. Vercel akan otomatis mendeteksi Next.js.
4. Framework Preset: **Next.js**.
5. Build Command: `next build`.
6. Output Directory: `out` (atau default Vercel).
7. Klik **Deploy**.

## Logika Redirect

Halaman ini menerapkan logika "Safe Redirect":
1. **Timer Acak**: 18-25 detik (ditentukan saat load).
2. **Scroll Check**: User harus scroll minimal 60% dari halaman.
3. **Trigger**: Redirect otomatis hanya jalan jika Waktu Habis AND Scroll Tercapai.
4. **Manual**: Tombol CTA selalu tersedia.

## Keamanan

- Tidak menggunakan back-button hijacking.
- Tidak memanipulasi history browser.
- Redirect murni client-side (`window.location.href`).
- User experience diutamakan (konten asli, waktu baca cukup).
