-- Restores the ordered home-banner source used by the dashboard and GET /v1/home.
-- Existing tables and records are preserved because this migration is additive.
CREATE TABLE IF NOT EXISTS banners (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(160) NOT NULL,
 subtitle VARCHAR(255),
 image_path VARCHAR(255),
 link_url VARCHAR(255),
 sort_order INT NOT NULL DEFAULT 0,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_banners_active_order(active,sort_order,id)
);
