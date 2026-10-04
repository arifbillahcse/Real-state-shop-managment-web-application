/* ===========================================================================
   v23: Track which staff member entered a customer

   "নতুন কাস্টমার" so far recorded no one — there was no way to see which
   staff member did the data entry, only who the customer's reference is
   (a different, already-existing thing: customer_references.ref_user_id
   credits sales performance, not data entry). This adds that missing
   attribution, captured automatically from the logged-in session at
   creation time — no new field on the form, since every staff member
   already has their own login.

   Safe to run more than once. Block comments and no DELIMITER, so the file
   survives being pasted through a browser that drops line breaks.
   =========================================================================== */

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
             AND COLUMN_NAME = 'created_by');
SET @s := IF(@x = 0,
    'ALTER TABLE customers ADD COLUMN created_by INT UNSIGNED NULL DEFAULT NULL AFTER due_limit',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
              AND CONSTRAINT_NAME = 'fk_customers_created_by');
SET @s := IF(@fk = 0,
    'ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
