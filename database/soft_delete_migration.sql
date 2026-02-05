-- Soft delete support for core entities
-- Run this once on the production database.

ALTER TABLE categories ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE purchase_orders ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE goods_received_notes ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

CREATE INDEX idx_categories_deleted_at ON categories (deleted_at);
CREATE INDEX idx_products_deleted_at ON products (deleted_at);
CREATE INDEX idx_users_deleted_at ON users (deleted_at);
CREATE INDEX idx_purchase_orders_deleted_at ON purchase_orders (deleted_at);
CREATE INDEX idx_grn_deleted_at ON goods_received_notes (deleted_at);
