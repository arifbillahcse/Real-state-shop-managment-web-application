// ============================================
// Derived data — ports the four SQL views in sql/install.sql:
//   vw_current_stock            → stockRows()
//   vw_branch_stock             → branchStockRows()
//   vw_customer_dues            → customerDues()
//   vw_customer_ledger_balance  → ledgerBalances()
// ============================================

const Compute = (() => {

    function num(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    const eq = (a, b) => Number(a) === Number(b);

    function completedSaleIds() {
        return new Set(
            DemoDB.table('sales').filter(s => s.status === 'completed').map(s => Number(s.id))
        );
    }

    // Sales joined to their branch, for the branch-scoped view.
    function completedSaleBranch() {
        const map = new Map();
        DemoDB.table('sales')
            .filter(s => s.status === 'completed')
            .forEach(s => map.set(Number(s.id), Number(s.branch_id)));
        return map;
    }

    function productMeta(p) {
        const cat = DemoDB.find('product_categories', p.category_id);
        const sub = p.subcategory_id ? DemoDB.find('product_subcategories', p.subcategory_id) : null;
        return {
            product_id:       p.id,
            product_name:     p.name,
            product_type:     cat ? cat.name : '',
            category_id:      p.category_id,
            subcategory_id:   p.subcategory_id,
            subcategory_name: sub ? sub.name : null,
            product_code:     p.product_code,
            image:            p.image,
            size_brand:       p.size_brand,
            unit:             p.unit,
        };
    }

    function activeProducts() {
        return DemoDB.table('products').filter(p => Number(p.is_active) === 1);
    }

    // --- vw_current_stock (business-wide, ignores branch) ---

    function stockRows() {
        const completed = completedSaleIds();
        const inbound   = DemoDB.table('stock_inbound');
        const adjust    = DemoDB.table('stock_adjustments');
        const items     = DemoDB.table('sale_items');

        return activeProducts().map(p => {
            const totalInbound = inbound.filter(r => eq(r.product_id, p.id))
                .reduce((a, r) => a + num(r.quantity), 0);
            const totalAdjust = adjust.filter(r => eq(r.product_id, p.id))
                .reduce((a, r) => a + num(r.quantity), 0);
            const totalSold = items
                .filter(r => eq(r.product_id, p.id) && completed.has(Number(r.sale_id)))
                .reduce((a, r) => a + num(r.quantity), 0);

            const current = totalInbound + totalAdjust - totalSold;

            return Object.assign(productMeta(p), {
                buy_price:         num(p.buy_price),
                sell_price:        num(p.sell_price),
                wholesale_price:   num(p.wholesale_price),
                min_stock:         num(p.min_stock),
                total_inbound:     totalInbound,
                total_sold:        totalSold,
                total_adjustments: totalAdjust,
                current_stock:     current,
                stock_cost:        current * num(p.buy_price),
                stock_value:       current * num(p.sell_price),
            });
        }).sort((a, b) =>
            String(a.product_type).localeCompare(String(b.product_type)) ||
            String(a.product_name).localeCompare(String(b.product_name))
        );
    }

    function currentStock(productId) {
        const row = stockRows().find(r => eq(r.product_id, productId));
        return row ? row.current_stock : 0;
    }

    // --- vw_branch_stock (per branch, with branch price overrides) ---
    // in  = inbound + adjustments + transfers received in
    // out = sales from this branch + transfers sent out (sent or received)

    function branchStockRows(branchId = null) {
        const saleBranch = completedSaleBranch();
        const inbound    = DemoDB.table('stock_inbound');
        const adjust     = DemoDB.table('stock_adjustments');
        const items      = DemoDB.table('sale_items');
        const transfers  = DemoDB.table('stock_transfers');
        const overrides  = DemoDB.table('branch_products');

        const branches = DemoDB.table('branches')
            .filter(b => Number(b.is_active) === 1 && (branchId === null || eq(b.id, branchId)));

        const rows = [];

        branches.forEach(b => {
            activeProducts().forEach(p => {
                const bp = overrides.find(o =>
                    eq(o.branch_id, b.id) && eq(o.product_id, p.id) && Number(o.is_active) === 1);

                const pick = (key) =>
                    (bp && bp[key] !== null && bp[key] !== undefined) ? num(bp[key]) : num(p[key]);

                const totalInbound = inbound
                    .filter(r => eq(r.product_id, p.id) && eq(r.branch_id, b.id))
                    .reduce((a, r) => a + num(r.quantity), 0);

                const totalAdjust = adjust
                    .filter(r => eq(r.product_id, p.id) && eq(r.branch_id, b.id))
                    .reduce((a, r) => a + num(r.quantity), 0);

                const totalSold = items
                    .filter(r => eq(r.product_id, p.id) &&
                                 saleBranch.get(Number(r.sale_id)) === Number(b.id))
                    .reduce((a, r) => a + num(r.quantity), 0);

                const inTransfer = transfers
                    .filter(r => eq(r.product_id, p.id) && eq(r.to_branch_id, b.id) &&
                                 r.status === 'received')
                    .reduce((a, r) => a + num(r.quantity), 0);

                const outTransfer = transfers
                    .filter(r => eq(r.product_id, p.id) && eq(r.from_branch_id, b.id) &&
                                 (r.status === 'sent' || r.status === 'received'))
                    .reduce((a, r) => a + num(r.quantity), 0);

                const current = totalInbound + totalAdjust + inTransfer - outTransfer - totalSold;

                rows.push(Object.assign(productMeta(p), {
                    branch_id:   b.id,
                    branch_name: b.name,
                    buy_price:       pick('buy_price'),
                    sell_price:      pick('sell_price'),
                    wholesale_price: pick('wholesale_price'),
                    min_stock:       pick('min_stock'),
                    total_inbound:     totalInbound,
                    total_sold:        totalSold,
                    total_adjustments: totalAdjust,
                    total_transferred_in:  inTransfer,
                    total_transferred_out: outTransfer,
                    current_stock: current,
                    stock_cost:    current * pick('buy_price'),
                    stock_value:   current * pick('sell_price'),
                }));
            });
        });

        return rows;
    }

    function branchStock(productId, branchId) {
        const row = branchStockRows(branchId).find(r => eq(r.product_id, productId));
        return row ? row.current_stock : 0;
    }

    // Stock visible to the signed-in user: their branch, or the whole business.
    function visibleStockRows() {
        const locked = (typeof DemoAuth !== 'undefined') ? DemoAuth.lockedBranchId() : null;
        return locked === null ? stockRows() : branchStockRows(locked);
    }

    function lowStockRows(rows) {
        return (rows || visibleStockRows())
            .filter(r => r.min_stock > 0 && r.current_stock <= r.min_stock);
    }

    // --- vw_customer_dues (invoice dues, excluding ledger-linked sales) ---

    function customerDues() {
        const sales = DemoDB.table('sales')
            .filter(s => s.status === 'completed' &&
                         (s.ledger_id === null || s.ledger_id === undefined));

        return DemoDB.table('customers').map(c => {
            const mine = sales.filter(s => eq(s.customer_id, c.id));
            return {
                customer_id:    c.id,
                customer_name:  c.name,
                phone:          c.phone,
                total_due:      mine.reduce((a, s) => a + num(s.due_amount),   0),
                total_purchase: mine.reduce((a, s) => a + num(s.total_amount), 0),
                total_paid:     mine.reduce((a, s) => a + num(s.paid_amount),  0),
            };
        });
    }

    function customerDue(customerId) {
        return customerDues().find(d => eq(d.customer_id, customerId)) ||
            { customer_id: customerId, total_purchase: 0, total_paid: 0, total_due: 0 };
    }

    // --- vw_customer_ledger_balance (final ledger entries only) ---

    function ledgerBalances() {
        const entries = DemoDB.table('customer_ledger').filter(l => l.status === 'final');

        return DemoDB.table('customers').map(c => {
            const mine   = entries.filter(l => eq(l.customer_id, c.id));
            const debit  = mine.reduce((a, l) => a + num(l.debit),  0);
            const credit = mine.reduce((a, l) => a + num(l.credit), 0);
            return {
                customer_id: c.id, customer_name: c.name,
                total_debit: debit, total_credit: credit, balance: debit - credit,
            };
        });
    }

    function ledgerBalance(customerId) {
        return ledgerBalances().find(b => eq(b.customer_id, customerId)) ||
            { customer_id: customerId, total_debit: 0, total_credit: 0, balance: 0 };
    }

    // --- shared lookups ---

    const nameOf = (tbl, id, fallback = '') => {
        const row = DemoDB.find(tbl, id);
        return row ? row.name : fallback;
    };

    const customerName = (id) => nameOf('customers', id, 'Walk-in');
    const userName     = (id) => nameOf('users', id);
    const branchName   = (id) => nameOf('branches', id);
    const productName  = (id) => nameOf('products', id);

    function inRange(date, from, to) {
        if (from && date < from) return false;
        if (to   && date > to)   return false;
        return true;
    }

    // Mirrors Sale::generateInvoiceNumber()
    function nextInvoiceNumber() {
        const prefix  = DemoDB.setting('invoice_prefix', 'INV');
        const today   = DemoDB.today();
        const dateKey = today.replace(/-/g, '');
        const count   = DemoDB.table('sales')
            .filter(s => String(s.created_at || '').slice(0, 10) === today).length;
        return prefix + '-' + dateKey + '-' + String(count + 1).padStart(4, '0');
    }

    return {
        num, eq,
        stockRows, currentStock,
        branchStockRows, branchStock, visibleStockRows, lowStockRows,
        customerDues, customerDue,
        ledgerBalances, ledgerBalance,
        customerName, userName, branchName, productName,
        inRange, nextInvoiceNumber,
    };
})();
