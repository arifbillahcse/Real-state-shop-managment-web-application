-- v16: Staff accountability + daily statement (buyer requirements §1, §8)
-- - Due assignment: a customer's due is handed to a staff member to collect
-- - Deposits auto-credit the assigned collector ("টাকা উত্তোলন" khata)
-- - Staff task assignment with status tracking
-- - Month-end collection ranking comes from customer_ledger.collected_by

ALTER TABLE customer_ledger
    ADD COLUMN collected_by INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'staff credited with collecting this deposit' AFTER created_by;

ALTER TABLE customer_ledger
    ADD CONSTRAINT fk_ledger_collector
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL;

-- Due collection assignments (হিসাব ট্রান্সফার)
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

-- Staff task assignment (§1)
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
