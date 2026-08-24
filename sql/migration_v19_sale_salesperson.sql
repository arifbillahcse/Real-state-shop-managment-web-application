/* ===========================================================================
   v19: Who made the sale

   The sale form asks for the customer but never recorded which employee
   served them, so a completed invoice could not be traced back to a person.
   Two plain text fields rather than a link to users: the seller is often a
   staff member without a login, and the name/number on the invoice should
   stay exactly as it was printed even if the account is later renamed or
   removed.

   Safe to run more than once. Block comments and no DELIMITER, so the file
   survives being pasted through a browser that drops line breaks.
   =========================================================================== */

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'sold_by_name');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN sold_by_name VARCHAR(150) NULL DEFAULT NULL AFTER note',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'sold_by_mobile');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN sold_by_mobile VARCHAR(20) NULL DEFAULT NULL AFTER sold_by_name',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
