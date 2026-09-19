-- v10: Expense Tracking

CREATE TABLE IF NOT EXISTS expense_categories (
    id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)   NOT NULL,
    icon       VARCHAR(50)    NOT NULL DEFAULT 'bi-receipt',
    created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO expense_categories (id, name, icon) VALUES
(1, 'ভাড়া',        'bi-house-door'),
(2, 'বেতন',        'bi-person-badge'),
(3, 'বিদ্যুৎ বিল', 'bi-lightning-charge'),
(4, 'ইন্টারনেট',   'bi-wifi'),
(5, 'পরিবহন',      'bi-truck'),
(6, 'মেরামত',      'bi-tools'),
(7, 'বিবিধ',       'bi-three-dots');

CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED   DEFAULT NULL,
    branch_id    INT UNSIGNED   DEFAULT NULL,
    amount       DECIMAL(12,2)  NOT NULL,
    expense_date DATE           NOT NULL,
    description  TEXT,
    created_by   INT UNSIGNED   DEFAULT NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
