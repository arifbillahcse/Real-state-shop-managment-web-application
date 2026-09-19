// Stock — ports classes/Stock.php
// Not admin-only: api/*stock*.php calls requireMethod but not requireAdminApi,
// so staff can record purchases here too.

(() => {

    const num = (v) => Compute.num(v);

    // total_cost is a generated column in the schema
    function withTotal(quantity, buyPrice) {
        return num(quantity) * num(buyPrice);
    }

    function decorate(row) {
        const product  = DemoDB.find('products',  row.product_id);
        const supplier = row.supplier_id ? DemoDB.find('suppliers', row.supplier_id) : null;

        return Object.assign({}, row, {
            product_name:    product ? product.name : '',
            product_type:    product ? product.type : '',
            unit:            product ? product.unit : '',
            supplier_name:   supplier ? supplier.name : null,
            created_by_name: Compute.userName(row.created_by),
        });
    }

    function activeProduct(id) {
        const p = DemoDB.find('products', id);
        return (p && Number(p.is_active) === 1) ? p : null;
    }

    function activeSupplier(id) {
        const s = DemoDB.find('suppliers', id);
        return (s && Number(s.is_active) === 1) ? s : null;
    }

    ApiRouter.register({

        'get_stock.php': () => {
            const stock = Compute.stockRows();
            return ApiRouter.ok('OK', { stock, count: stock.length });
        },

        'get_stock_inbound.php': (p) => {
            const id = Number(p.id || 0);
            if (id > 0) {
                const record = DemoDB.find('stock_inbound', id);
                return record
                    ? ApiRouter.ok('OK', { record })
                    : ApiRouter.fail('রেকর্ডটি খুঁজে পাওয়া যায়নি।');
            }

            const productId = Number(p.product_id || 0);
            const history = DemoDB.table('stock_inbound')
                .filter(r => !productId || Number(r.product_id) === productId)
                .map(decorate)
                .sort((a, b) =>
                    (a.inbound_date < b.inbound_date ? 1 : a.inbound_date > b.inbound_date ? -1 : 0) ||
                    Number(b.id) - Number(a.id)
                );

            return ApiRouter.ok('OK', { history, count: history.length });
        },

        'add_stock_inbound.php': (p) => {
            const productId = Number(p.product_id || 0);
            const quantity  = num(p.quantity);
            const buyPrice  = num(p.buy_price);

            if (productId <= 0)          return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');
            if (!activeProduct(productId)) return ApiRouter.fail('পণ্যটি খুঁজে পাওয়া যায়নি।');
            if (!(quantity > 0))         return ApiRouter.fail('পরিমাণ ০ এর বেশি হতে হবে।');
            if (!(buyPrice > 0))         return ApiRouter.fail('ক্রয় দাম ০ এর বেশি হতে হবে।');

            let supplierId = (p.supplier_id !== undefined && p.supplier_id !== '')
                ? Number(p.supplier_id) : null;
            if (supplierId !== null && supplierId > 0) {
                if (!activeSupplier(supplierId)) return ApiRouter.fail('সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');
            } else {
                supplierId = null;
            }

            const user = DemoAuth.current();
            const id = DemoDB.insert('stock_inbound', {
                product_id:   productId,
                supplier_id:  supplierId,
                quantity:     quantity,
                buy_price:    buyPrice,
                total_cost:   withTotal(quantity, buyPrice),
                inbound_date: String(p.inbound_date || '') || DemoDB.today(),
                note:         String(p.note || '').trim(),
                created_by:   user ? user.id : null,
            });

            DemoDB.log('add_stock', 'stock', id,
                'Stock inbound: product #' + productId + ', qty ' + quantity);
            return ApiRouter.ok('স্টক সফলভাবে যোগ হয়েছে।', { id });
        },

        'update_stock_inbound.php': (p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const row = DemoDB.find('stock_inbound', id);
            if (!row) return ApiRouter.fail('রেকর্ডটি খুঁজে পাওয়া যায়নি।');

            const quantity = num(p.quantity  !== undefined && p.quantity  !== '' ? p.quantity  : row.quantity);
            const buyPrice = num(p.buy_price !== undefined && p.buy_price !== '' ? p.buy_price : row.buy_price);

            if (!(quantity > 0)) return ApiRouter.fail('পরিমাণ ০ এর বেশি হতে হবে।');
            if (!(buyPrice > 0)) return ApiRouter.fail('ক্রয় দাম ০ এর বেশি হতে হবে।');

            let supplierId = (p.supplier_id !== undefined) ? p.supplier_id : row.supplier_id;
            supplierId = (supplierId === '' || supplierId === null || supplierId === undefined)
                ? null : Number(supplierId);
            if (supplierId !== null && supplierId > 0) {
                if (!activeSupplier(supplierId)) return ApiRouter.fail('সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');
            } else {
                supplierId = null;
            }

            DemoDB.update('stock_inbound', id, {
                quantity:     quantity,
                buy_price:    buyPrice,
                total_cost:   withTotal(quantity, buyPrice),
                supplier_id:  supplierId,
                inbound_date: String(p.inbound_date !== undefined && p.inbound_date !== ''
                    ? p.inbound_date : row.inbound_date).trim(),
                note:         String(p.note !== undefined ? p.note : (row.note || '')).trim(),
            });

            DemoDB.log('update_stock', 'stock', id, 'Updated stock inbound #' + id);
            return ApiRouter.ok('স্টক আপডেট হয়েছে।');
        },

        'delete_stock_inbound.php': (p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const row = DemoDB.find('stock_inbound', id);
            if (!row) return ApiRouter.fail('রেকর্ডটি খুঁজে পাওয়া যায়নি।');

            const current = Compute.currentStock(row.product_id);
            if (current - num(row.quantity) < 0) {
                return ApiRouter.fail('এই রেকর্ড ডিলিট করলে স্টক ঋণাত্মক হয়ে যাবে।');
            }

            DemoDB.remove('stock_inbound', id);
            DemoDB.log('delete_stock', 'stock', id, 'Deleted stock inbound #' + id);
            return ApiRouter.ok('স্টক রেকর্ড ডিলিট হয়েছে।');
        },
    });
})();
