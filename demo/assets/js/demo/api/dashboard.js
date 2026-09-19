// Dashboard stats — pages/dashboard.php rendered these server-side.

ApiRouter.register({

    'get_dashboard.php': () => {
        const today = DemoDB.today();
        const sales = DemoDB.table('sales').filter(s => s.status === 'completed');
        const num   = Compute.num;

        const todays = sales.filter(s => s.sale_date === today);
        const stock  = Compute.stockRows();

        // Last 7 days, including days with no sales.
        const chart = [];
        for (let i = 6; i >= 0; i--) {
            const date = Seed.daysAgo(i);
            chart.push({
                sale_date: date,
                total: sales
                    .filter(s => s.sale_date === date)
                    .reduce((a, s) => a + num(s.total_amount), 0),
            });
        }

        return ApiRouter.ok('', {
            data: {
                today_total: todays.reduce((a, s) => a + num(s.total_amount), 0),
                today_paid:  todays.reduce((a, s) => a + num(s.paid_amount),  0),
                today_count: todays.length,
                total_due:   sales.reduce((a, s) => a + num(s.due_amount), 0),
                stock_value: stock.reduce((a, r) => a + r.stock_cost, 0),
                low_stock:   Compute.lowStockRows(),
                chart:       chart,
            },
        });
    },
});
