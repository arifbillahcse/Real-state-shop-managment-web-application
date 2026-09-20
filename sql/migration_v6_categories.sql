-- v6: Dynamic Product Categories
-- Run AFTER migration_v5_manager_role.sql
-- Replaces the hardcoded ENUM('rod','cement') with a flexible categories table.

CREATE TABLE IF NOT EXISTS product_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default categories matching existing ENUM values
INSERT IGNORE INTO product_categories (name) VALUES ('রড'), ('সিমেন্ট');

-- Add category_id column next to old type column
ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL AFTER id;

-- Migrate existing rows
UPDATE products
    SET category_id = (SELECT id FROM product_categories WHERE name = 'রড'     LIMIT 1)
WHERE type = 'rod';
UPDATE products
    SET category_id = (SELECT id FROM product_categories WHERE name = 'সিমেন্ট' LIMIT 1)
WHERE type = 'cement';

-- Enforce NOT NULL + FK
ALTER TABLE products MODIFY COLUMN category_id INT UNSIGNED NOT NULL;
ALTER TABLE products
    ADD CONSTRAINT fk_product_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE RESTRICT;

-- Drop old ENUM column
ALTER TABLE products DROP COLUMN type;

-- ── Rebuild views to use category name ────────────────────────────────────────

CREATE OR REPLACE VIEW vw_current_stock AS
SELECT
    p.id          AS product_id,
    p.name        AS product_name,
    pc.name       AS product_type,
    p.category_id,
    p.size_brand,
    p.unit,
    p.buy_price,
    p.sell_price,
    p.min_stock,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id), 0) AS total_inbound,
    COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.status = 'completed'), 0) AS total_sold,
    COALESCE((SELECT SUM(sa.quantity)  FROM stock_adjustments sa WHERE sa.product_id = p.id), 0) AS total_adjustments,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.status = 'completed'), 0)
        AS current_stock
FROM   products p
JOIN   product_categories pc ON pc.id = p.category_id
WHERE  p.is_active = 1;

CREATE OR REPLACE VIEW vw_branch_stock AS
SELECT
    b.id    AS branch_id,
    b.name  AS branch_name,
    p.id    AS product_id,
    p.name  AS product_name,
    pc.name AS product_type,
    p.category_id,
    p.size_brand,
    p.unit,
    p.buy_price,
    p.sell_price,
    p.min_stock,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0) AS total_inbound,
    COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0) AS total_sold,
    COALESCE((SELECT SUM(sa.quantity)  FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0) AS total_adjustments,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id), 0) AS total_transferred_in,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id), 0) AS total_transferred_out,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id), 0)
        - COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0)
        AS current_stock
FROM   branches b
CROSS  JOIN products p
JOIN   product_categories pc ON pc.id = p.category_id
WHERE  p.is_active = 1
  AND  b.is_active = 1;
