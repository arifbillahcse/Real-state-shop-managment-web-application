// Reports — ports classes/Report.php

(() => {

    const num = (v) => Compute.num(v);

    function completedSales(from, to) {
        return DemoDB.table('sales')
            .filter(s => s.status === 'completed' && Compute.inRange(s.sale_date, from, to));
    }

    // sale_items joined to completed sales in range, with their product
    function soldItems(from, to) {
        const sales = new Map(completedSales(from, to).map(s => [Number(s.id), s]));

        return DemoDB.table('sale_items')
            .filter(i => sales.has(Number(i.sale_id)))
            .map(i => ({
                item:    i,
                sale:    sales.get(Number(i.sale_id)),
                product: DemoDB.find('products', i.product_id),
            }))
            .filter(r => r.product);
    }

    function salesSummary(from, to) {
        const rows = completedSales(from, to);
        return {
            sale_count: rows.length,
            subtotal:   rows.reduce((a, s) => a + num(s.subtotal),     0),
            discount:   rows.reduce((a, s) => a + num(s.discount),     0),
            total:      rows.reduce((a, s) => a + num(s.total_amount), 0),
            paid:       rows.reduce((a, s) => a + num(s.paid_amount),  0),
            due:        rows.reduce((a, s) => a + num(s.due_amount),   0),
        };
    }

    function dailySales(from, to) {
        const byDate = new Map();

        completedSales(from, to).forEach(s => {
            const row = byDate.get(s.sale_date) || {
                sale_date: s.sale_date, sale_count: 0, total: 0, paid: 0, due: 0,
            };
            row.sale_count += 1;
            row.total += num(s.total_amount);
            row.paid  += num(s.paid_amount);
            row.due   += num(s.due_amount);
            byDate.set(s.sale_date, row);
        });

        return [...byDate.values()].sort((a, b) => a.sale_date.localeCompare(b.sale_date));
    }

    function topProducts(from, to, limit = 10) {
        const byProduct = new Map();

        soldItems(from, to).forEach(({ item, product }) => {
            const row = byProduct.get(Number(product.id)) || {
                id:            product.id,
                product_name:  product.name,
                product_type:  product.type,
                unit:          product.unit,
                total_qty:     0,
                total_revenue: 0,
            };
            row.total_qty     += num(item.quantity);
            row.total_revenue += num(item.total_price);
            byProduct.set(Number(product.id), row);
        });

        return [...byProduct.values()]
            .sort((a, b) => b.total_revenue - a.total_revenue)
            .slice(0, Math.max(1, Math.min(100, limit)));
    }

    function salesByType(from, to) {
        const byType = new Map();

        soldItems(from, to).forEach(({ item, product }) => {
            const row = byType.get(product.type) || {
                product_type: product.type, total_qty: 0, total_revenue: 0,
            };
            row.total_qty     += num(item.quantity);
            row.total_revenue += num(item.total_price);
            byType.set(product.type, row);
        });

        return [...byType.values()];
    }

    function purchaseSummary(from, to) {
        const rows = DemoDB.table('stock_inbound')
            .filter(r => Compute.inRange(r.inbound_date, from, to));

        return {
            purchase_count: rows.length,
            total_qty:      rows.reduce((a, r) => a + num(r.quantity),   0),
            total_cost:     rows.reduce((a, r) => a + num(r.total_cost), 0),
        };
    }

    function paymentsSummary(from, to) {
        const rows = DemoDB.table('payments')
            .filter(r => Compute.inRange(r.payment_date, from, to));

        return {
            payment_count:   rows.length,
            total_collected: rows.reduce((a, r) => a + num(r.amount), 0),
        };
    }

    function stockValuation() {
        const rows = Compute.stockRows();
        return {
            rows,
            totals: {
                cost:  rows.reduce((a, r) => a + r.stock_cost,  0),
                value: rows.reduce((a, r) => a + r.stock_value, 0),
            },
        };
    }

    function customerDues() {
        return Compute.customerDues()
            .filter(d => d.total_due > 0)
            .sort((a, b) => b.total_due - a.total_due);
    }

    // Uses each product's CURRENT buy_price, like Report::profitEstimate()
    function profitEstimate(from, to) {
        let revenue = 0;
        let cost    = 0;

        soldItems(from, to).forEach(({ item, product }) => {
            revenue += num(item.total_price);
            cost    += num(item.quantity) * num(product.buy_price);
        });

        return { revenue, cost, profit: revenue - cost };
    }

    function firstOfMonth() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-01';
    }

    ApiRouter.register({

        'get_report.php': ApiRouter.adminOnly((p) => {
            const isDate = v => /^\d{4}-\d{2}-\d{2}$/.test(String(v || ''));

            const from = isDate(p.from) ? p.from : firstOfMonth();
            const to   = isDate(p.to)   ? p.to   : DemoDB.today();

            return ApiRouter.ok('', {
                data: {
                    range:         { from, to },
                    sales_summary: salesSummary(from, to),
                    daily_sales:   dailySales(from, to),
                    top_products:  topProducts(from, to, 10),
                    sales_by_type: salesByType(from, to),
                    purchase:      purchaseSummary(from, to),
                    payments:      paymentsSummary(from, to),
                    profit:        profitEstimate(from, to),
                    stock:         stockValuation(),
                    customer_dues: customerDues(),
                },
            });
        }),
    });
})();
