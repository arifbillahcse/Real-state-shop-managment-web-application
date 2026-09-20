-- v17: Low Stock Alert Center (buyer requirements §9)
-- History of every day a product appeared on the alert list —
-- used to see which products run out most often.

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
