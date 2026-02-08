ALTER TABLE generated_pins
ADD COLUMN redirect_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER affiliate_link;
