-- v13: Customer ledger / account system (buyer requirements §5–6)
-- Balance convention: debit increases what the customer owes the shop,
-- credit decreases it. balance = SUM(debit) - SUM(credit).
-- Positive balance = customer owes; negative = customer has advance.

CREATE TABLE IF NOT EXISTS customer_ledger (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    entry_type     ENUM('goods','deposit','money_return','product_return',
                        'expense','due_transfer','opening') NOT NULL,
    entry_date     DATE NOT NULL,
    debit          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    -- Combined (एकত্রে) charges for goods entries; per-item charges live on items
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

-- Item lines for goods / product_return entries
CREATE TABLE IF NOT EXISTS customer_ledger_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ledger_id      INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    product_name   VARCHAR(150) NOT NULL,
    quantity       DECIMAL(12,2) NOT NULL,
    unit           VARCHAR(30) NOT NULL DEFAULT '',
    unit_price     DECIMAL(12,2) NOT NULL,
    line_total     DECIMAL(14,2) NOT NULL,
    -- Per-item (পণ্যভিত্তিক) charges; 0 when charges are combined on the entry
    unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    INDEX idx_ledger (ledger_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (ledger_id)  REFERENCES customer_ledger(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advance purchase agreements / deeds (§6.8)
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

-- Delivery tracking sheet against an agreement (2nd page of the deed)
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

-- Notification log (SMS/WhatsApp gateway pluggable later — §5 deposit SMS, §9 alerts)
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

-- Customer balance view (running totals; previous due = balance before a date)
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
