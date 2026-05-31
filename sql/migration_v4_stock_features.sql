-- stock_adjustments: manual +/- corrections
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    branch_id  INT UNSIGNED DEFAULT NULL,
    quantity   DECIMAL(12,2) NOT NULL COMMENT 'positive=add, negative=subtract',
    reason     ENUM('damage','count_correction','return','other') NOT NULL DEFAULT 'other',
    note       TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- stock_transfers: branch-to-branch moves
CREATE TABLE IF NOT EXISTS stock_transfers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id     INT UNSIGNED NOT NULL,
    from_branch_id INT UNSIGNED NOT NULL,
    to_branch_id   INT UNSIGNED NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    note           TEXT DEFAULT NULL,
    created_by     INT UNSIGNED DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)     REFERENCES products(id),
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id)   REFERENCES branches(id),
    FOREIGN KEY (created_by)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- vw_current_stock: add adjustments to global formula
CREATE OR REPLACE VIEW vw_current_stock AS
SELECT
    p.id AS product_id, p.name AS product_name, p.type AS product_type,
    p.size_brand, p.unit, p.buy_price, p.sell_price, p.min_stock,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id), 0) AS total_inbound,
    COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.status = 'completed'), 0) AS total_sold,
    COALESCE((SELECT SUM(sa.quantity)  FROM stock_adjustments sa WHERE sa.product_id = p.id), 0) AS total_adjustments,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.status = 'completed'), 0)
        AS current_stock
FROM products p WHERE p.is_active = 1;

-- vw_branch_stock: add adjustments + transfers to per-branch formula
CREATE OR REPLACE VIEW vw_branch_stock AS
SELECT
    b.id AS branch_id, b.name AS branch_name,
    p.id AS product_id, p.name AS product_name, p.type AS product_type,
    p.size_brand, p.unit, p.buy_price, p.sell_price, p.min_stock,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0) AS total_inbound,
    COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0) AS total_sold,
    COALESCE((SELECT SUM(sa.quantity)  FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0) AS total_adjustments,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id = b.id), 0) AS total_transferred_in,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id), 0) AS total_transferred_out,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id = b.id), 0)
        - COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0)
        AS current_stock
FROM branches b CROSS JOIN products p
WHERE p.is_active = 1 AND b.is_active = 1;
