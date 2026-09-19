-- v11: Product model upgrade (buyer requirements §2)
-- - Sub-categories
-- - Product image, product code (QR source), wholesale price
-- - Central catalog + per-branch pricing/threshold (branch_products)

-- 1. Sub-categories (belongs to a category)
CREATE TABLE IF NOT EXISTS product_subcategories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subcat (category_id, name),
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. New product fields
ALTER TABLE products
    ADD COLUMN subcategory_id  INT UNSIGNED  NULL DEFAULT NULL AFTER category_id,
    ADD COLUMN product_code    VARCHAR(50)   NULL DEFAULT NULL AFTER name,
    ADD COLUMN image           VARCHAR(255)  NULL DEFAULT NULL AFTER unit,
    ADD COLUMN wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER sell_price;

ALTER TABLE products
    ADD CONSTRAINT fk_product_subcategory
    FOREIGN KEY (subcategory_id) REFERENCES product_subcategories(id) ON DELETE SET NULL;

-- Auto-generate codes for existing products (P-0001 style)
UPDATE products SET product_code = CONCAT('P-', LPAD(id, 4, '0'))
WHERE product_code IS NULL OR product_code = '';

ALTER TABLE products ADD UNIQUE KEY uq_product_code (product_code);

-- 3. Per-branch product list: prices + alert threshold per branch
CREATE TABLE IF NOT EXISTS branch_products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id       INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    buy_price       DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'NULL = use central price',
    sell_price      DECIMAL(12,2) NULL DEFAULT NULL,
    wholesale_price DECIMAL(12,2) NULL DEFAULT NULL,
    min_stock       DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'NULL = use central threshold',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_product (branch_id, product_id),
    FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: every active branch sells every active product by default (central prices)
INSERT IGNORE INTO branch_products (branch_id, product_id)
SELECT b.id, p.id FROM branches b CROSS JOIN products p
WHERE b.is_active = 1 AND p.is_active = 1;

-- 4. Rebuild views with new fields (prices resolved per-branch via COALESCE)
CREATE OR REPLACE VIEW vw_current_stock AS
SELECT
    p.id          AS product_id,
    p.name        AS product_name,
    pc.name       AS product_type,
    p.category_id,
    p.subcategory_id,
    psc.name      AS subcategory_name,
    p.product_code,
    p.image,
    p.size_brand,
    p.unit,
    p.buy_price,
    p.sell_price,
    p.wholesale_price,
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
LEFT   JOIN product_subcategories psc ON psc.id = p.subcategory_id
WHERE  p.is_active = 1;

CREATE OR REPLACE VIEW vw_branch_stock AS
SELECT
    b.id    AS branch_id,
    b.name  AS branch_name,
    p.id    AS product_id,
    p.name  AS product_name,
    pc.name AS product_type,
    p.category_id,
    p.subcategory_id,
    psc.name AS subcategory_name,
    p.product_code,
    p.image,
    p.size_brand,
    p.unit,
    COALESCE(bp.buy_price,       p.buy_price)       AS buy_price,
    COALESCE(bp.sell_price,      p.sell_price)      AS sell_price,
    COALESCE(bp.wholesale_price, p.wholesale_price) AS wholesale_price,
    COALESCE(bp.min_stock,       p.min_stock)       AS min_stock,
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
LEFT   JOIN product_subcategories psc ON psc.id = p.subcategory_id
LEFT   JOIN branch_products bp ON bp.branch_id = b.id AND bp.product_id = p.id
WHERE  p.is_active = 1
  AND  b.is_active = 1;
