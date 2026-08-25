/* ===========================================================================
   v21: Two things the spec asks of the sales page.

   1) Walk-in details on a cash memo (§৭, page 12)
      A cash sale form must take কাস্টমারের নাম / মোবাইল / ঠিকানা. Until now
      the only way to name a buyer was to pick an existing account, so a
      one-off walk-in was printed as "Walk-in Customer". These three columns
      hold the buyer's details for exactly that case — a sale tied to a real
      customer account keeps reading its name from customers, so they are
      deliberately NOT called customer_name/customer_mobile: several queries
      already select `s.*` alongside `c.name AS customer_name`, and reusing
      that name would silently shadow the joined value.

   2) ledger_id — "চাইলে মেমোটি তার মূল একাউন্টের লেজারে যুক্ত করা যাবে"
      When a credit memo is pushed into the customer's khata, the khata entry
      becomes the owner of that receivable. The invoice row stays untouched
      so it can still be reprinted, but its due must stop being counted on
      the sales side or the customer would appear to owe the money twice —
      once in the invoice due list and once in the khata balance. Every place
      that sums outstanding money from `sales` therefore skips rows where
      ledger_id IS NOT NULL, starting with vw_customer_dues below.

   Safe to run more than once. Block comments and no DELIMITER, so the file
   survives being pasted through a browser that drops line breaks.
   =========================================================================== */

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'walkin_name');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN walkin_name VARCHAR(150) NULL DEFAULT NULL AFTER customer_id',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'walkin_mobile');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN walkin_mobile VARCHAR(20) NULL DEFAULT NULL AFTER walkin_name',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'walkin_address');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN walkin_address VARCHAR(500) NULL DEFAULT NULL AFTER walkin_mobile',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'ledger_id');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN ledger_id INT UNSIGNED NULL DEFAULT NULL AFTER previous_due',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

/* A memo that now lives in the khata is no longer outstanding here. */
CREATE OR REPLACE VIEW vw_customer_dues AS
SELECT
    c.id   AS customer_id,
    c.name AS customer_name,
    c.phone,
    COALESCE(SUM(s.due_amount),   0) AS total_due,
    COALESCE(SUM(s.total_amount), 0) AS total_purchase,
    COALESCE(SUM(s.paid_amount),  0) AS total_paid
FROM   customers c
LEFT JOIN sales s ON s.customer_id = c.id AND s.status = 'completed'
                 AND s.ledger_id IS NULL
GROUP BY c.id;
