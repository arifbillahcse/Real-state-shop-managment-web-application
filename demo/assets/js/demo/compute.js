// ============================================
// Derived data
// Ports the two SQL views from sql/schema.sql:
//   vw_current_stock  → stockRows() / currentStock()
//   vw_customer_dues  → customerDues() / customerDue()
// ============================================

const Compute = (() => {

    function num(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function completedSaleIds() {
        return new Set(
            DemoDB.table('sales')
                .filter(s => s.status === 'completed')
                .map(s => Number(s.id))
        );
    }

    // --- vw_current_stock ---

    function totalInbound(productId) {
        return DemoDB.table('stock_inbound')
            .filter(r => Number(r.product_id) === Number(productId))
            .reduce((sum, r) => sum + num(r.quantity), 0);
    }

    function totalSold(productId, completed) {
        const ids = completed || completedSaleIds();
        return DemoDB.table('sale_items')
            .filter(r => Number(r.product_id) === Number(productId) && ids.has(Number(r.sale_id)))
            .reduce((sum, r) => sum + num(r.quantity), 0);
    }

    function currentStock(productId) {
        return totalInbound(productId) - totalSold(productId);
    }

    function stockRows() {
        const completed = completedSaleIds();

        return DemoDB.table('products')
            .filter(p => Number(p.is_active) === 1)
            .map(p => {
                const inbound = totalInbound(p.id);
                const sold    = totalSold(p.id, completed);
                const stock   = inbound - sold;
                return {
                    product_id:    p.id,
                    product_name:  p.name,
                    product_type:  p.type,
                    size_brand:    p.size_brand,
                    unit:          p.unit,
                    buy_price:     num(p.buy_price),
                    sell_price:    num(p.sell_price),
                    min_stock:     num(p.min_stock),
                    total_inbound: inbound,
                    total_sold:    sold,
                    current_stock: stock,
                    stock_cost:    stock * num(p.buy_price),
                    stock_value:   stock * num(p.sell_price),
                };
            })
            .sort((a, b) =>
                a.product_type.localeCompare(b.product_type) ||
                a.product_name.localeCompare(b.product_name)
            );
    }

    function lowStockRows() {
        return stockRows().filter(r => r.min_stock > 0 && r.current_stock <= r.min_stock);
    }

    // --- vw_customer_dues ---

    function customerDues() {
        const sales = DemoDB.table('sales').filter(s => s.status === 'completed');

        return DemoDB.table('customers').map(c => {
            const mine = sales.filter(s => Number(s.customer_id) === Number(c.id));
            return {
                customer_id:    c.id,
                customer_name:  c.name,
                phone:          c.phone,
                total_purchase: mine.reduce((a, s) => a + num(s.total_amount), 0),
                total_paid:     mine.reduce((a, s) => a + num(s.paid_amount),  0),
                total_due:      mine.reduce((a, s) => a + num(s.due_amount),   0),
            };
        });
    }

    function customerDue(customerId) {
        return customerDues().find(d => Number(d.customer_id) === Number(customerId)) || {
            customer_id: customerId, total_purchase: 0, total_paid: 0, total_due: 0,
        };
    }

    // --- shared helpers used by the API layer ---

    function customerName(customerId) {
        const c = DemoDB.find('customers', customerId);
        return c ? c.name : 'Walk-in';
    }

    function userName(userId) {
        const u = DemoDB.find('users', userId);
        return u ? u.name : '';
    }

    function productName(productId) {
        const p = DemoDB.find('products', productId);
        return p ? p.name : '';
    }

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
        num, currentStock, stockRows, lowStockRows,
        customerDues, customerDue,
        customerName, userName, productName,
        inRange, nextInvoiceNumber,
    };
})();
