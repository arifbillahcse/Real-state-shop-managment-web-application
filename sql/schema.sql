-- ============================================
-- Rod & Cement Shop Management System
-- Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS rod_cement_shop
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE rod_cement_shop;

-- --------------------------------------------
-- 1. USERS TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    username    VARCHAR(50)   NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password: admin123)
INSERT INTO users (name, username, password, role)
VALUES ('Administrator', 'admin', '$2y$12$yZQDhvfj3dJa1R.kWuF4MeR5bNQmdZhN9JPTZ5bC7E4qiuF6fI.jW', 'admin');

-- --------------------------------------------
-- 2. SUPPLIERS TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    address     TEXT          DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 3. PRODUCTS TABLE (Rod & Cement)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type        ENUM('rod','cement') NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    size_brand  VARCHAR(100)  DEFAULT NULL COMMENT 'rod: size (8mm,10mm...), cement: brand name',
    unit        VARCHAR(30)   NOT NULL DEFAULT 'pcs' COMMENT 'ton, bag, pcs',
    buy_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sell_price  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    min_stock   DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'alert when stock falls below this',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample products
INSERT INTO products (type, name, size_brand, unit, buy_price, sell_price, min_stock) VALUES
('rod',    'Steel Rod 8mm',          '8mm',       'ton', 65000.00, 68000.00, 2),
('rod',    'Steel Rod 10mm',         '10mm',      'ton', 67000.00, 70000.00, 2),
('rod',    'Steel Rod 12mm',         '12mm',      'ton', 68000.00, 71000.00, 2),
('rod',    'Steel Rod 16mm',         '16mm',      'ton', 70000.00, 73000.00, 2),
('cement', 'Lafarge Cement',         'LAFARGE',   'bag',   480.00,   520.00, 50),
('cement', 'Holcim Cement',          'HOLCIM',    'bag',   475.00,   515.00, 50),
('cement', 'Heidelberg Cement',      'HEIDELBERG','bag',   470.00,   510.00, 50),
('cement', 'Shah Cement',            'SHAH',      'bag',   460.00,   500.00, 50);

-- --------------------------------------------
-- 4. STOCK INBOUND TABLE (Purchase)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS stock_inbound (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED  NOT NULL,
    supplier_id INT UNSIGNED  DEFAULT NULL,
    quantity    DECIMAL(12,2) NOT NULL,
    buy_price   DECIMAL(12,2) NOT NULL COMMENT 'price at the time of purchase',
    total_cost  DECIMAL(14,2) GENERATED ALWAYS AS (quantity * buy_price) STORED,
    inbound_date DATE         NOT NULL,
    note        TEXT          DEFAULT NULL,
    created_by  INT UNSIGNED  DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 5. CUSTOMERS TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    address     TEXT          DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Walk-in customer (default)
INSERT INTO customers (name, phone) VALUES ('Walk-in Customer', '0000000000');

-- --------------------------------------------
-- 6. SALES TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(30)   NOT NULL UNIQUE,
    customer_id     INT UNSIGNED  DEFAULT NULL,
    sale_date       DATE          NOT NULL,
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_amount      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_method  ENUM('cash','credit','cheque','mobile_banking') NOT NULL DEFAULT 'cash',
    status          ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    note            TEXT          DEFAULT NULL,
    created_by      INT UNSIGNED  DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 7. SALE ITEMS TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS sale_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id     INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    quantity    DECIMAL(12,2) NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(14,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id)    REFERENCES sales(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 8. PAYMENTS TABLE (Due collections)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED  NOT NULL,
    sale_id         INT UNSIGNED  DEFAULT NULL COMMENT 'specific sale or general payment',
    amount          DECIMAL(14,2) NOT NULL,
    payment_method  ENUM('cash','cheque','mobile_banking') NOT NULL DEFAULT 'cash',
    reference_no    VARCHAR(100)  DEFAULT NULL COMMENT 'cheque/mobile banking ref',
    payment_date    DATE          NOT NULL,
    note            TEXT          DEFAULT NULL,
    created_by      INT UNSIGNED  DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (sale_id)     REFERENCES sales(id)     ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 9. ACTIVITY LOG TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  DEFAULT NULL,
    action      VARCHAR(100)  NOT NULL COMMENT 'e.g. create_sale, delete_product',
    module      VARCHAR(50)   NOT NULL COMMENT 'e.g. sales, products, stock',
    reference_id INT UNSIGNED DEFAULT NULL COMMENT 'ID of the affected record',
    description TEXT          DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------
-- 10. SETTINGS TABLE
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100)  NOT NULL UNIQUE,
    setting_val TEXT          DEFAULT NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_val) VALUES
('shop_name',    'আমার রড সিমেন্ট ভান্ডার'),
('shop_address', 'ঢাকা, বাংলাদেশ'),
('shop_phone',   '01XXXXXXXXX'),
('shop_email',   'shop@example.com'),
('currency',     'BDT'),
('invoice_prefix','INV');

-- ============================================
-- USEFUL VIEWS
-- ============================================

-- Current stock per product (inbound - sold)
CREATE OR REPLACE VIEW vw_current_stock AS
SELECT
    p.id              AS product_id,
    p.name            AS product_name,
    p.type            AS product_type,
    p.size_brand,
    p.unit,
    p.buy_price,
    p.sell_price,
    p.min_stock,
    COALESCE(SUM(si.quantity), 0)                          AS total_inbound,
    COALESCE((
        SELECT SUM(sai.quantity)
        FROM   sale_items sai
        JOIN   sales s ON s.id = sai.sale_id
        WHERE  sai.product_id = p.id
        AND    s.status = 'completed'
    ), 0)                                                   AS total_sold,
    COALESCE(SUM(si.quantity), 0) - COALESCE((
        SELECT SUM(sai.quantity)
        FROM   sale_items sai
        JOIN   sales s ON s.id = sai.sale_id
        WHERE  sai.product_id = p.id
        AND    s.status = 'completed'
    ), 0)                                                   AS current_stock
FROM   products p
LEFT JOIN stock_inbound si ON si.product_id = p.id
WHERE  p.is_active = 1
GROUP BY p.id;

-- Customer outstanding dues
CREATE OR REPLACE VIEW vw_customer_dues AS
SELECT
    c.id            AS customer_id,
    c.name          AS customer_name,
    c.phone,
    COALESCE(SUM(s.due_amount), 0)                        AS total_due,
    COALESCE(SUM(s.total_amount), 0)                      AS total_purchase,
    COALESCE(SUM(s.paid_amount), 0)                       AS total_paid
FROM   customers c
LEFT JOIN sales s ON s.customer_id = c.id AND s.status = 'completed'
GROUP BY c.id;
