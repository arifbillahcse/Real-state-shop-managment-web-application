// ============================================
// Demo seed data — mirrors sql/install.sql
//
// Every table in the schema exists here, so DemoDB.table() always returns
// something. Tables the demo does not exercise yet start empty and are
// filled in as more pages are ported.
//
// Dates are generated relative to today so the dashboard chart and
// "today's sales" are populated whenever the demo is opened.
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

    const stamp = n => daysAgo(n) + ' 10:00:00';

    // ---- Branches ----
    const branches = [
        { id: 1, name: 'প্রধান গুদাম',   address: 'ঢাকা, বাংলাদেশ',   phone: '01711000000', is_active: 1, created_at: stamp(180) },
        { id: 2, name: 'মিরপুর ব্রাঞ্চ',  address: 'মিরপুর ১০, ঢাকা', phone: '01711000002', is_active: 1, created_at: stamp(150) },
        { id: 3, name: 'সাভার ব্রাঞ্চ',   address: 'সাভার, ঢাকা',     phone: '01711000003', is_active: 1, created_at: stamp(120) },
    ];

    // ---- Users (one per role; branch roles are pinned to a branch) ----
    const users = [
        { id: 1, name: 'Administrator',  username: 'admin',   password: 'admin123',   role: 'admin',             branch_id: null, is_active: 1, created_at: stamp(180) },
        { id: 2, name: 'রফিকুল ইসলাম',   username: 'manager', password: 'manager123', role: 'manager',           branch_id: null, is_active: 1, created_at: stamp(150) },
        { id: 3, name: 'সোহেল রানা',     username: 'asst',    password: 'asst123',    role: 'assistant_manager', branch_id: 2,    is_active: 1, created_at: stamp(120) },
        { id: 4, name: 'জাহিদ হাসান',    username: 'staff',   password: 'staff123',   role: 'staff',             branch_id: 2,    is_active: 1, created_at: stamp(90)  },
        { id: 5, name: 'কামরুল হাসান',   username: 'staff2',  password: 'staff123',   role: 'staff',             branch_id: 3,    is_active: 0, created_at: stamp(60)  },
    ];

    // ---- Product catalogue ----
    const product_categories = [
        { id: 1, name: 'রড',      created_at: stamp(180) },
        { id: 2, name: 'সিমেন্ট', created_at: stamp(180) },
        { id: 3, name: 'পাইপ',    created_at: stamp(170) },
        { id: 4, name: 'বালু',    created_at: stamp(170) },
        { id: 5, name: 'ইলেকট্রিক', created_at: stamp(160) },
    ];

    const product_subcategories = [
        { id: 1, category_id: 1, name: 'এম এস রড',   created_at: stamp(180) },
        { id: 2, category_id: 2, name: 'ব্যাগ সিমেন্ট', created_at: stamp(180) },
        { id: 3, category_id: 3, name: 'পিভিসি',      created_at: stamp(170) },
        { id: 4, category_id: 3, name: 'ফিটিংস',      created_at: stamp(170) },
        { id: 5, category_id: 5, name: 'ফ্যান',        created_at: stamp(160) },
    ];

    const products = [
        { id: 1, category_id: 1, subcategory_id: 1, name: 'Steel Rod 8mm',    product_code: 'ROD-08',  size_brand: '8mm',      unit: 'ton',  buy_price: 65000, sell_price: 68000, wholesale_price: 66500, min_stock: 2 },
        { id: 2, category_id: 1, subcategory_id: 1, name: 'Steel Rod 10mm',   product_code: 'ROD-10',  size_brand: '10mm',     unit: 'ton',  buy_price: 67000, sell_price: 70000, wholesale_price: 68500, min_stock: 2 },
        { id: 3, category_id: 1, subcategory_id: 1, name: 'Steel Rod 12mm',   product_code: 'ROD-12',  size_brand: '12mm',     unit: 'ton',  buy_price: 68000, sell_price: 71000, wholesale_price: 69500, min_stock: 2 },
        { id: 4, category_id: 1, subcategory_id: 1, name: 'Steel Rod 16mm',   product_code: 'ROD-16',  size_brand: '16mm',     unit: 'ton',  buy_price: 70000, sell_price: 73000, wholesale_price: 71500, min_stock: 2 },
        { id: 5, category_id: 2, subcategory_id: 2, name: 'Jamuna Cement',    product_code: 'CEM-JAM', size_brand: 'JAMUNA',   unit: 'bag',  buy_price: 480,   sell_price: 520,   wholesale_price: 500,   min_stock: 50 },
        { id: 6, category_id: 2, subcategory_id: 2, name: 'Scan Cement',      product_code: 'CEM-SCN', size_brand: 'SCAN',     unit: 'bag',  buy_price: 475,   sell_price: 515,   wholesale_price: 495,   min_stock: 50 },
        { id: 7, category_id: 2, subcategory_id: 2, name: 'Holcim Cement',    product_code: 'CEM-HOL', size_brand: 'HOLCIM',   unit: 'bag',  buy_price: 470,   sell_price: 510,   wholesale_price: 490,   min_stock: 50 },
        { id: 8, category_id: 3, subcategory_id: 3, name: 'N.Mohammad Pipe',  product_code: 'PIP-NM',  size_brand: '1 inch',   unit: 'pcs',  buy_price: 340,   sell_price: 390,   wholesale_price: 365,   min_stock: 100 },
        { id: 9, category_id: 3, subcategory_id: 4, name: '1" Elbow',         product_code: 'FIT-EL1', size_brand: '1 inch',   unit: 'pcs',  buy_price: 22,    sell_price: 30,    wholesale_price: 26,    min_stock: 200 },
        { id: 10, category_id: 4, subcategory_id: null, name: 'যমুনা বালু',    product_code: 'SND-JAM', size_brand: 'মোটা',     unit: 'sqft', buy_price: 28,    sell_price: 38,    wholesale_price: 33,    min_stock: 2000 },
        { id: 11, category_id: 5, subcategory_id: 5, name: 'Ceiling Fan 56"', product_code: 'FAN-56',  size_brand: '56 inch',  unit: 'pcs',  buy_price: 3100,  sell_price: 3650,  wholesale_price: 3400,  min_stock: 5 },
    ].map(p => Object.assign(p, {
        image: null, is_active: 1, created_at: stamp(170),
    }));

    // Mirpur sells cement a little dearer; Savar keeps a lower rod threshold.
    const branch_products = [
        { id: 1, branch_id: 2, product_id: 5, buy_price: null, sell_price: 530, wholesale_price: null, min_stock: null, is_active: 1 },
        { id: 2, branch_id: 2, product_id: 6, buy_price: null, sell_price: 525, wholesale_price: null, min_stock: null, is_active: 1 },
        { id: 3, branch_id: 3, product_id: 3, buy_price: null, sell_price: null, wholesale_price: null, min_stock: 1,   is_active: 1 },
    ];

    // ---- Customers ----
    const customers = [
        { id: 1, name: 'কামরুল হাসান',          phone: '01812345601', address: 'মিরপুর ১০, ঢাকা',  book_no: 'B-001', account_no: 'AC-1001', account_type: 'regular', due_limit: 200000 },
        { id: 2, name: 'আব্দুল করিম',            phone: '01812345602', address: 'সাভার, ঢাকা',      book_no: 'B-002', account_no: 'AC-1002', account_type: 'regular', due_limit: 150000 },
        { id: 3, name: 'মেসার্স রহমান ট্রেডার্স', phone: '01812345603', address: 'কেরানীগঞ্জ, ঢাকা', book_no: 'B-003', account_no: 'AC-1003', account_type: 'wholesale', due_limit: 500000 },
        { id: 4, name: 'শাহীন কনস্ট্রাকশন',      phone: '01812345604', address: 'উত্তরা, ঢাকা',     book_no: 'B-004', account_no: 'AC-1004', account_type: 'wholesale', due_limit: 400000 },
        { id: 5, name: 'নুরুল ইসলাম',            phone: '01812345605', address: 'গাজীপুর',          book_no: 'B-005', account_no: 'AC-1005', account_type: 'regular', due_limit: 100000 },
        { id: 6, name: 'জাহিদ বিল্ডার্স',        phone: '01812345606', address: 'নারায়ণগঞ্জ',       book_no: 'B-006', account_no: 'AC-1006', account_type: 'wholesale', due_limit: 300000 },
        { id: 7, name: 'Mahmud Nabi',           phone: '01812345607', address: 'টঙ্গী, গাজীপুর',    book_no: 'B-007', account_no: 'AC-1007', account_type: 'regular', due_limit: 100000 },
    ].map((c, i) => Object.assign(c, {
        whatsapp: c.phone, imo: null, photo: null, is_active: 1, created_at: stamp(140 - i * 12),
    }));

    // ---- Stock in, per branch: product, branch, qty, days ago ----
    const inboundSpec = [
        [1, 1, 14, 55], [2, 1, 16, 55], [3, 1, 20, 54], [4, 1, 8, 54],
        [5, 1, 900, 50], [6, 1, 800, 50], [7, 1, 700, 49],
        [8, 1, 600, 48], [9, 1, 1200, 48], [10, 1, 9000, 47], [11, 1, 40, 46],
        [1, 2, 6, 40], [3, 2, 7, 40], [5, 2, 400, 38], [6, 2, 350, 38],
        [8, 2, 250, 36], [9, 2, 500, 36], [11, 2, 15, 35],
        [2, 3, 5, 30], [5, 3, 300, 28], [10, 3, 4000, 27], [9, 3, 400, 26],
        [1, 1, 6, 20], [5, 1, 500, 18], [3, 2, 4, 12], [6, 2, 200, 10],
    ];

    // product, branch, qty (negative = loss/damage), reason, days ago
    const adjustmentSpec = [
        [9,  1, -40, 'damage', 25],
        [10, 1, -250, 'damage', 18],
        [5,  2, -12, 'damage', 9],
        [11, 1, 2, 'correction', 6],
    ];

    // product, from, to, qty, status, days ago
    const transferSpec = [
        [1,  1, 2, 3,   'received', 22],
        [5,  1, 2, 150, 'received', 20],
        [3,  1, 3, 4,   'received', 16],
        [9,  1, 2, 200, 'received', 12],
        [6,  1, 3, 120, 'sent',     3],
        [11, 1, 2, 5,   'pending',  1],
    ];

    // days ago, customer_id (null = walk-in), branch, items [[product, qty, price]],
    // discount, paid, method, created_by
    const salesSpec = [
        [26, 3, 1, [[3, 1.5, 71000], [5, 60, 520]],   1000, 138200, 'cash',   2],
        [24, 6, 1, [[1, 2, 68000]],                      0, 100000, 'credit', 2],
        [22, 2, 2, [[5, 120, 530], [6, 80, 525]],      500, 103800, 'cash',   3],
        [20, 4, 1, [[4, 2.5, 73000], [3, 1, 71000]], 2000, 150000, 'credit', 2],
        [18, 1, 2, [[8, 60, 390], [9, 150, 30]],         0,  27900, 'cash',   3],
        [15, 5, 1, [[2, 1.2, 70000], [7, 50, 510]],    800,  60000, 'credit', 2],
        [12, 6, 1, [[3, 3, 71000]],                   3000, 210000, 'cash',   1],
        [10, 3, 3, [[5, 100, 520], [10, 1500, 38]],      0, 100000, 'credit', 2],
        [ 8, 2, 1, [[1, 1, 68000], [11, 4, 3650]],     500,  82600, 'cash',   1],
        [ 6, 7, 2, [[4, 2, 73000]],                      0,  90000, 'credit', 3],
        [ 6, null, 2, [[5, 25, 530]],                    0,  13250, 'cash',   4],
        [ 5, 4, 1, [[7, 100, 510], [9, 300, 30]],     1000,  50000, 'credit', 2],
        [ 5, 1, 2, [[2, 1, 70000]],                      0,  70000, 'cash',   3],
        [ 4, 5, 1, [[3, 1.5, 71000]],                  500, 106000, 'cash',   2],
        [ 4, null, 1, [[9, 120, 30], [8, 20, 390]],      0,  11400, 'cash',   1],
        [ 3, 6, 1, [[1, 2.5, 68000], [5, 80, 520]],   2000, 150000, 'credit', 2],
        [ 2, 2, 3, [[10, 2000, 38]],                     0,  40000, 'cash',   2],
        [ 2, 3, 1, [[4, 1.5, 73000]],                 1500,  60000, 'credit', 2],
        [ 1, 4, 1, [[2, 2, 70000], [7, 60, 510]],     1000, 170600, 'cash',   2],
        [ 1, 7, 2, [[5, 90, 530]],                       0,  30000, 'credit', 3],
        [ 0, 1, 1, [[3, 1, 71000], [6, 50, 510]],      500,  96250, 'cash',   2],
        [ 0, 5, 1, [[11, 6, 3650]],                      0,  21900, 'cash',   1],
        [ 0, null, 2, [[9, 80, 30], [8, 15, 390]],       0,   8250, 'cash',   4],
    ];

    // customer, sale index (null = general), amount, days ago, method
    const paymentSpec = [
        [6,  1, 36000, 19, 'cash'],
        [4,  3, 30000, 15, 'cheque'],
        [5,  5, 15000, 11, 'cash'],
        [6,  6, 45000,  7, 'mobile_banking'],
        [7,  9, 20000,  4, 'cash'],
        [4, 11, 10000,  3, 'cash'],
        [6, 15, 25000,  2, 'cheque'],
        [3, null, 5000, 1, 'cash'],
        [4, 17, 18000,  0, 'cash'],
        [1, 20, 12000,  0, 'mobile_banking'],
    ];

    const settings = {
        shop_name:      'নিহারিকা এন্টারপ্রাইজ',
        shop_address:   'ঢাকা, বাংলাদেশ',
        shop_phone:     '01XXXXXXXXX',
        shop_email:     'shop@example.com',
        currency:       'BDT',
        invoice_prefix: 'INV',
    };

    const expense_categories = [
        { id: 1, name: 'পরিবহন',   created_at: stamp(120) },
        { id: 2, name: 'বেতন',     created_at: stamp(120) },
        { id: 3, name: 'বিদ্যুৎ বিল', created_at: stamp(120) },
        { id: 4, name: 'দোকান ভাড়া', created_at: stamp(120) },
        { id: 5, name: 'অন্যান্য',  created_at: stamp(120) },
    ];

    const suppliers = [
        { id: 1, name: 'BSRM Steel Ltd.',        phone: '01711000001', address: 'চট্টগ্রাম',  is_active: 1, created_at: stamp(170) },
        { id: 2, name: 'AKS Steel',              phone: '01711000002', address: 'ঢাকা',       is_active: 1, created_at: stamp(168) },
        { id: 3, name: 'Jamuna Cement Ltd.',     phone: '01711000003', address: 'ঢাকা',       is_active: 1, created_at: stamp(160) },
        { id: 4, name: 'Scan Cement Industries', phone: '01711000004', address: 'নারায়ণগঞ্জ', is_active: 1, created_at: stamp(155) },
    ];

    // Migrations the app has already applied — the banner stays quiet.
    const schema_migrations = [
        2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22,
    ].map(v => ({ version: v, name: 'migration_v' + v, applied_at: stamp(30) }));

    // ---- Builder ----

    function build() {
        const stock_inbound = inboundSpec.map((row, i) => {
            const [productId, branchId, quantity, ago] = row;
            const product = products.find(p => p.id === productId);
            return {
                id: i + 1, product_id: productId, branch_id: branchId,
                supplier_id: (product.category_id === 1) ? 1 : (product.category_id === 2 ? 3 : null),
                quantity, buy_price: product.buy_price,
                total_cost: quantity * product.buy_price,
                inbound_date: daysAgo(ago), note: '', created_by: 1, created_at: stamp(ago),
            };
        });

        const stock_adjustments = adjustmentSpec.map((row, i) => {
            const [productId, branchId, quantity, reason, ago] = row;
            return {
                id: i + 1, product_id: productId, branch_id: branchId, quantity, reason,
                note: reason === 'damage' ? 'ভাঙা/নষ্ট' : 'গণনা সংশোধন',
                created_by: 1, created_at: stamp(ago),
            };
        });

        const stock_transfers = transferSpec.map((row, i) => {
            const [productId, from, to, quantity, status, ago] = row;
            return {
                id: i + 1, product_id: productId, from_branch_id: from, to_branch_id: to,
                quantity, status, transfer_date: daysAgo(ago),
                customer_name: null, customer_address: null, customer_mobile: null,
                order_manager: 'রফিকুল ইসলাম', driver_name: 'আকবর আলী', driver_mobile: '01911000001',
                return_note: null,
                received_by: status === 'received' ? 3 : null,
                received_at: status === 'received' ? stamp(ago - 1) : null,
                note: '', created_by: 1, created_at: stamp(ago),
            };
        });

        const sales = [];
        const sale_items = [];
        let itemId = 1;
        const invoiceSeq = {};

        salesSpec.forEach((row, i) => {
            const [ago, customerId, branchId, items, discount, paidRaw, method, createdBy] = row;
            const saleId   = i + 1;
            const saleDate = daysAgo(ago);

            let subtotal = 0;
            items.forEach(([productId, qty, price]) => {
                subtotal += qty * price;
                sale_items.push({
                    id: itemId++, sale_id: saleId, product_id: productId,
                    quantity: qty, unit_price: price, rate_type: 'retail',
                    unload_bill: 0, labor_bill: 0, transport_bill: 0,
                    total_price: qty * price, created_at: stamp(ago),
                });
            });

            const total = Math.max(0, subtotal - discount);
            const paid  = Math.max(0, Math.min(paidRaw, total));
            const key   = saleDate.replace(/-/g, '');
            invoiceSeq[key] = (invoiceSeq[key] || 0) + 1;

            sales.push({
                id: saleId,
                invoice_number: settings.invoice_prefix + '-' + key + '-' +
                                String(invoiceSeq[key]).padStart(4, '0'),
                customer_id: customerId,
                walkin_name: customerId ? null : 'Walk-in',
                walkin_mobile: null, walkin_address: null,
                branch_id: branchId, sale_date: saleDate,
                subtotal, discount,
                unload_bill: 0, labor_bill: 0, transport_bill: 0, delivery_charge: 0,
                discount_note: null,
                total_amount: total, paid_amount: paid, due_amount: total - paid,
                previous_due: 0, ledger_id: null,
                payment_method: method, status: 'completed', note: '',
                sold_by_name: null, sold_by_mobile: null,
                created_by: createdBy, approved_by: null,
                created_at: stamp(ago), updated_at: stamp(ago),
            });
        });

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
                id: i + 1, customer_id: customerId, sale_id: saleId, amount,
                payment_method: method,
                reference_no: method === 'cash' ? '' : 'REF' + (100000 + i * 37),
                payment_date: daysAgo(ago), note: '', created_by: 2, created_at: stamp(ago),
            });
        });

        return {
            _version:   2,
            _seeded_at: new Date().toISOString(),

            // populated
            branches:             clone(branches),
            users:                clone(users),
            product_categories:   clone(product_categories),
            product_subcategories: clone(product_subcategories),
            products:             clone(products),
            branch_products:      clone(branch_products),
            customers:            clone(customers),
            suppliers:            clone(suppliers),
            expense_categories:   clone(expense_categories),
            schema_migrations:    clone(schema_migrations),
            settings:             clone(settings),
            stock_inbound, stock_adjustments, stock_transfers,
            sales, sale_items, payments,

            // ported in later phases — present so lookups never fail
            activity_logs: [], agreement_deliveries: [], customer_ledger: [],
            customer_ledger_items: [], customer_notes: [], customer_phones: [],
            customer_references: [], due_assignments: [], expenses: [],
            free_notes: [], installment_plans: [], installments: [],
            low_stock_history: [], notification_log: [],
            purchase_agreement_items: [], purchase_agreements: [],
            quotation_items: [], quotations: [],
            sale_return_items: [], sale_returns: [], staff_tasks: [],
        };
    }

    function clone(v) {
        return JSON.parse(JSON.stringify(v));
    }

    return { build, daysAgo, fmtDate };
})();
