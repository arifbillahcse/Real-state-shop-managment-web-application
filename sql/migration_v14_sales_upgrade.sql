-- v14: Sales process upgrade (buyer requirements §7)
-- - Labor/unload/transport charges (per item OR combined) + delivery charge
-- - Rate type per line (retail/wholesale/custom)
-- - Discount note; manager approval for over-limit credit sales

ALTER TABLE sales
    ADD COLUMN unload_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount,
    ADD COLUMN labor_bill      DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unload_bill,
    ADD COLUMN transport_bill  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER labor_bill,
    ADD COLUMN delivery_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER transport_bill,
    ADD COLUMN discount_note   VARCHAR(300)  NULL DEFAULT NULL AFTER delivery_charge,
    ADD COLUMN approved_by     INT UNSIGNED  NULL DEFAULT NULL
        COMMENT 'manager who approved an over-limit credit sale' AFTER created_by;

ALTER TABLE sales
    ADD CONSTRAINT fk_sales_approver
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE sale_items
    ADD COLUMN rate_type      ENUM('retail','wholesale','custom') NOT NULL DEFAULT 'retail' AFTER unit_price,
    ADD COLUMN unload_bill    DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER rate_type,
    ADD COLUMN labor_bill     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unload_bill,
    ADD COLUMN transport_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER labor_bill;
