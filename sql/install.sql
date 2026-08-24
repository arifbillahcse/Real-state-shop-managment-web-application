-- ---------------------------------------------------------------------------
-- FRESH INSTALL ONLY.
--
-- Run this against an EMPTY database. To update a database that is already
-- live and holding data, run sql/upgrade_to_v3.sql instead — this file does
-- not alter existing tables, so it cannot bring an older schema up to date.
--
-- Seed rows use INSERT IGNORE, so re-running this file is harmless: it will
-- skip rows that already exist rather than aborting on a duplicate key.
-- ---------------------------------------------------------------------------
-- ============================================================
--  Rod & Cement / Shop Management System
--  Complete Install — Single File (v3.0.0)
--  Every table below is in its FINAL form (no ALTER statements
--  needed) — reusable as a clean starting schema for any similar
--  shop/inventory/customer-ledger project.
--  Run once on a fresh MySQL 5.7+ / MariaDB 10.3+ database.
-- ============================================================

CREATE DATABASE IF NOT EXISTS rod_cement_shop
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE rod_cement_shop;

-- ============================================================
--  TABLES
-- ============================================================

-- 1. Branches
CREATE TABLE IF NOT EXISTS branches (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    address    TEXT         DEFAULT NULL,
    phone      VARCHAR(20)  DEFAULT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users (roles: admin / manager / staff)
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','manager','assistant_manager','staff') NOT NULL DEFAULT 'staff',
    branch_id  INT UNSIGNED DEFAULT NULL COMMENT 'required for staff + assistant_manager — the branch they are tied to',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account (username: admin, password: admin123 — CHANGE AFTER FIRST LOGIN)
INSERT IGNORE INTO users (name, username, password, role)
VALUES ('Administrator', 'admin', '$2y$12$wgUtvV291cMFFRxEd3gKYuz0EjZECg1RqywX49pKfTkkEFpHV/WEe', 'admin');

-- 3. Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(20)  DEFAULT NULL,
    address    TEXT         DEFAULT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Product Categories
CREATE TABLE IF NOT EXISTS product_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO product_categories (name) VALUES ('রড'), ('সিমেন্ট');

-- 5. Product Sub-categories
CREATE TABLE IF NOT EXISTS product_subcategories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subcat (category_id, name),
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Products (central catalog — per-branch price overrides live in branch_products)
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED  NOT NULL,
    subcategory_id  INT UNSIGNED  NULL DEFAULT NULL,
    name            VARCHAR(150)  NOT NULL,
    product_code    VARCHAR(50)   NULL DEFAULT NULL,
    size_brand      VARCHAR(100)  DEFAULT NULL,
    unit            VARCHAR(30)   NOT NULL DEFAULT 'pcs',
    image           VARCHAR(255)  NULL DEFAULT NULL,
    buy_price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sell_price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    min_stock       DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'alert when stock falls below this',
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_code (product_code),
    CONSTRAINT fk_product_category FOREIGN KEY (category_id)
        REFERENCES product_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_subcategory FOREIGN KEY (subcategory_id)
        REFERENCES product_subcategories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample products
INSERT IGNORE INTO products (category_id, name, product_code, size_brand, unit, buy_price, sell_price, min_stock) VALUES
((SELECT id FROM product_categories WHERE name='রড'),       'Steel Rod 8mm',     'P-0001', '8mm',       'ton', 65000.00, 68000.00, 2),
((SELECT id FROM product_categories WHERE name='রড'),       'Steel Rod 10mm',    'P-0002', '10mm',      'ton', 67000.00, 70000.00, 2),
((SELECT id FROM product_categories WHERE name='রড'),       'Steel Rod 12mm',    'P-0003', '12mm',      'ton', 68000.00, 71000.00, 2),
((SELECT id FROM product_categories WHERE name='রড'),       'Steel Rod 16mm',    'P-0004', '16mm',      'ton', 70000.00, 73000.00, 2),
((SELECT id FROM product_categories WHERE name='সিমেন্ট'), 'Lafarge Cement',    'P-0005', 'LAFARGE',   'bag',   480.00,   520.00, 50),
((SELECT id FROM product_categories WHERE name='সিমেন্ট'), 'Holcim Cement',     'P-0006', 'HOLCIM',    'bag',   475.00,   515.00, 50),
((SELECT id FROM product_categories WHERE name='সিমেন্ট'), 'Heidelberg Cement', 'P-0007', 'HEIDELBERG','bag',   470.00,   510.00, 50),
((SELECT id FROM product_categories WHERE name='সিমেন্ট'), 'Shah Cement',       'P-0008', 'SHAH',      'bag',   460.00,   500.00, 50);

-- 7. Per-branch product list: price/threshold overrides (NULL = use central value)
CREATE TABLE IF NOT EXISTS branch_products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id       INT UNSIGNED  NOT NULL,
    product_id      INT UNSIGNED  NOT NULL,
    buy_price       DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'NULL = use central price',
    sell_price      DECIMAL(12,2) NULL DEFAULT NULL,
    wholesale_price DECIMAL(12,2) NULL DEFAULT NULL,
    min_stock       DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'NULL = use central threshold',
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_product (branch_id, product_id),
    FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Stock Inbound / Purchases
CREATE TABLE IF NOT EXISTS stock_inbound (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    product_id   INT UNSIGNED  NOT NULL,
    supplier_id  INT UNSIGNED  DEFAULT NULL,
    branch_id    INT UNSIGNED  DEFAULT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    buy_price    DECIMAL(12,2) NOT NULL,
    total_cost   DECIMAL(14,2) GENERATED ALWAYS AS (quantity * buy_price) STORED,
    inbound_date DATE          NOT NULL,
    note         TEXT          DEFAULT NULL,
    created_by   INT UNSIGNED  DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id)   REFERENCES branches(id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Stock Adjustments (manual +/- corrections)
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED  NOT NULL,
    branch_id  INT UNSIGNED  DEFAULT NULL,
    quantity   DECIMAL(12,2) NOT NULL COMMENT 'positive=add, negative=subtract',
    reason     ENUM('damage','count_correction','return','other') NOT NULL DEFAULT 'other',
    note       TEXT          DEFAULT NULL,
    created_by INT UNSIGNED  DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Stock Transfers — branch-to-branch workflow
--     pending -> sent -> received | returned (returned can be edited & re-sent)
CREATE TABLE IF NOT EXISTS stock_transfers (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    product_id       INT UNSIGNED  NOT NULL,
    from_branch_id   INT UNSIGNED  NOT NULL,
    to_branch_id     INT UNSIGNED  NOT NULL,
    quantity         DECIMAL(12,2) NOT NULL,
    status           ENUM('pending','sent','received','returned') NOT NULL DEFAULT 'pending',
    transfer_date    DATE          NULL DEFAULT NULL,
    customer_name    VARCHAR(150)  NULL DEFAULT NULL,
    customer_address VARCHAR(500)  NULL DEFAULT NULL,
    customer_mobile  VARCHAR(20)   NULL DEFAULT NULL,
    order_manager    VARCHAR(150)  NULL DEFAULT NULL,
    driver_name      VARCHAR(150)  NULL DEFAULT NULL,
    driver_mobile    VARCHAR(20)   NULL DEFAULT NULL,
    return_note      VARCHAR(500)  NULL DEFAULT NULL,
    received_by      INT UNSIGNED  NULL DEFAULT NULL,
    received_at      DATETIME      NULL DEFAULT NULL,
    note             TEXT          DEFAULT NULL,
    created_by       INT UNSIGNED  DEFAULT NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer_date (transfer_date),
    INDEX idx_transfer_status (status),
    FOREIGN KEY (product_id)     REFERENCES products(id),
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id)   REFERENCES branches(id),
    FOREIGN KEY (received_by)    REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Customers
CREATE TABLE IF NOT EXISTS customers (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150)  NOT NULL,
    phone        VARCHAR(20)   DEFAULT NULL,
    whatsapp     VARCHAR(20)   NULL DEFAULT NULL,
    imo          VARCHAR(20)   NULL DEFAULT NULL,
    address      TEXT          DEFAULT NULL,
    photo        VARCHAR(255)  NULL DEFAULT NULL,
    book_no      VARCHAR(20)   NULL DEFAULT NULL,
    account_no   VARCHAR(30)   NULL DEFAULT NULL,
    account_type ENUM('full','short') NOT NULL DEFAULT 'full',
    due_limit    DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '0 = no limit',
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_no (account_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO customers (name, phone) VALUES ('Walk-in Customer', '0000000000');

-- 12. Customer extra phone numbers (searchable)
CREATE TABLE IF NOT EXISTS customer_phones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Customer references (multiple, optionally linked to a staff/manager)
CREATE TABLE IF NOT EXISTS customer_references (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    ref_user_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'staff/manager reference for sales tracking',
    name        VARCHAR(150) NOT NULL,
    address     VARCHAR(500) NULL DEFAULT NULL,
    phone       VARCHAR(20)  NULL DEFAULT NULL,
    photo       VARCHAR(255) NULL DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (ref_user_id) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Customer account notes / remarks
CREATE TABLE IF NOT EXISTS customer_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    note        TEXT         NOT NULL,
    created_by  INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Sales (invoices)
CREATE TABLE IF NOT EXISTS sales (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    invoice_number   VARCHAR(30)   NOT NULL UNIQUE,
    customer_id      INT UNSIGNED  DEFAULT NULL,
    branch_id        INT UNSIGNED  DEFAULT NULL,
    sale_date        DATE          NOT NULL,
    subtotal         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    unload_bill      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    delivery_charge  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_note    VARCHAR(300)  NULL DEFAULT NULL,
    total_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_amount       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    previous_due     DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'balance shown on the memo; not part of this sale',
    payment_method   ENUM('cash','credit','cheque','mobile_banking') NOT NULL DEFAULT 'cash',
    status           ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    note             TEXT          DEFAULT NULL,
    sold_by_name     VARCHAR(150)  NULL DEFAULT NULL COMMENT 'employee who made the sale (as printed)',
    sold_by_mobile   VARCHAR(20)   NULL DEFAULT NULL,
    created_by       INT UNSIGNED  DEFAULT NULL,
    approved_by      INT UNSIGNED  NULL DEFAULT NULL COMMENT 'manager who approved an over-limit credit sale',
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id)   REFERENCES branches(id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Sale Items
CREATE TABLE IF NOT EXISTS sale_items (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    sale_id        INT UNSIGNED  NOT NULL,
    product_id     INT UNSIGNED  NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    rate_type      ENUM('retail','wholesale','custom') NOT NULL DEFAULT 'retail',
    unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_price    DECIMAL(14,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id)    REFERENCES sales(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Payments (due collections against invoices)
CREATE TABLE IF NOT EXISTS payments (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED  NOT NULL,
    sale_id        INT UNSIGNED  DEFAULT NULL,
    amount         DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash','cheque','mobile_banking') NOT NULL DEFAULT 'cash',
    reference_no   VARCHAR(100)  DEFAULT NULL,
    payment_date   DATE          NOT NULL,
    note           TEXT          DEFAULT NULL,
    created_by     INT UNSIGNED  DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (sale_id)     REFERENCES sales(id)     ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Activity Log
CREATE TABLE IF NOT EXISTS activity_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED DEFAULT NULL,
    action       VARCHAR(100) NOT NULL,
    module       VARCHAR(50)  NOT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    description  TEXT         DEFAULT NULL,
    ip_address   VARCHAR(45)  DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Settings (key/value store)
CREATE TABLE IF NOT EXISTS settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_val TEXT         DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_val) VALUES
('shop_name',     'আমার রড সিমেন্ট ভান্ডার'),
('shop_address',  'ঢাকা, বাংলাদেশ'),
('shop_phone',    '01XXXXXXXXX'),
('shop_email',    'shop@example.com'),
('currency',      'BDT'),
('invoice_prefix','INV'),
('sms_gateway_url', ''),
('sms_api_key',     ''),
('alert_phone',     '');

-- 20. Free Notes / Notepad
CREATE TABLE IF NOT EXISTS free_notes (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150)  NOT NULL,
    note          TEXT          NOT NULL,
    note_date     DATE          NOT NULL,
    author        VARCHAR(100)  NOT NULL DEFAULT '',
    is_pinned     TINYINT(1)    NOT NULL DEFAULT 0,
    status        ENUM('pending','done') NOT NULL DEFAULT 'pending',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Quotations
CREATE TABLE IF NOT EXISTS quotations (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    quote_number  VARCHAR(30)   NOT NULL,
    customer_name VARCHAR(150)  NOT NULL DEFAULT '',
    customer_id   INT UNSIGNED  DEFAULT NULL,
    branch_id     INT UNSIGNED  DEFAULT NULL,
    quote_date    DATE          NOT NULL,
    valid_days    INT           NOT NULL DEFAULT 7,
    subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status        ENUM('active','converted','cancelled') NOT NULL DEFAULT 'active',
    note          TEXT,
    created_by    INT UNSIGNED  DEFAULT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_quotation_branch (branch_id),
    CONSTRAINT fk_quotation_branch FOREIGN KEY (branch_id)
        REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT UNSIGNED  NOT NULL,
    product_id   INT UNSIGNED  NOT NULL,
    product_name VARCHAR(150)  NOT NULL DEFAULT '',
    quantity     DECIMAL(12,2) NOT NULL,
    unit_price   DECIMAL(12,2) NOT NULL,
    total_price  DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Sale Returns
CREATE TABLE IF NOT EXISTS sale_returns (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    sale_id      INT UNSIGNED  NOT NULL,
    return_date  DATE          NOT NULL,
    reason       VARCHAR(500)  NOT NULL DEFAULT '',
    total_refund DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note         TEXT,
    created_by   INT UNSIGNED  DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_return_items (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    return_id     INT UNSIGNED  NOT NULL,
    product_id    INT UNSIGNED  NOT NULL,
    product_name  VARCHAR(150)  NOT NULL DEFAULT '',
    quantity      DECIMAL(12,2) NOT NULL,
    unit_price    DECIMAL(12,2) NOT NULL,
    refund_amount DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES sale_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Installment Plans
CREATE TABLE IF NOT EXISTS installment_plans (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_name      VARCHAR(150)  NOT NULL,
    customer_id        INT UNSIGNED  DEFAULT NULL,
    sale_id            INT UNSIGNED  DEFAULT NULL,
    branch_id          INT UNSIGNED  DEFAULT NULL,
    total_amount       DECIMAL(12,2) NOT NULL,
    down_payment       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    installment_count  INT           NOT NULL,
    installment_amount DECIMAL(12,2) NOT NULL,
    start_date         DATE          NOT NULL,
    status             ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    note               TEXT,
    created_by         INT UNSIGNED  DEFAULT NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_installment_branch (branch_id),
    CONSTRAINT fk_installment_branch FOREIGN KEY (branch_id)
        REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installments (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    plan_id        INT UNSIGNED  NOT NULL,
    installment_no INT           NOT NULL,
    due_date       DATE          NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    paid_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_date      DATE          DEFAULT NULL,
    status         ENUM('pending','paid','overdue') NOT NULL DEFAULT 'pending',
    note           VARCHAR(500)  DEFAULT NULL,
    FOREIGN KEY (plan_id) REFERENCES installment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Expense Categories
CREATE TABLE IF NOT EXISTS expense_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    icon       VARCHAR(50)  NOT NULL DEFAULT 'bi-receipt',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO expense_categories (id, name, icon) VALUES
(1, 'ভাড়া',        'bi-house-door'),
(2, 'বেতন',        'bi-person-badge'),
(3, 'বিদ্যুৎ বিল', 'bi-lightning-charge'),
(4, 'ইন্টারনেট',   'bi-wifi'),
(5, 'পরিবহন',      'bi-truck'),
(6, 'মেরামত',      'bi-tools'),
(7, 'বিবিধ',       'bi-three-dots');

-- 25. Expenses
CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED  DEFAULT NULL,
    branch_id    INT UNSIGNED  DEFAULT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    expense_date DATE          NOT NULL,
    description  TEXT,
    created_by   INT UNSIGNED  DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. Customer Ledger (khata) — single source of truth for customer accounts.
--     balance = SUM(debit) - SUM(credit), always computed, never stored.
--     Final entries are immutable; corrections are new offsetting entries.
CREATE TABLE IF NOT EXISTS customer_ledger (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED  NOT NULL,
    entry_type     ENUM('goods','deposit','money_return','product_return',
                        'expense','due_transfer','opening') NOT NULL,
    entry_date     DATE          NOT NULL,
    debit          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note           TEXT          NULL,
    received_by    VARCHAR(150)  NULL COMMENT 'money_return: who received the cash',
    status         ENUM('final','pending') NOT NULL DEFAULT 'final' COMMENT 'pending = draft memo',
    ref_table      VARCHAR(30)   NULL,
    ref_id         INT UNSIGNED  NULL,
    created_by     INT UNSIGNED  NULL,
    collected_by   INT UNSIGNED  NULL DEFAULT NULL COMMENT 'staff credited with collecting this deposit',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cust_date (customer_id, entry_date),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id)  REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)   REFERENCES users(id)     ON DELETE SET NULL,
    FOREIGN KEY (collected_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. Ledger item lines (goods / product_return entries)
CREATE TABLE IF NOT EXISTS customer_ledger_items (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    ledger_id      INT UNSIGNED  NOT NULL,
    product_id     INT UNSIGNED  NULL,
    product_name   VARCHAR(150)  NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    unit           VARCHAR(30)   NOT NULL DEFAULT '',
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

-- 28. Advance purchase agreements / deeds
CREATE TABLE IF NOT EXISTS purchase_agreements (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    agreement_no   VARCHAR(30)   NOT NULL UNIQUE,
    customer_id    INT UNSIGNED  NOT NULL,
    agreement_date DATE          NOT NULL,
    total_amount   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deposit_method VARCHAR(100)  NULL COMMENT 'e.g. bank / cash',
    note           TEXT          NULL,
    status         ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    created_by     INT UNSIGNED  NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_agreement_items (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    agreement_id INT UNSIGNED  NOT NULL,
    product_id   INT UNSIGNED  NULL,
    product_name VARCHAR(150)  NOT NULL,
    quantity     DECIMAL(12,2) NOT NULL,
    unit         VARCHAR(30)   NOT NULL DEFAULT '',
    unit_price   DECIMAL(12,2) NOT NULL,
    line_total   DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (agreement_id) REFERENCES purchase_agreements(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. Delivery tracking sheet against an agreement
CREATE TABLE IF NOT EXISTS agreement_deliveries (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    agreement_id  INT UNSIGNED  NOT NULL,
    delivery_date DATE          NOT NULL,
    product_id    INT UNSIGNED  NULL,
    product_name  VARCHAR(150)  NOT NULL,
    quantity      DECIMAL(12,2) NOT NULL,
    unit          VARCHAR(30)   NOT NULL DEFAULT '',
    note          VARCHAR(500)  NULL,
    created_by    INT UNSIGNED  NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agreement_id) REFERENCES purchase_agreements(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)            ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. Notification log (SMS/WhatsApp gateway pluggable via settings)
CREATE TABLE IF NOT EXISTS notification_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel    ENUM('sms','whatsapp','app') NOT NULL DEFAULT 'sms',
    recipient  VARCHAR(30)  NOT NULL,
    message    TEXT         NOT NULL,
    status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    ref_table  VARCHAR(30)  NULL,
    ref_id     INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 31. Due collection assignments (হিসাব ট্রান্সফার — customer due handed to staff)
CREATE TABLE IF NOT EXISTS due_assignments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT UNSIGNED NOT NULL,
    staff_id      INT UNSIGNED NOT NULL,
    assigned_date DATE         NOT NULL,
    note          VARCHAR(500) NULL,
    status        ENUM('active','closed') NOT NULL DEFAULT 'active',
    assigned_by   INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assign_cust (customer_id, status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 32. Staff task assignment
CREATE TABLE IF NOT EXISTS staff_tasks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    details     TEXT         NULL,
    due_date    DATE         NULL,
    status      ENUM('pending','done') NOT NULL DEFAULT 'pending',
    assigned_by INT UNSIGNED NULL,
    done_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_user (user_id, status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33. Low stock alert history (one row per product/branch/day sighting)
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

-- Seed: every active branch sells every active product by default (central prices)
INSERT IGNORE INTO branch_products (branch_id, product_id)
SELECT b.id, p.id FROM branches b CROSS JOIN products p
WHERE b.is_active = 1 AND p.is_active = 1;

-- ============================================================
--  VIEWS  (final versions)
-- ============================================================

-- Global stock per product (inbound + adjustments − sold)
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

-- Per-branch stock, status-aware transfer math
-- (source loses stock while sent/received; destination gains only when received)
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

-- Customer outstanding dues (invoice-based)
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

-- Customer ledger (khata) running balance — final entries only
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

-- ---------------------------------------------------------------------------
-- Migration tracking. The app applies new sql/migration_v*.sql files itself
-- (see classes/Migrator.php); these rows tell it everything up to here is
-- already part of this schema, so it only ever runs genuinely new ones.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    INT UNSIGNED NOT NULL PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version, name) VALUES
    (2, 'branches'),
    (4, 'stock_features'),
    (5, 'manager_role'),
    (6, 'categories'),
    (7, 'customer_notes'),
    (8, 'notes'),
    (9, 'sales_features'),
    (10, 'expenses'),
    (11, 'product_model'),
    (12, 'customer_profile'),
    (13, 'customer_ledger'),
    (14, 'sales_upgrade'),
    (15, 'transfer_workflow'),
    (16, 'staff_statement'),
    (17, 'low_stock_alerts'),
    (18, 'assistant_manager'),
    (19, 'sale_salesperson'),
    (20, 'sale_previous_due');
