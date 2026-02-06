# V3 Development Checklist

- [ ] **Database Update**: Add `affiliate_link` and `image_path` columns to `generated_pins`.
- [ ] **Backend Image Processing**:
    - [ ] Create `ImageProcessor.php` using the GD library.
    - [ ] Image upload handling.
    - [ ] Image resize/optimization (optional but recommended).
    - [ ] Add a “PROMO” text watermark (corner or center).
- [ ] **Backend API**:
    - [ ] Update `generate_pin_api.php` to accept file uploads (`$_FILES`).
    - [ ] Update input validation.
    - [ ] Save the image path and affiliate link to the database.
    - [ ] Return the watermarked image URL to the frontend.
- [ ] **Frontend (`index.php`)**:
    - [ ] Add an image file upload input.
    - [ ] Add an affiliate link input.
    - [ ] Show an image preview before upload (JS).
    - [ ] Show the generated/watermarked image in the Results section.
