/* ===========================================================================
   v18: Assistant Manager role (branch-scoped manager)

   An assistant manager is tied to exactly ONE branch and may only work with
   that branch's data (sales, stock, transfers, customers-by-transaction,
   dues/payments, quotations, installments, expenses, daily statement,
   reports, low-stock alerts). They can NOT touch the central product
   catalogue, branches, users, settings or backups.

   Two things are needed:
     1. the new role value on users.role
     2. branch_id on quotations / installment_plans, which had no branch
        column at all and therefore could not be scoped to a branch

   Safe to run more than once, and safe to run on a database that already
   got these changes from upgrade_to_v3.sql: every step checks first.

   Deliberately written with block comments and no DELIMITER / stored
   procedures. Line comments and DELIMITER both depend on real line breaks,
   and pasting SQL through a browser sometimes loses them — which turns the
   whole file into one comment, or breaks the procedure bodies. This form
   survives that.
   =========================================================================== */

/* 1. Role enum. MODIFY COLUMN restates the whole definition, so running it
      again simply sets it to what it already is. */
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','manager','assistant_manager','staff')
    NOT NULL DEFAULT 'staff';

/* 2a. quotations.branch_id */
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotations'
             AND COLUMN_NAME = 'branch_id');
SET @s := IF(@x = 0,
    'ALTER TABLE quotations ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER customer_id',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotations'
             AND INDEX_NAME = 'idx_quotation_branch');
SET @s := IF(@x = 0,
    'ALTER TABLE quotations ADD INDEX idx_quotation_branch (branch_id)',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotations'
             AND CONSTRAINT_NAME = 'fk_quotation_branch'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @s := IF(@x = 0,
    'ALTER TABLE quotations ADD CONSTRAINT fk_quotation_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

/* 2b. installment_plans.branch_id */
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installment_plans'
             AND COLUMN_NAME = 'branch_id');
SET @s := IF(@x = 0,
    'ALTER TABLE installment_plans ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER sale_id',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installment_plans'
             AND INDEX_NAME = 'idx_installment_branch');
SET @s := IF(@x = 0,
    'ALTER TABLE installment_plans ADD INDEX idx_installment_branch (branch_id)',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installment_plans'
             AND CONSTRAINT_NAME = 'fk_installment_branch'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @s := IF(@x = 0,
    'ALTER TABLE installment_plans ADD CONSTRAINT fk_installment_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

/* 3. Backfill: derive the branch from the linked sale where one exists, so
      existing rows are not invisible to branch-scoped users. Re-running is
      harmless because it only touches rows that are still NULL. */
UPDATE quotations q
JOIN sales s ON s.customer_id = q.customer_id
SET q.branch_id = s.branch_id
WHERE q.branch_id IS NULL AND q.customer_id IS NOT NULL AND s.branch_id IS NOT NULL;

UPDATE installment_plans ip
JOIN sales s ON s.id = ip.sale_id
SET ip.branch_id = s.branch_id
WHERE ip.branch_id IS NULL AND ip.sale_id IS NOT NULL AND s.branch_id IS NOT NULL;
