USE askustours;
CREATE TABLE IF NOT EXISTS collaborators (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,collaborator_type ENUM('driver','hotel','transport_company','other') NOT NULL DEFAULT 'other',email VARCHAR(190),phone VARCHAR(40) NOT NULL,company_name VARCHAR(160),notes TEXT,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(collaborator_type),INDEX(phone));
ALTER TABLE bookings ADD COLUMN collaborator_id INT UNSIGNED NULL AFTER customer_id;
ALTER TABLE bookings ADD COLUMN source ENUM('admin','mobile') NOT NULL DEFAULT 'admin' AFTER item_id;
ALTER TABLE bookings ADD CONSTRAINT fk_booking_collaborator FOREIGN KEY (collaborator_id) REFERENCES collaborators(id) ON DELETE SET NULL;
ALTER TABLE bookings ADD INDEX(source);
