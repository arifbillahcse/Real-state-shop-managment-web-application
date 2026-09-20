// Dashboard — pages/dashboard.php built these queries server-side.
// Branch-locked users see only their own branch's sales and stock.

ApiRouter.register({

    'get_dashboard.php': () => {
        const num      = Compute.num;
        const today    = DemoDB.today();
        const branchId = DemoAuth.sessionBranchId();
        const isStaff  = DemoAuth.isStaff();

        const completed = DemoDB.table('sales').filter(s => s.status === 'completed');
        const scoped    = branchId
            ? completed.filter(s => Compute.eq(s.branch_id, branchId))
            : completed;

        const todays = scoped.filter(s => s.sale_date === today);

        // Stock: branch view for a branch user, business-wide otherwise.
        const stockRows = branchId
            ? Compute.branchStockRows(branchId)
            : Compute.stockRows();

        const data = {
            today_total: todays.reduce((a, s) => a + num(s.total_amount), 0),
            today_paid:  todays.reduce((a, s) => a + num(s.paid_amount),  0),
            today_count: todays.length,
            stock_value: stockRows.reduce((a, r) => a + r.current_stock * r.buy_price, 0),
            low_stock:   stockRows.filter(r => r.min_stock > 0 && r.current_stock <= r.min_stock),
            is_staff:    isStaff,
            branch_name: branchId ? Compute.branchName(branchId) : null,
        };

        // Staff do not see collections or the overall due figure.
        if (!isStaff) {
            const todayPayments = DemoDB.table('payments')
                .filter(p => p.payment_date === today);

            data.today_payments_total = todayPayments.reduce((a, p) => a + num(p.amount), 0);
            data.today_payments_count = todayPayments.length;
            data.total_due = completed
                .filter(s => s.ledger_id === null || s.ledger_id === undefined)
                .reduce((a, s) => a + num(s.due_amount), 0);
        }

        // Last 7 days, including days with no sales.
        data.chart = [];
        for (let i = 6; i >= 0; i--) {
            const date = Seed.daysAgo(i);
            data.chart.push({
                sale_date: date,
                total: scoped.filter(s => s.sale_date === date)
                    .reduce((a, s) => a + num(s.total_amount), 0),
            });
        }

        // Recent 5 sales, newest first.
        data.recent_sales = scoped
            .slice()
            .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)) ||
                            Number(b.id) - Number(a.id))
            .slice(0, 5)
            .map(s => ({
                id: s.id,
                invoice_number: s.invoice_number,
                sale_date: s.sale_date,
                customer_name: s.customer_id ? Compute.customerName(s.customer_id) : 'Walk-in',
                total_amount: num(s.total_amount),
                paid_amount:  num(s.paid_amount),
                due_amount:   num(s.due_amount),
                payment_method: s.payment_method,
            }));

        return ApiRouter.ok('', { data });
    },
});
