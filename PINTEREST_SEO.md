# Panduan & Template SEO Pinterest Indonesia (2025)

Dokumen ini berisi standar operasional prosedur (SOP) untuk membuat konten Pinterest yang viral, aman, dan sustainable untuk market Indonesia.

## 1. STRUKTUR KONTEN PINTEREST IDEAL

### A. Judul (Title)
Judul adalah penentu pertama apakah Pin diklik atau tidak.
*   **Panjang:** 40 - 70 Karakter (Optimal agar tidak terpotong di mobile).
*   **Formula:** `[Keyword Utama] + [Manfaat/Adjective] + [Hook Singkat]`
*   **Contoh:**
    *   *Salah:* Baju Bagus Murah
    *   *Benar:* Gamis Syari Modern Pastel - Simpel Buat Kondangan

### B. Deskripsi (Description)
Deskripsi bekerja untuk SEO search engine Pinterest.
*   **Panjang:** 300 - 500 Karakter.
*   **Struktur:**
    1.  **Opening:** Kalimat sapaan atau pertanyaan retoris yang relevan ("Lagi cari outfit lebaran?").
    2.  **Body:** Jelaskan fitur utama produk dengan bahasa natural. Masukkan keyword turunan di sini.
    3.  **Closing (CTA):** Ajakan bertindak yang halus, jangan mendesak ("Simpan pin ini", "Cek detail di sini").

### C. Keywords
Pinterest adalah Visual Search Engine. Keyword sangat krusial.
*   Gunakan 5-8 keyword.
*   Campurkan keyword umum (misal: "OOTD hijab") dan spesifik (misal: "rok plisket mocca").

---

## 2. TEMPLATE GENERATOR

Gunakan template prompt ini jika ingin generate manual via ChatGPT/Gemini:

**Template Judul:**
> "Buatkan 5 variasi judul Pinterest untuk produk {NAMA_PRODUK}. Panjang 40-70 karakter. Fokus pada manfaat {MANFAAT_UTAMA}. Bahasa Indonesia natural, tidak clickbait."

**Template Deskripsi:**
> "Buatkan deskripsi Pinterest untuk {NAMA_PRODUK}. Maksimal 500 karakter. Mulai dengan pertanyaan tentang {MASALAH_CUSTOMER}. Jelaskan solusi produk ini. Akhiri dengan ajakan halus cek link. Masukkan keyword: {KEYWORD_1}, {KEYWORD_2}."

---

## 3. REKOMENDASI BOARD (CONTOH)

Jangan campur aduk niche dalam satu board. Buatlah spesifik.

**Kategori Fashion:**
*   `OOTD Hijab Kekinian`
*   `Inspirasi Outfit Kampus`
*   `Style Kondangan Simple`

**Kategori Rumah Tangga:**
*   `Dekorasi Kamar Minimalis`
*   `Tips Dapur Rapi`
*   `Perabot Estetik Murah`

**Kategori Gadget/Aksesoris:**
*   `Aksesoris HP Lucu`
*   `Setup Meja Belajar`
*   `Rekomendasi Gadget 2025`

---

## 4. BEST PRACTICE & SAFETY RULES

Untuk menjaga akun tetap aman (Anti Banned/Shadowban):

1.  **Posting Interval:**
    *   Maksimal 15 Pin per hari.
    *   Beri jeda antar posting (jangan blast sekaligus).
2.  **Variasi Gambar:**
    *   Jangan upload gambar yang SAMA PERSIS berkali-kali ke URL yang sama.
    *   Ubah sedikit crop, tambah teks overlay, atau ganti background.
3.  **Variasi Teks:**
    *   Putar/Rotasi awalan kalimat (Hook). Jangan selalu mulai dengan "Dapatkan produk ini...".
    *   Gunakan sinonim.
4.  **Fresh URL:**
    *   Pinterest suka konten baru. Jika memungkinkan, arahkan ke halaman detail produk yang spesifik, bukan cuma home page.
5.  **Engagement:**
    *   Balas jika ada komen.
    *   Save juga pin orang lain ke board kita (prinsip 80% konten sendiri, 20% konten orang lain/repin).

---

## 5. ALUR SISTEM (Integrasi API)

Sistem `PinterestGenerator.php` yang telah dibuat bekerja dengan alur:
1.  Menerima data Produk (Nama, Deskripsi).
2.  Mengirim Prompt strict ke Google Gemini.
3.  Menerima output JSON berisi Judul, Deskripsi, dan Board.
4.  User tinggal copy-paste atau sistem otomatis posting (jika ada API Pinterest).
