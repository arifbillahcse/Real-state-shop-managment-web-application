-- v9: Quotations, Sale Returns, Installment Plans

-- ── Quotations ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quotations (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    quote_number  VARCHAR(30)    NOT NULL,
    customer_name VARCHAR(150)   NOT NULL DEFAULT '',
    customer_id   INT UNSIGNED   DEFAULT NULL,
    quote_date    DATE           NOT NULL,
    valid_days    INT            NOT NULL DEFAULT 7,
    subtotal      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    discount      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_amount  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    status        ENUM('active','converted','cancelled') NOT NULL DEFAULT 'active',
    note          TEXT,
    created_by    INT UNSIGNED   DEFAULT NULL,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    quotation_id   INT UNSIGNED  NOT NULL,
    product_id     INT UNSIGNED  NOT NULL,
    product_name   VARCHAR(150)  NOT NULL DEFAULT '',
    quantity       DECIMAL(12,2) NOT NULL,
    unit_price     DECIMAL(12,2) NOT NULL,
    total_price    DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sale Returns ──────────────────────────────────────────────────────────────
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

-- ── Installment Plans ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS installment_plans (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    customer_name      VARCHAR(150)  NOT NULL,
    customer_id        INT UNSIGNED  DEFAULT NULL,
    sale_id            INT UNSIGNED  DEFAULT NULL,
    total_amount       DECIMAL(12,2) NOT NULL,
    down_payment       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    installment_count  INT           NOT NULL,
    installment_amount DECIMAL(12,2) NOT NULL,
    start_date         DATE          NOT NULL,
    status             ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    note               TEXT,
    created_by         INT UNSIGNED  DEFAULT NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
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
