/* ===========================================================================
   v22: Re-cut vw_branch_stock — transfers were invisible to branch stock

   The view has been through several shapes across older migration files
   (v2, v4, v6, v11) before v15 settled on the correct one. A database that
   reached its current state some other way than "every migration file ran
   in order on this exact database" — restored from an old backup, built by
   hand-running SQL out of order, or whose schema_migrations table was
   baselined as "already applied" (see classes/Migrator.php) when v15's fix
   in particular never actually ran — can be left with an OLDER view body
   forever, because CREATE OR REPLACE VIEW only fires when a migration file
   actually executes, not because the table/column checks pass.

   The visible symptom: sending and receiving a transfer moves rows in
   stock_transfers correctly, but current_stock for both the sending and the
   receiving branch does not change, because the oldest shape of this view
   (v2) never looked at stock_transfers at all, and the versions between
   there and v15 counted a transfer's effect on the wrong status (or every
   status at once) instead of "sent/received" for the source and "received"
   for the destination.

   This migration does not check which version is live — CREATE OR REPLACE
   VIEW is cheap and touches no data, so it simply re-asserts the v15/
   install.sql definition unconditionally. Safe to run more than once.
   =========================================================================== */

CREATE OR REPLACE VIEW vw_branch_stock AS
SELECT
    b.id    AS branch_id,
    b.name  AS branch_name,
    p.id    AS product_id,
    p.name  AS product_name,
    pc.name AS product_type,
    p.category_id,
    p.subcategory_id,
    psc.name AS subcategory_name,
    p.product_code,
    p.image,
    p.size_brand,
    p.unit,
    COALESCE(bp.buy_price,       p.buy_price)       AS buy_price,
    COALESCE(bp.sell_price,      p.sell_price)      AS sell_price,
    COALESCE(bp.wholesale_price, p.wholesale_price) AS wholesale_price,
    COALESCE(bp.min_stock,       p.min_stock)       AS min_stock,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0) AS total_inbound,
    COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0) AS total_sold,
    COALESCE((SELECT SUM(sa.quantity)  FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0) AS total_adjustments,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id AND st.status = 'received'), 0) AS total_transferred_in,
    COALESCE((SELECT SUM(st.quantity)  FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id AND st.status IN ('sent','received')), 0) AS total_transferred_out,
    COALESCE((SELECT SUM(si.quantity) FROM stock_inbound si WHERE si.product_id = p.id AND si.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(sa.quantity) FROM stock_adjustments sa WHERE sa.product_id = p.id AND sa.branch_id = b.id), 0)
        + COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.to_branch_id   = b.id AND st.status = 'received'), 0)
        - COALESCE((SELECT SUM(st.quantity) FROM stock_transfers st WHERE st.product_id = p.id AND st.from_branch_id = b.id AND st.status IN ('sent','received')), 0)
        - COALESCE((SELECT SUM(sai.quantity) FROM sale_items sai JOIN sales s ON s.id = sai.sale_id WHERE sai.product_id = p.id AND s.branch_id = b.id AND s.status = 'completed'), 0)
        AS current_stock
FROM   branches b
CROSS  JOIN products p
JOIN   product_categories pc ON pc.id = p.category_id
LEFT   JOIN product_subcategories psc ON psc.id = p.subcategory_id
LEFT   JOIN branch_products bp ON bp.branch_id = b.id AND bp.product_id = p.id
WHERE  p.is_active = 1
  AND  b.is_active = 1;
