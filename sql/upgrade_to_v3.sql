-- ============================================================
--  UPGRADE: v2.2.1 (post-v10)  ->  v3.0.0
--  Idempotent: safe to run once, and safe to re-run.
--  Applies migrations v11 through v17 in one pass.
--  Run on the LIVE database (phpMyAdmin > SQL tab, or mysql CLI).
-- ============================================================

-- Guard helpers: add column / index / foreign key only if missing.
DROP PROCEDURE IF EXISTS _up_addcol;
DROP PROCEDURE IF EXISTS _up_addidx;
DROP PROCEDURE IF EXISTS _up_addfk;
DELIMITER $$

CREATE PROCEDURE _up_addcol(IN t VARCHAR(64), IN c VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c) THEN
    SET @s = CONCAT('ALTER TABLE `', t, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

CREATE PROCEDURE _up_addidx(IN t VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND INDEX_NAME = idx) THEN
    SET @s = CONCAT('ALTER TABLE `', t, '` ADD ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

CREATE PROCEDURE _up_addfk(IN t VARCHAR(64), IN fk VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t
                   AND CONSTRAINT_NAME = fk AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
    SET @s = CONCAT('ALTER TABLE `', t, '` ADD CONSTRAINT `', fk, '` ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

DELIMITER ;

-- ── New tables ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS product_subcategories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subcat (category_id, name),
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS customer_phones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_references (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    ref_user_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'staff/manager reference for sales tracking',
    name        VARCHAR(150) NOT NULL,
    address     VARCHAR(500) NULL DEFAULT NULL,
    phone       VARCHAR(20)  NULL DEFAULT NULL,
    photo       VARCHAR(255) NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (ref_user_id) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_ledger (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    entry_type     ENUM('goods','deposit','money_return','product_return',
                        'expense','due_transfer','opening') NOT NULL,
    entry_date     DATE NOT NULL,
    debit          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note           TEXT NULL,
    received_by    VARCHAR(150) NULL COMMENT 'money_return: who received the cash',
    status         ENUM('final','pending') NOT NULL DEFAULT 'final' COMMENT 'pending = draft memo',
    ref_table      VARCHAR(30) NULL,
    ref_id         INT UNSIGNED NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cust_date (customer_id, entry_date),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_ledger_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ledger_id      INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    product_name   VARCHAR(150) NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    unit           VARCHAR(30) NOT NULL DEFAULT '',
    unit_price     DECIMAL(12,2) NOT NULL,
    line_total     DECIMAL(14,2) NOT NULL,
    unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    INDEX idx_ledger (ledger_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (ledger_id)  REFERENCES customer_ledger(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_agreements (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agreement_no   VARCHAR(30) NOT NULL UNIQUE,
    customer_id    INT UNSIGNED NOT NULL,
    agreement_date DATE NOT NULL,
    total_amount   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deposit_method VARCHAR(100) NULL COMMENT 'e.g. bank / cash',
    note           TEXT NULL,
    status         ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_agreement_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agreement_id INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    unit         VARCHAR(30) NOT NULL DEFAULT '',
    unit_price   DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (agreement_id) REFERENCES purchase_agreements(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreement_deliveries (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agreement_id  INT UNSIGNED NOT NULL,
    delivery_date DATE NOT NULL,
    product_id    INT UNSIGNED NULL,
    product_name  VARCHAR(150) NOT NULL,
    quantity      DECIMAL(12,2) NOT NULL,
    unit          VARCHAR(30) NOT NULL DEFAULT '',
    note          VARCHAR(500) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agreement_id) REFERENCES purchase_agreements(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)            ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel    ENUM('sms','whatsapp','app') NOT NULL DEFAULT 'sms',
    recipient  VARCHAR(30) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    ref_table  VARCHAR(30) NULL,
    ref_id     INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS due_assignments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT UNSIGNED NOT NULL,
    staff_id      INT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    note          VARCHAR(500) NULL,
    status        ENUM('active','closed') NOT NULL DEFAULT 'active',
    assigned_by   INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assign_cust (customer_id, status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_tasks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    details     TEXT NULL,
    due_date    DATE NULL,
    status      ENUM('pending','done') NOT NULL DEFAULT 'pending',
    assigned_by INT UNSIGNED NULL,
    done_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_user (user_id, status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS low_stock_history (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    branch_id  INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = global stock alert',
    stock_qty  DECIMAL(12,2) NOT NULL,
    threshold  DECIMAL(12,2) NOT NULL,
    tier       ENUM('red','yellow') NOT NULL,
    alerted_on DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alert_day (product_id, branch_id, alerted_on),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── New columns / keys / FKs on existing tables (guarded) ──

-- products (v11)
CALL _up_addcol('products','subcategory_id', "subcategory_id INT UNSIGNED NULL DEFAULT NULL AFTER category_id");
CALL _up_addcol('products','product_code',   "product_code VARCHAR(50) NULL DEFAULT NULL AFTER name");
CALL _up_addcol('products','image',          "image VARCHAR(255) NULL DEFAULT NULL AFTER unit");
CALL _up_addcol('products','wholesale_price', "wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER sell_price");
UPDATE products SET product_code = CONCAT('P-', LPAD(id, 4, '0'))
 WHERE product_code IS NULL OR product_code = '';
CALL _up_addidx('products','uq_product_code', "UNIQUE KEY uq_product_code (product_code)");
CALL _up_addfk('products','fk_product_subcategory',
  "FOREIGN KEY (subcategory_id) REFERENCES product_subcategories(id) ON DELETE SET NULL");

-- customers (v12)
CALL _up_addcol('customers','whatsapp',     "whatsapp VARCHAR(20) NULL DEFAULT NULL AFTER phone");
CALL _up_addcol('customers','imo',          "imo VARCHAR(20) NULL DEFAULT NULL AFTER whatsapp");
CALL _up_addcol('customers','photo',        "photo VARCHAR(255) NULL DEFAULT NULL AFTER address");
CALL _up_addcol('customers','book_no',      "book_no VARCHAR(20) NULL DEFAULT NULL AFTER photo");
CALL _up_addcol('customers','account_no',   "account_no VARCHAR(30) NULL DEFAULT NULL AFTER book_no");
CALL _up_addcol('customers','account_type', "account_type ENUM('full','short') NOT NULL DEFAULT 'full' AFTER account_no");
CALL _up_addcol('customers','due_limit',    "due_limit DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER account_type");
CALL _up_addidx('customers','uq_account_no', "UNIQUE KEY uq_account_no (account_no)");

-- sales (v14)
CALL _up_addcol('sales','unload_bill',     "unload_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount");
CALL _up_addcol('sales','labor_bill',      "labor_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unload_bill");
CALL _up_addcol('sales','transport_bill',  "transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER labor_bill");
CALL _up_addcol('sales','delivery_charge', "delivery_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER transport_bill");
CALL _up_addcol('sales','discount_note',   "discount_note VARCHAR(300) NULL DEFAULT NULL AFTER delivery_charge");
CALL _up_addcol('sales','approved_by',     "approved_by INT UNSIGNED NULL DEFAULT NULL AFTER created_by");
CALL _up_addfk('sales','fk_sales_approver',
  "FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL");

-- sale_items (v14)
CALL _up_addcol('sale_items','rate_type',      "rate_type ENUM('retail','wholesale','custom') NOT NULL DEFAULT 'retail' AFTER unit_price");
CALL _up_addcol('sale_items','unload_bill',    "unload_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER rate_type");
CALL _up_addcol('sale_items','labor_bill',     "labor_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unload_bill");
CALL _up_addcol('sale_items','transport_bill', "transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER labor_bill");

-- stock_transfers (v15)
CALL _up_addcol('stock_transfers','status',           "status ENUM('pending','sent','received','returned') NOT NULL DEFAULT 'pending' AFTER quantity");
CALL _up_addcol('stock_transfers','transfer_date',    "transfer_date DATE NULL DEFAULT NULL AFTER status");
CALL _up_addcol('stock_transfers','customer_name',    "customer_name VARCHAR(150) NULL DEFAULT NULL AFTER transfer_date");
CALL _up_addcol('stock_transfers','customer_address', "customer_address VARCHAR(500) NULL DEFAULT NULL AFTER customer_name");
CALL _up_addcol('stock_transfers','customer_mobile',  "customer_mobile VARCHAR(20) NULL DEFAULT NULL AFTER customer_address");
CALL _up_addcol('stock_transfers','order_manager',    "order_manager VARCHAR(150) NULL DEFAULT NULL AFTER customer_mobile");
CALL _up_addcol('stock_transfers','driver_name',      "driver_name VARCHAR(150) NULL DEFAULT NULL AFTER order_manager");
CALL _up_addcol('stock_transfers','driver_mobile',    "driver_mobile VARCHAR(20) NULL DEFAULT NULL AFTER driver_name");
CALL _up_addcol('stock_transfers','return_note',      "return_note VARCHAR(500) NULL DEFAULT NULL AFTER driver_mobile");
CALL _up_addcol('stock_transfers','received_by',      "received_by INT UNSIGNED NULL DEFAULT NULL AFTER return_note");
CALL _up_addcol('stock_transfers','received_at',      "received_at DATETIME NULL DEFAULT NULL AFTER received_by");
CALL _up_addfk('stock_transfers','fk_transfer_receiver',
  "FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL");
-- Legacy rows (no transfer_date) were instant both-side moves -> received
UPDATE stock_transfers SET status = 'received', transfer_date = DATE(created_at)
 WHERE transfer_date IS NULL;
CALL _up_addidx('stock_transfers','idx_transfer_date',   "INDEX idx_transfer_date (transfer_date)");
CALL _up_addidx('stock_transfers','idx_transfer_status', "INDEX idx_transfer_status (status)");

-- customer_ledger (v16) — table created above
CALL _up_addcol('customer_ledger','collected_by', "collected_by INT UNSIGNED NULL DEFAULT NULL AFTER created_by");
CALL _up_addfk('customer_ledger','fk_ledger_collector',
  "FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL");

-- ── Seed per-branch product list ───────────────────────────

INSERT IGNORE INTO branch_products (branch_id, product_id)
SELECT b.id, p.id FROM branches b CROSS JOIN products p
WHERE b.is_active = 1 AND p.is_active = 1;
-- ── Rebuild views (final, need the new columns above) ──────
-- Some deployments accidentally have these as real TABLEs instead of
-- VIEWs (e.g. from an interrupted import). CREATE OR REPLACE VIEW
-- refuses to touch a table, so rename any stray table out of the way
-- first (renamed, never dropped — nothing is deleted).

DROP PROCEDURE IF EXISTS _up_movetable;
DELIMITER $$
CREATE PROCEDURE _up_movetable(IN v VARCHAR(64))
BEGIN
  DECLARE otype VARCHAR(20) DEFAULT NULL;
  SELECT TABLE_TYPE INTO otype FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v LIMIT 1;
  IF otype = 'BASE TABLE' THEN
    SET @bak = CONCAT(v, '_stray_table_backup');
    SET @drop_old = CONCAT('DROP TABLE IF EXISTS `', @bak, '`');
    PREPARE st1 FROM @drop_old; EXECUTE st1; DEALLOCATE PREPARE st1;
    SET @s = CONCAT('RENAME TABLE `', v, '` TO `', @bak, '`');
    PREPARE st2 FROM @s; EXECUTE st2; DEALLOCATE PREPARE st2;
  END IF;
END $$
DELIMITER ;

CALL _up_movetable('vw_current_stock');
CALL _up_movetable('vw_branch_stock');
CALL _up_movetable('vw_customer_ledger_balance');
CALL _up_movetable('vw_customer_dues');

DROP PROCEDURE IF EXISTS _up_movetable;

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

CREATE OR REPLACE VIEW vw_customer_ledger_balance AS
SELECT
    c.id   AS customer_id,
    c.name AS customer_name,
    COALESCE(SUM(l.debit),  0) AS total_debit,
    COALESCE(SUM(l.credit), 0) AS total_credit,
    COALESCE(SUM(l.debit),  0) - COALESCE(SUM(l.credit), 0) AS balance
FROM customers c
LEFT JOIN customer_ledger l
       ON l.customer_id = c.id AND l.status = 'final'
GROUP BY c.id;

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

-- Also rebuild vw_customer_dues (used by Customer/Payment/Report) in case
-- it was the same kind of stray table — moved aside above, recreated here
-- so nothing is left broken.
CREATE OR REPLACE VIEW vw_customer_dues AS
SELECT
    c.id   AS customer_id,
    c.name AS customer_name,
    c.phone,
    COALESCE(SUM(s.due_amount),   0) AS total_due,
    COALESCE(SUM(s.total_amount), 0) AS total_purchase,
    COALESCE(SUM(s.paid_amount),  0) AS total_paid
FROM   customers c
LEFT JOIN sales s ON s.customer_id = c.id AND s.status = 'completed'
GROUP BY c.id;

-- ── Cleanup ────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS _up_addcol;
DROP PROCEDURE IF EXISTS _up_addidx;
DROP PROCEDURE IF EXISTS _up_addfk;
