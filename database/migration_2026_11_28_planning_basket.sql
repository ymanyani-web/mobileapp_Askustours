-- Extend the existing owner-scoped basket; keep all current entries and keys.
ALTER TABLE basket_items MODIFY item_type ENUM('driver','guide','planning') NOT NULL;
