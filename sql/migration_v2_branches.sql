-- ============================================
-- Migration v2: Branch System
-- Run this on an existing database.
-- schema.sql already includes these for fresh installs.
-- ============================================

-- 1. Branches table
CREATE TABLE IF NOT EXISTS branches (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    address     TEXT          DEFAULT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add branch_id to users (staff can be tied to a branch)
ALTER TABLE users
    ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER role;

-- 3. Add branch_id to stock_inbound (stock belongs to a branch)
ALTER TABLE stock_inbound
    ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER supplier_id;

-- 4. Add branch_id to sales (order fulfilled from which branch)
ALTER TABLE sales
    ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER customer_id;

-- 5. Per-branch stock view
CREATE OR REPLACE VIEW vw_branch_stock AS
SELECT
    b.id              AS branch_id,
    b.name            AS branch_name,
    p.id              AS product_id,
    p.name            AS product_name,
    p.type            AS product_type,
    p.size_brand,
    p.unit,
    p.buy_price,
    p.sell_price,
    p.min_stock,
    COALESCE(SUM(si.quantity), 0)                           AS total_inbound,
    COALESCE((
        SELECT SUM(sai.quantity)
        FROM   sale_items sai
        JOIN   sales s ON s.id = sai.sale_id
        WHERE  sai.product_id = p.id
          AND  s.branch_id    = b.id
          AND  s.status       = 'completed'
    ), 0)                                                   AS total_sold,
    COALESCE(SUM(si.quantity), 0) - COALESCE((
        SELECT SUM(sai.quantity)
        FROM   sale_items sai
        JOIN   sales s ON s.id = sai.sale_id
        WHERE  sai.product_id = p.id
          AND  s.branch_id    = b.id
          AND  s.status       = 'completed'
    ), 0)                                                   AS current_stock
FROM   branches b
CROSS JOIN products p
LEFT  JOIN stock_inbound si
       ON  si.product_id = p.id
       AND si.branch_id  = b.id
WHERE  p.is_active = 1
  AND  b.is_active = 1
GROUP BY b.id, p.id;
