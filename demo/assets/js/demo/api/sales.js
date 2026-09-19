// Sales — ports classes/Sale.php
// createSale validates every line against current stock before writing,
// the same order Sale::createSale() does inside its transaction.

(() => {

    const num = (v) => Compute.num(v);

    function saleWithMeta(s) {
        return Object.assign({}, s, {
            customer_name:   s.customer_id ? Compute.customerName(s.customer_id) : 'Walk-in',
            created_by_name: Compute.userName(s.created_by),
            item_count:      DemoDB.table('sale_items')
                .filter(i => Number(i.sale_id) === Number(s.id)).length,
        });
    }

    function itemsOf(saleId) {
        return DemoDB.table('sale_items')
            .filter(i => Number(i.sale_id) === Number(saleId))
            .sort((a, b) => Number(a.id) - Number(b.id))
            .map(i => {
                const p = DemoDB.find('products', i.product_id);
                return Object.assign({}, i, {
                    product_name: p ? p.name : '',
                    product_type: p ? p.type : '',
                    unit:         p ? p.unit : '',
                });
            });
    }

    function parseItems(raw) {
        if (Array.isArray(raw)) return raw;
        try {
            const parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed) ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    ApiRouter.register({

        'create_sale.php': (p) => {
            const items = parseItems(p.items);
            if (items === null) return ApiRouter.fail('পণ্যের তালিকা সঠিক নয়।');
            if (!items.length)  return ApiRouter.fail('কমপক্ষে একটি পণ্য যোগ করুন।');

            let customerId = (p.customer_id !== undefined && p.customer_id !== '')
                ? Number(p.customer_id) : null;
            if (customerId !== null && customerId > 0) {
                const c = DemoDB.find('customers', customerId);
                if (!c || Number(c.is_active) !== 1) {
                    return ApiRouter.fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
                }
            } else {
                customerId = null;
            }

            let subtotal = 0;
            const validItems = [];

            for (const item of items) {
                const productId = Number(item.product_id || 0);
                const qty       = num(item.quantity);
                const price     = num(item.unit_price);

                if (productId <= 0) return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');
                if (!(qty   > 0))   return ApiRouter.fail('পরিমাণ ০ এর বেশি হতে হবে।');
                if (!(price > 0))   return ApiRouter.fail('মূল্য ০ এর বেশি হতে হবে।');

                const product = DemoDB.find('products', productId);
                if (!product || Number(product.is_active) !== 1) {
                    return ApiRouter.fail('পণ্যটি খুঁজে পাওয়া যায়নি।');
                }
                if (Compute.currentStock(productId) < qty) {
                    return ApiRouter.fail('পর্যাপ্ত স্টক নেই।');
                }

                validItems.push({ product_id: productId, quantity: qty, unit_price: price });
                subtotal += qty * price;
            }

            const discount    = Math.max(0, num(p.discount));
            const totalAmount = Math.max(0, subtotal - discount);
            const paidAmount  = Math.max(0, Math.min(num(p.paid_amount), totalAmount));
            const dueAmount   = totalAmount - paidAmount;
            const user        = DemoAuth.current();

            const saleId = DemoDB.insert('sales', {
                invoice_number: Compute.nextInvoiceNumber(),
                customer_id:    customerId,
                sale_date:      String(p.sale_date || '').trim() || DemoDB.today(),
                subtotal:       subtotal,
                discount:       discount,
                total_amount:   totalAmount,
                paid_amount:    paidAmount,
                due_amount:     dueAmount,
                payment_method: String(p.payment_method || 'cash').trim(),
                status:         'completed',
                note:           String(p.note || '').trim(),
                created_by:     user ? user.id : null,
            });

            validItems.forEach(it => {
                DemoDB.insert('sale_items', {
                    sale_id:     saleId,
                    product_id:  it.product_id,
                    quantity:    it.quantity,
                    unit_price:  it.unit_price,
                    total_price: it.quantity * it.unit_price,
                });
            });

            const sale = DemoDB.find('sales', saleId);
            DemoDB.log('create_sale', 'sales', saleId, 'Sale #' + sale.invoice_number + ' created');

            return ApiRouter.ok('বিক্রয় সফলভাবে সম্পন্ন হয়েছে।', {
                sale_id:        saleId,
                invoice_number: sale.invoice_number,
            });
        },

        'get_sales.php': (p) => {
            const data = DemoDB.table('sales')
                .filter(s => {
                    if (p.date_from && s.sale_date < p.date_from) return false;
                    if (p.date_to   && s.sale_date > p.date_to)   return false;
                    if (p.customer_id && Number(s.customer_id) !== Number(p.customer_id)) return false;
                    if (p.status && s.status !== p.status) return false;
                    return true;
                })
                .map(saleWithMeta)
                .sort((a, b) =>
                    (a.sale_date < b.sale_date ? 1 : a.sale_date > b.sale_date ? -1 : 0) ||
                    Number(b.id) - Number(a.id)
                );

            return ApiRouter.ok('', { data });
        },

        'get_sale_detail.php': (p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক ID দিন।');

            const sale = DemoDB.find('sales', id);
            if (!sale) return ApiRouter.fail('বিক্রয় রেকর্ড পাওয়া যায়নি।');

            const customer = sale.customer_id ? DemoDB.find('customers', sale.customer_id) : null;

            const data = Object.assign({}, sale, {
                customer_name:   customer ? customer.name  : 'Walk-in',
                customer_phone:  customer ? customer.phone : '',
                created_by_name: Compute.userName(sale.created_by),
                items:           itemsOf(id),
            });

            return ApiRouter.ok('', {
                data,
                shop_name:    DemoDB.setting('shop_name', 'রড সিমেন্ট ম্যানেজমেন্ট'),
                shop_address: DemoDB.setting('shop_address', ''),
                shop_phone:   DemoDB.setting('shop_phone', ''),
            });
        },

        'cancel_sale.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক ID দিন।');

            const sale = DemoDB.find('sales', id);
            if (!sale)                         return ApiRouter.fail('বিক্রয় রেকর্ড খুঁজে পাওয়া যায়নি।');
            if (sale.status === 'cancelled')   return ApiRouter.fail('এই বিক্রয় ইতিমধ্যে বাতিল।');

            // Stock and dues both read completed sales only, so cancelling
            // returns the items to stock and clears the customer's due.
            DemoDB.update('sales', id, { status: 'cancelled' });
            DemoDB.log('cancel_sale', 'sales', id, 'Cancelled sale #' + sale.invoice_number);

            return ApiRouter.ok('বিক্রয় বাতিল করা হয়েছে।');
        }),
    });
})();
