ALTER TABLE generated_pins
ADD COLUMN content_language VARCHAR(5) NOT NULL DEFAULT 'en' AFTER strategy;
