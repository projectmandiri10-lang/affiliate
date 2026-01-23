CREATE TABLE IF NOT EXISTS generated_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    product_description TEXT,
    category VARCHAR(100),
    pinterest_title VARCHAR(255),
    pinterest_description TEXT,
    keywords TEXT, -- Stored as JSON or comma separated
    recommended_boards TEXT, -- Stored as JSON or comma separated
    strategy TEXT,
    affiliate_link TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_name (product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
