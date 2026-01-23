# Checklist Pengembangan V3

- [ ] **Database Update**: Tambah kolom `affiliate_link` dan `image_path` ke tabel `generated_pins`.
- [ ] **Backend Image Processing**:
    - [ ] Buat class `ImageProcessor.php` menggunakan GD Library.
    - [ ] Fungsi upload gambar.
    - [ ] Fungsi resize/optimize gambar (optional tapi recommended).
    - [ ] Fungsi tambah watermark text "PROMO" di pojok atau tengah.
- [ ] **Backend API**:
    - [ ] Update `generate_pin_api.php` untuk terima file upload (`$_FILES`).
    - [ ] Update validasi input.
    - [ ] Simpan path gambar dan link ke database.
    - [ ] Return URL gambar hasil watermark ke frontend.
- [ ] **Frontend (index.php)**:
    - [ ] Tambah input file upload image.
    - [ ] Tambah input text affiliate link.
    - [ ] Tampilkan preview gambar sebelum upload (JS).
    - [ ] Tampilkan hasil gambar yang sudah di-watermark di bagian Result.
