-- v8: Standalone free-form notepad (no FK constraints)
CREATE TABLE IF NOT EXISTS free_notes (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150)   NOT NULL,
    note          TEXT           NOT NULL,
    note_date     DATE           NOT NULL,
    author        VARCHAR(100)   NOT NULL DEFAULT '',
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
