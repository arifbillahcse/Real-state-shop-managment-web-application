// ============================================
// Low stock alert centre — mirrors classes/AlertCenter.php and
// api/get_low_stock_alerts.php, get_low_stock_history.php,
// notify_low_stock.php
// ============================================

(() => {

    const num = Compute.num;
    const eq  = Compute.eq;

    // AlertCenter::tier()
    function tier(stock, threshold) {
        if (threshold <= 0)           return 'green';
        if (stock < threshold * 0.5)  return 'red';
        if (stock <= threshold)       return 'yellow';
        return 'green';
    }

    // AlertCenter::getAlerts() — branchId null reads the business-wide view
    function getAlerts(branchId, includeGreen) {
        const rows = (branchId
            ? Compute.branchStockRows(branchId).map(r => ({
                product_id: r.product_id, product_name: r.product_name,
                product_type: r.product_type, unit: r.unit,
                current_stock: r.current_stock, min_stock: r.min_stock,
                branch_id: r.branch_id, branch_name: r.branch_name,
            }))
            : Compute.stockRows().map(r => ({
                product_id: r.product_id, product_name: r.product_name,
                product_type: r.product_type, unit: r.unit,
                current_stock: r.current_stock, min_stock: r.min_stock,
                branch_id: null, branch_name: null,
            })))
            .filter(r => num(r.min_stock) > 0)
            // ORDER BY current_stock / min_stock ASC
            .sort((a, b) => (num(a.current_stock) / num(a.min_stock)) -
                            (num(b.current_stock) / num(b.min_stock)));

        return rows
            .map(r => Object.assign({}, r, { tier: tier(num(r.current_stock), num(r.min_stock)) }))
            .filter(r => includeGreen || r.tier !== 'green');
    }

    // AlertCenter::logToday() — INSERT IGNORE, one row per product/branch/day
    function logToday(branchId) {
        const today   = DemoDB.today();
        const history = DemoDB.table('low_stock_history');

        getAlerts(branchId, false).forEach(a => {
            const seen = history.some(h =>
                eq(h.product_id, a.product_id) &&
                Number(h.branch_id || 0) === Number(a.branch_id || 0) &&
                h.alerted_on === today);
            if (seen) return;

            DemoDB.insert('low_stock_history', {
                product_id: a.product_id,
                branch_id:  a.branch_id !== null ? a.branch_id : null,
                stock_qty:  num(a.current_stock),
                threshold:  num(a.min_stock),
                tier:       a.tier,
                alerted_on: today,
            });
        });
    }

    // Days back from today, as an ISO date — DATE_SUB(CURDATE(), INTERVAL ? DAY)
    function since(days) {
        const d = new Date(DemoDB.today() + 'T00:00:00');
        d.setDate(d.getDate() - days);
        return d.toISOString().slice(0, 10);
    }

    ApiRouter.register({

        'get_low_stock_alerts.php': (p) => {
            const branchId     = DemoAuth.resolveBranchId(p.branch_id);
            const includeGreen = String(p.all || '') === '1';

            // Opening the centre also records today's alerts, as the PHP does.
            logToday(branchId);

            return ApiRouter.ok('OK', { alerts: getAlerts(branchId, includeGreen) });
        },

        'get_low_stock_history.php': (p) => {
            const days = Math.max(7, Math.min(365, Number(p.days || 90)));
            const from = since(days);

            const grouped = new Map();
            DemoDB.table('low_stock_history')
                .filter(h => String(h.alerted_on) >= from)
                .forEach(h => {
                    const key = Number(h.product_id);
                    if (!grouped.has(key)) {
                        const prod = DemoDB.find('products', key) || {};
                        grouped.set(key, {
                            product_id: key, product_name: prod.name || '', unit: prod.unit || '',
                            alert_days: 0, red_days: 0,
                            first_alerted: h.alerted_on, last_alerted: h.alerted_on,
                        });
                    }
                    const g = grouped.get(key);
                    g.alert_days += 1;
                    if (h.tier === 'red') g.red_days += 1;
                    if (h.alerted_on < g.first_alerted) g.first_alerted = h.alerted_on;
                    if (h.alerted_on > g.last_alerted)  g.last_alerted  = h.alerted_on;
                });

            const history = [...grouped.values()]
                .sort((a, b) => b.alert_days - a.alert_days || b.red_days - a.red_days);

            return ApiRouter.ok('OK', { history });
        },

        'notify_low_stock.php': ApiRouter.branchWriteOnly((p) => {
            const branchId = Number(p.branch_id || 0) || null;
            const alerts   = getAlerts(branchId, false);
            if (!alerts.length) return ApiRouter.fail('এই মুহূর্তে কোনো স্টক সতর্কতা নেই।');

            const phone = DemoDB.setting('alert_phone', '') || DemoDB.setting('shop_phone', '');
            if (phone === '' || phone === '01XXXXXXXXX') {
                return ApiRouter.fail('সেটিংসে সতর্কতা পাঠানোর ফোন নাম্বার (alert_phone / shop_phone) সেট করুন।');
            }

            // No SMS gateway in a static demo — the message is queued the same
            // way Notifier::send() queues it when no gateway is configured.
            DemoDB.insert('notification_log', {
                recipient: phone, channel: 'sms', status: 'queued',
                ref_table: 'low_stock_history', ref_id: null,
                message: 'স্টক সতর্কতা (' + alerts.length + ' পণ্য)',
            });

            return ApiRouter.ok('নোটিফিকেশন পাঠানো হয়েছে (গেটওয়ে সেট থাকলে SMS যাবে, নাহলে কিউতে থাকবে)।');
        }),
    });

})();
