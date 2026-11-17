-- Separates the small "Ofertas destacadas" cards from the large home slides.
-- Safe to run repeatedly. Existing banner rows remain unchanged.
CREATE TABLE IF NOT EXISTS home_offers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(160) NOT NULL,
 subtitle VARCHAR(255),
 image_path VARCHAR(255),
 link_url VARCHAR(255),
 sort_order INT NOT NULL DEFAULT 0,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_home_offers_active_order(active,sort_order,id)
);
