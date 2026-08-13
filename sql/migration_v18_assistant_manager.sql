-- v18: Assistant Manager role (branch-scoped manager)
--
-- An assistant manager is tied to exactly ONE branch and may only work with
-- that branch's data (sales, stock, transfers, customers-by-transaction,
-- dues/payments, quotations, installments, expenses, daily statement,
-- reports, low-stock alerts). They can NOT touch the central product
-- catalogue, branches, users, settings or backups.
--
-- Two things are needed:
--   1. the new role value on users.role
--   2. branch_id on quotations / installment_plans, which had no branch
--      column at all and therefore could not be scoped to a branch

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','manager','assistant_manager','staff')
    NOT NULL DEFAULT 'staff';

-- Quotations: which branch issued this quotation
ALTER TABLE quotations
    ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER customer_id;

ALTER TABLE quotations
    ADD CONSTRAINT fk_quotation_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

ALTER TABLE quotations ADD INDEX idx_quotation_branch (branch_id);

-- Installment plans: which branch owns this plan
ALTER TABLE installment_plans
    ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER sale_id;

ALTER TABLE installment_plans
    ADD CONSTRAINT fk_installment_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

ALTER TABLE installment_plans ADD INDEX idx_installment_branch (branch_id);

-- Backfill: derive the branch from the linked sale where one exists, so
-- existing rows are not invisible to branch-scoped users.
UPDATE quotations q
JOIN sales s ON s.customer_id = q.customer_id
SET q.branch_id = s.branch_id
WHERE q.branch_id IS NULL AND q.customer_id IS NOT NULL AND s.branch_id IS NOT NULL;

UPDATE installment_plans ip
JOIN sales s ON s.id = ip.sale_id
SET ip.branch_id = s.branch_id
WHERE ip.branch_id IS NULL AND ip.sale_id IS NOT NULL AND s.branch_id IS NOT NULL;
