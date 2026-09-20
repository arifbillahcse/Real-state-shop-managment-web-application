-- v8: Standalone free-form notepad with pin + status
CREATE TABLE IF NOT EXISTS free_notes (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150)   NOT NULL,
    note          TEXT           NOT NULL,
    note_date     DATE           NOT NULL,
    author        VARCHAR(100)   NOT NULL DEFAULT '',
    is_pinned     TINYINT(1)     NOT NULL DEFAULT 0,
    status        ENUM('pending','done') NOT NULL DEFAULT 'pending',
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If table already exists from a previous run, add the new columns:
ALTER TABLE free_notes ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE free_notes ADD COLUMN IF NOT EXISTS status ENUM('pending','done') NOT NULL DEFAULT 'pending';
