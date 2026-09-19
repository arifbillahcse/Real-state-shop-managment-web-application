// ============================================
// Demo API router
// One handler per file in /api/*.php, returning the same
// { success, message, ...data } contract the real endpoints return.
//
// Phase 1 registers the dashboard + customer endpoints.
// Later phases add products, stock, sales, payments, suppliers,
// users, reports and settings to the same map.
// ============================================

const ApiRouter = (() => {

    const num = v => Compute.num(v);
    const ok   = (message = '', data = {}) => Object.assign({ success: true,  message }, data);
    const fail = (message)                  => ({ success: false, message });

    const routes = {

        // ---- Dashboard (server-rendered in pages/dashboard.php) ----

        'get_dashboard.php': () => {
            const today = DemoDB.today();
            const sales = DemoDB.table('sales').filter(s => s.status === 'completed');

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

            return ok('', {
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

        // ---- Customers ----

        'get_customers.php': () => {
            const dues = Compute.customerDues();

            const rows = DemoDB.table('customers')
                .filter(c => Number(c.is_active) === 1)
                .map(c => {
                    const d = dues.find(x => Number(x.customer_id) === Number(c.id));
                    return Object.assign({}, c, {
                        total_due:      d ? d.total_due      : 0,
                        total_purchase: d ? d.total_purchase : 0,
                    });
                })
                .sort((a, b) => a.name.localeCompare(b.name));

            return ok('', { data: rows });
        },

        'add_customer.php': (p) => {
            const name = String(p.name || '').trim();
            if (name === '') return fail('কাস্টমারের নাম দিন।');

            const id = DemoDB.insert('customers', {
                name:      name,
                phone:     String(p.phone   || '').trim(),
                address:   String(p.address || '').trim(),
                is_active: 1,
            });
            DemoDB.log('add_customer', 'customers', id, 'Added customer: ' + name);
            return ok('কাস্টমার যোগ করা হয়েছে।', { id });
        },

        'update_customer.php': (p) => {
            const id       = Number(p.id);
            const customer = DemoDB.find('customers', id);
            if (!customer || Number(customer.is_active) !== 1) {
                return fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
            }

            const name = String(p.name !== undefined ? p.name : customer.name).trim();
            if (name === '') return fail('কাস্টমারের নাম দিন।');

            DemoDB.update('customers', id, {
                name:    name,
                phone:   String(p.phone   !== undefined ? p.phone   : customer.phone   || '').trim(),
                address: String(p.address !== undefined ? p.address : customer.address || '').trim(),
            });
            DemoDB.log('update_customer', 'customers', id, 'Updated customer: ' + name);
            return ok('কাস্টমার আপডেট হয়েছে।');
        },

        'delete_customer.php': (p) => {
            const id       = Number(p.id);
            const customer = DemoDB.find('customers', id);
            if (!customer || Number(customer.is_active) !== 1) {
                return fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
            }
            if (id === 1) return fail('ডিফল্ট কাস্টমার ডিলিট করা যাবে না।');

            const used = DemoDB.table('sales').some(s => Number(s.customer_id) === id);
            if (used) return fail('এই কাস্টমারের বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।');

            // Soft delete, matching Customer::deleteCustomer()
            DemoDB.update('customers', id, { is_active: 0 });
            DemoDB.log('delete_customer', 'customers', id, 'Deleted customer: ' + customer.name);
            return ok('কাস্টমার ডিলিট হয়েছে।');
        },
    };

    function handle(endpoint, params) {
        const route = routes[endpoint];
        if (!route) {
            return fail('ডেমোতে এই অংশটি এখনো যুক্ত হয়নি (' + endpoint + ')।');
        }
        try {
            return route(params || {});
        } catch (err) {
            console.error('Demo API error in ' + endpoint, err);
            return fail('একটি সমস্যা হয়েছে।');
        }
    }

    // Lets later phases register their endpoints without touching this file.
    function register(map) {
        Object.assign(routes, map);
    }

    return { handle, register, ok, fail };
})();
