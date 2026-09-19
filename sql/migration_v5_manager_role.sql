-- v5: Add 'manager' role
-- Manager can access everything except user management and settings.

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff';
