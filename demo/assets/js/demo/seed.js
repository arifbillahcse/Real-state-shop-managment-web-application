// ============================================
// Demo seed data
// Dates are generated relative to today so charts and
// "today's sales" always look populated, whenever the demo is opened.
// ============================================

const Seed = (() => {

    function fmtDate(dt) {
        const p = n => String(n).padStart(2, '0');
        return dt.getFullYear() + '-' + p(dt.getMonth() + 1) + '-' + p(dt.getDate());
    }

    function daysAgo(n) {
        const dt = new Date();
        dt.setDate(dt.getDate() - n);
        return fmtDate(dt);
    }

    function stamp(n) {
        return daysAgo(n) + ' 10:00:00';
    }

    // ---- Static rows ----

    const users = [
        { id: 1, name: 'Administrator',   username: 'admin', password: 'admin123', role: 'admin', is_active: 1, created_at: stamp(90) },
        { id: 2, name: 'রফিকুল ইসলাম',    username: 'staff', password: 'staff123', role: 'staff', is_active: 1, created_at: stamp(60) },
        { id: 3, name: 'সোহেল রানা',      username: 'sohel', password: 'staff123', role: 'staff', is_active: 0, created_at: stamp(45) },
    ];

    const suppliers = [
        { id: 1, name: 'BSRM Steel Ltd.',        phone: '01711000001', address: 'চট্টগ্রাম', is_active: 1, created_at: stamp(90) },
        { id: 2, name: 'AKS Steel',              phone: '01711000002', address: 'ঢাকা',      is_active: 1, created_at: stamp(88) },
        { id: 3, name: 'Lafarge Holcim BD',      phone: '01711000003', address: 'ঢাকা',      is_active: 1, created_at: stamp(80) },
        { id: 4, name: 'Shah Cement Industries', phone: '01711000004', address: 'নারায়ণগঞ্জ', is_active: 1, created_at: stamp(75) },
    ];

    const products = [
        { id: 1, type: 'rod',    name: 'Steel Rod 8mm',     size_brand: '8mm',        unit: 'ton', buy_price: 65000, sell_price: 68000, min_stock: 2,  is_active: 1, created_at: stamp(90) },
        { id: 2, type: 'rod',    name: 'Steel Rod 10mm',    size_brand: '10mm',       unit: 'ton', buy_price: 67000, sell_price: 70000, min_stock: 2,  is_active: 1, created_at: stamp(90) },
        { id: 3, type: 'rod',    name: 'Steel Rod 12mm',    size_brand: '12mm',       unit: 'ton', buy_price: 68000, sell_price: 71000, min_stock: 2,  is_active: 1, created_at: stamp(90) },
        { id: 4, type: 'rod',    name: 'Steel Rod 16mm',    size_brand: '16mm',       unit: 'ton', buy_price: 70000, sell_price: 73000, min_stock: 2,  is_active: 1, created_at: stamp(90) },
        { id: 5, type: 'cement', name: 'Lafarge Cement',    size_brand: 'LAFARGE',    unit: 'bag', buy_price: 480,   sell_price: 520,   min_stock: 50, is_active: 1, created_at: stamp(90) },
        { id: 6, type: 'cement', name: 'Holcim Cement',     size_brand: 'HOLCIM',     unit: 'bag', buy_price: 475,   sell_price: 515,   min_stock: 50, is_active: 1, created_at: stamp(90) },
        { id: 7, type: 'cement', name: 'Heidelberg Cement', size_brand: 'HEIDELBERG', unit: 'bag', buy_price: 470,   sell_price: 510,   min_stock: 50, is_active: 1, created_at: stamp(90) },
        { id: 8, type: 'cement', name: 'Shah Cement',       size_brand: 'SHAH',       unit: 'bag', buy_price: 460,   sell_price: 500,   min_stock: 50, is_active: 1, created_at: stamp(90) },
    ];

    const customers = [
        { id: 1, name: 'Walk-in Customer', phone: '0000000000', address: '',                    is_active: 1, created_at: stamp(90) },
        { id: 2, name: 'কামরুল হাসান',     phone: '01812345601', address: 'মিরপুর ১০, ঢাকা',     is_active: 1, created_at: stamp(70) },
        { id: 3, name: 'আব্দুল করিম',       phone: '01812345602', address: 'সাভার, ঢাকা',        is_active: 1, created_at: stamp(65) },
        { id: 4, name: 'মেসার্স রহমান ট্রেডার্স', phone: '01812345603', address: 'কেরানীগঞ্জ, ঢাকা', is_active: 1, created_at: stamp(60) },
        { id: 5, name: 'শাহীন কনস্ট্রাকশন',  phone: '01812345604', address: 'উত্তরা, ঢাকা',       is_active: 1, created_at: stamp(50) },
        { id: 6, name: 'নুরুল ইসলাম',       phone: '01812345605', address: 'গাজীপুর',            is_active: 1, created_at: stamp(40) },
        { id: 7, name: 'জাহিদ বিল্ডার্স',    phone: '01812345606', address: 'নারায়ণগঞ্জ',         is_active: 1, created_at: stamp(30) },
        { id: 8, name: 'মোস্তফা কামাল',      phone: '01812345607', address: 'টঙ্গী, গাজীপুর',      is_active: 1, created_at: stamp(20) },
    ];

    // product_id, supplier_id, quantity, days ago
    // Rod 16mm and Heidelberg Cement are deliberately under-stocked so the
    // dashboard's low-stock alert has something to show.
    const inboundSpec = [
        [1, 1, 12, 55], [2, 1, 15, 55], [3, 2, 18, 54], [4, 2,  5, 54],
        [5, 3, 900, 50], [6, 3, 700, 50], [7, 3, 150, 49], [8, 4, 800, 49],
        [1, 1,  8, 30], [3, 2, 10, 30], [5, 3, 600, 28], [8, 4, 500, 28],
        [2, 1,  6, 12], [6, 3, 300, 10], [4, 2, 2.5, 6], [7, 3, 110,  4],
    ];

    // days ago, customer_id, items [[product_id, qty, unit_price]], discount, paid, method, created_by
    const salesSpec = [
        [28, 2, [[3, 1.5, 71000], [5, 60, 520]],  1000, 138200, 'cash',   1],
        [26, 4, [[1, 2,   68000]],                   0, 100000, 'credit', 1],
        [24, 3, [[5, 120, 520], [6, 80, 515]],     500, 103700, 'cash',   2],
        [21, 5, [[4, 2.5, 73000], [3, 1, 71000]], 2000,  150000,'credit', 1],
        [18, 2, [[8, 200, 500]],                     0, 100000, 'cash',   2],
        [15, 6, [[2, 1.2, 70000], [7, 50, 510]],   800,  60000, 'credit', 1],
        [12, 7, [[3, 3,  71000]],                 3000, 210000, 'cash',   1],
        [10, 4, [[5, 150, 520], [8, 100, 500]],      0, 100000, 'credit', 2],
        [ 8, 3, [[1, 1,  68000], [6, 40, 515]],    500,  88100, 'cash',   1],
        [ 6, 8, [[4, 2,  73000]],                    0,  90000, 'credit', 1],
        [ 6, 1, [[5, 25, 520]],                      0,  13000, 'cash',   2],
        [ 5, 5, [[7, 100, 510], [8, 60, 500]],    1000,  80000, 'credit', 1],
        [ 5, 2, [[2, 1,  70000]],                    0,  70000, 'cash',   1],
        [ 4, 6, [[3, 1.5, 71000]],                 500, 106000, 'cash',   2],
        [ 4, 1, [[6, 30, 515]],                      0,  15450, 'cash',   1],
        [ 3, 7, [[1, 2.5, 68000], [5, 80, 520]],  2000,  150000,'credit', 1],
        [ 2, 3, [[8, 120, 500]],                     0,  60000, 'cash',   2],
        [ 2, 4, [[4, 1.5, 73000]],                1500,  60000, 'credit', 1],
        [ 1, 5, [[2, 2,  70000], [7, 60, 510]],   1000,  170600,'cash',   1],
        [ 1, 8, [[5, 90, 520]],                      0,  30000, 'credit', 2],
        [ 0, 2, [[3, 1,  71000], [6, 50, 515]],    500,  96250, 'cash',   1],
        [ 0, 6, [[8, 150, 500]],                     0,  50000, 'credit', 1],
        [ 0, 1, [[5, 20, 520], [7, 20, 510]],        0,  20600, 'cash',   2],
    ];

    // customer_id, sale_index (into salesSpec, or null for general), amount, days ago, method
    const paymentSpec = [
        [4,  1, 36000, 20, 'cash'],
        [5,  3, 30000, 16, 'cheque'],
        [6,  5, 15000, 11, 'cash'],
        [4,  7, 45000,  7, 'mobile_banking'],
        [8,  9, 20000,  4, 'cash'],
        [5, 11, 10000,  3, 'cash'],
        [7, 15, 25000,  2, 'cheque'],
    ];

    const settings = {
        shop_name:      'আমার রড সিমেন্ট ভান্ডার',
        shop_address:   'ঢাকা, বাংলাদেশ',
        shop_phone:     '01XXXXXXXXX',
        shop_email:     'shop@example.com',
        currency:       'BDT',
        invoice_prefix: 'INV',
    };

    // ---- Builder ----

    function build() {
        const stock_inbound = inboundSpec.map((row, i) => {
            const [productId, supplierId, quantity, ago] = row;
            const product = products.find(p => p.id === productId);
            return {
                id:           i + 1,
                product_id:   productId,
                supplier_id:  supplierId,
                quantity:     quantity,
                buy_price:    product.buy_price,
                total_cost:   quantity * product.buy_price,
                inbound_date: daysAgo(ago),
                note:         '',
                created_by:   1,
                created_at:   stamp(ago),
            };
        });

        const sales      = [];
        const sale_items = [];
        let   itemId     = 1;
        const invoiceSeq = {};

        salesSpec.forEach((row, i) => {
            const [ago, customerId, items, discount, paidRaw, method, createdBy] = row;
            const saleId   = i + 1;
            const saleDate = daysAgo(ago);

            let subtotal = 0;
            items.forEach(([productId, qty, price]) => {
                subtotal += qty * price;
                sale_items.push({
                    id:          itemId++,
                    sale_id:     saleId,
                    product_id:  productId,
                    quantity:    qty,
                    unit_price:  price,
                    total_price: qty * price,
                    created_at:  stamp(ago),
                });
            });

            const total = Math.max(0, subtotal - discount);
            const paid  = Math.max(0, Math.min(paidRaw, total));

            const dateKey = saleDate.replace(/-/g, '');
            invoiceSeq[dateKey] = (invoiceSeq[dateKey] || 0) + 1;

            sales.push({
                id:             saleId,
                invoice_number: settings.invoice_prefix + '-' + dateKey + '-' +
                                String(invoiceSeq[dateKey]).padStart(4, '0'),
                customer_id:    customerId,
                sale_date:      saleDate,
                subtotal:       subtotal,
                discount:       discount,
                total_amount:   total,
                paid_amount:    paid,
                due_amount:     total - paid,
                payment_method: method,
                status:         'completed',
                note:           '',
                created_by:     createdBy,
                created_at:     stamp(ago),
            });
        });

        // Apply payments against their sales, exactly like Payment::addPayment does.
        const payments = [];
        paymentSpec.forEach((row, i) => {
            const [customerId, saleIndex, amountRaw, ago, method] = row;
            let saleId = null;
            let amount = amountRaw;

            if (saleIndex !== null) {
                const sale = sales[saleIndex];
                if (sale && sale.customer_id === customerId && sale.due_amount > 0) {
                    amount = Math.min(amount, sale.due_amount);
                    sale.paid_amount += amount;
                    sale.due_amount   = Math.max(0, sale.total_amount - sale.paid_amount);
                    saleId = sale.id;
                }
            }

            payments.push({
                id:             i + 1,
                customer_id:    customerId,
                sale_id:        saleId,
                amount:         amount,
                payment_method: method,
                reference_no:   method === 'cash' ? '' : 'REF' + (100000 + i * 37),
                payment_date:   daysAgo(ago),
                note:           '',
                created_by:     1,
                created_at:     stamp(ago),
            });
        });

        return {
            _version:      1,
            _seeded_at:    new Date().toISOString(),
            users:         clone(users),
            suppliers:     clone(suppliers),
            products:      clone(products),
            stock_inbound: stock_inbound,
            customers:     clone(customers),
            sales:         sales,
            sale_items:    sale_items,
            payments:      payments,
            activity_logs: [],
            settings:      clone(settings),
        };
    }

    function clone(v) {
        return JSON.parse(JSON.stringify(v));
    }

    return { build, daysAgo, fmtDate };
})();
