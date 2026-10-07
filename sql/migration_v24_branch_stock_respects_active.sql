/* ===========================================================================
   v24: vw_branch_stock must honor branch_products.is_active

   The spec (§২.২) describes each branch selecting its own sellable products
   from the central catalog, and the schema/UI for exactly that already
   exist: branch_products.is_active, toggled per branch from the "ব্রাঞ্চ
   মূল্য" modal on the products page (classes/Product.php::saveBranchPrice,
   assets/js/products.js). What was missing: vw_branch_stock — the single
   view every branch-scoped stock/sales/report query reads from — never
   looked at that flag. It CROSS JOINs every active product against every
   active branch unconditionally, so turning the checkbox off had no visible
   effect anywhere: a product "deactivated" for a branch still showed in
   that branch's সেলস product dropdown, স্টক list, and রিপোর্ট.

   This re-asserts the view with one added condition — a branch_products row
   explicitly marked inactive (is_active = 0) excludes that product from
   that branch. No row at all, or is_active = 1, still means active (the
   COALESCE default), so nothing changes for branches that have never
   touched this setting — only a branch that explicitly deactivated a
   product starts actually seeing it disappear, which is the whole point.

   Safe to run more than once — CREATE OR REPLACE VIEW touches no data.
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
  AND  b.is_active = 1
  AND  COALESCE(bp.is_active, 1) = 1;
