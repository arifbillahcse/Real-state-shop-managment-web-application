-- v15: Product transfer workflow (buyer requirements §3–4)
-- pending → sent → received | returned  (returned entries can be edited & re-sent)
-- Stock effect: source loses stock while 'sent' or 'received';
--               destination gains stock only when 'received'.

ALTER TABLE stock_transfers
    ADD COLUMN status ENUM('pending','sent','received','returned')
        NOT NULL DEFAULT 'pending' AFTER quantity,
    ADD COLUMN transfer_date    DATE         NULL DEFAULT NULL AFTER status,
    ADD COLUMN customer_name    VARCHAR(150) NULL DEFAULT NULL AFTER transfer_date,
    ADD COLUMN customer_address VARCHAR(500) NULL DEFAULT NULL AFTER customer_name,
    ADD COLUMN customer_mobile  VARCHAR(20)  NULL DEFAULT NULL AFTER customer_address,
    ADD COLUMN order_manager    VARCHAR(150) NULL DEFAULT NULL AFTER customer_mobile,
    ADD COLUMN driver_name      VARCHAR(150) NULL DEFAULT NULL AFTER order_manager,
    ADD COLUMN driver_mobile    VARCHAR(20)  NULL DEFAULT NULL AFTER driver_name,
    ADD COLUMN return_note      VARCHAR(500) NULL DEFAULT NULL AFTER driver_mobile,
    ADD COLUMN received_by      INT UNSIGNED NULL DEFAULT NULL AFTER return_note,
    ADD COLUMN received_at      DATETIME     NULL DEFAULT NULL AFTER received_by;

ALTER TABLE stock_transfers
    ADD CONSTRAINT fk_transfer_receiver
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL;

-- Legacy rows were instant both-side moves → mark as received
UPDATE stock_transfers SET status = 'received' WHERE status = 'pending';
UPDATE stock_transfers SET transfer_date = DATE(created_at) WHERE transfer_date IS NULL;

ALTER TABLE stock_transfers
    ADD INDEX idx_transfer_date (transfer_date),
    ADD INDEX idx_transfer_status (status);

-- Rebuild branch stock view with status-aware transfer math
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
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id AND st.status = 'received'), 0) AS total_transferred_in,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id AND st.status IN ('sent','received')), 0) AS total_transferred_out,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id AND st.status = 'received'), 0)
        - COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id AND st.status IN ('sent','received')), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0)
        AS current_stock
FROM   branches b
CROSS  JOIN products p
JOIN   product_categories pc ON pc.id = p.category_id
LEFT   JOIN product_subcategories psc ON psc.id = p.subcategory_id
LEFT   JOIN branch_products bp ON bp.branch_id = b.id AND bp.product_id = p.id
WHERE  p.is_active = 1
  AND  b.is_active = 1;
