-- v12: Customer profile expansion (buyer requirements §5)
-- - WhatsApp / Imo numbers, photo
-- - Book No + auto-generated Account No (serial per book)
-- - Full / Short account types
-- - Extra mobile numbers, multiple references (with photo)
-- - Per-customer due limit (used by sale credit warning)

ALTER TABLE customers
    ADD COLUMN whatsapp     VARCHAR(20)   NULL DEFAULT NULL AFTER phone,
    ADD COLUMN imo          VARCHAR(20)   NULL DEFAULT NULL AFTER whatsapp,
    ADD COLUMN photo        VARCHAR(255)  NULL DEFAULT NULL AFTER address,
    ADD COLUMN book_no      VARCHAR(20)   NULL DEFAULT NULL AFTER photo,
    ADD COLUMN account_no   VARCHAR(30)   NULL DEFAULT NULL AFTER book_no,
    ADD COLUMN account_type ENUM('full','short') NOT NULL DEFAULT 'full' AFTER account_no,
    ADD COLUMN due_limit    DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT '0 = no limit' AFTER account_type;

ALTER TABLE customers ADD UNIQUE KEY uq_account_no (account_no);

-- Extra phone numbers (searchable)
CREATE TABLE IF NOT EXISTS customer_phones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- References (multiple per customer)
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
