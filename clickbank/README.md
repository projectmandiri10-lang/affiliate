# ClickBank Pinterest Pin Generator (English)

This folder is a ClickBank-focused duplicate of the main project, with:
- English UI text
- An English-only Gemini prompt tuned for ClickBank-style offers
- Affiliate link validation (requires a valid `https://` URL)

## Setup

1. Create `clickbank/.env` based on `clickbank/.env.example`.
2. Create the database tables using `clickbank/database.sql` (and/or `clickbank/migration_v3.sql` if needed).
3. Host the `clickbank/` folder on a PHP server (Apache/Nginx) with:
   - PHP + `pdo_mysql`, `curl`, `gd`

Open `clickbank/index.php` to use the generator.

