/* ===========================================================================
   v20: Previous due shown on a credit memo

   The spec allows a credit sale's memo to show the customer's earlier
   outstanding balance alongside this sale, so the customer sees one figure
   to pay.

   This is stored, not recomputed at print time, for two reasons:
     - the balance moves, so reprinting an old memo months later has to show
       what that memo actually said, not today's number;
     - it must never be folded into total_amount / due_amount, or the old
       invoices carrying that due would be counted a second time and the
       customer's balance would double.

   NULL means the memo did not show a previous balance at all.

   Safe to run more than once. Block comments and no DELIMITER, so the file
   survives being pasted through a browser that drops line breaks.
   =========================================================================== */

SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
             AND COLUMN_NAME = 'previous_due');
SET @s := IF(@x = 0,
    'ALTER TABLE sales ADD COLUMN previous_due DECIMAL(14,2) NULL DEFAULT NULL AFTER due_amount',
    'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
