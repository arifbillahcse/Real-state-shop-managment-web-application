// Payments / Khata — ports classes/Payment.php

(() => {

    const num = (v) => Compute.num(v);

    function completedSalesOf(customerId) {
        return DemoDB.table('sales')
            .filter(s => Number(s.customer_id) === Number(customerId) && s.status === 'completed');
    }

    ApiRouter.register({

        'add_payment.php': (p) => {
            const customerId = Number(p.customer_id || 0);
            if (customerId <= 0) return ApiRouter.fail('সঠিক কাস্টমার নির্বাচন করুন।');

            const customer = DemoDB.find('customers', customerId);
            if (!customer || Number(customer.is_active) !== 1) {
                return ApiRouter.fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
            }

            const amount = num(p.amount);
            if (!(amount > 0)) return ApiRouter.fail('পরিমাণ ০ এর বেশি হতে হবে।');

            let saleId = (p.sale_id !== undefined && p.sale_id !== '')
                ? Number(p.sale_id) : null;

            if (saleId !== null && saleId > 0) {
                const sale = DemoDB.find('sales', saleId);
                if (!sale ||
                    Number(sale.customer_id) !== customerId ||
                    sale.status !== 'completed') {
                    return ApiRouter.fail('বিক্রয় রেকর্ড খুঁজে পাওয়া যায়নি।');
                }
                if (num(sale.due_amount) <= 0)     return ApiRouter.fail('এই বিক্রয়ের কোনো বাকি নেই।');
                if (amount > num(sale.due_amount)) return ApiRouter.fail('পরিমাণ বাকির চেয়ে বেশি হতে পারবে না।');

                const newPaid = num(sale.paid_amount) + amount;
                DemoDB.update('sales', saleId, {
                    paid_amount: newPaid,
                    due_amount:  Math.max(0, num(sale.total_amount) - newPaid),
                });
            } else {
                saleId = null;
            }

            const user = DemoAuth.current();
            const id = DemoDB.insert('payments', {
                customer_id:    customerId,
                sale_id:        saleId,
                amount:         amount,
                payment_method: String(p.payment_method || 'cash').trim(),
                reference_no:   String(p.reference_no || '').trim(),
                payment_date:   String(p.payment_date || '').trim() || DemoDB.today(),
                note:           String(p.note || '').trim(),
                created_by:     user ? user.id : null,
            });

            DemoDB.log('add_payment', 'payments', id,
                'Payment ' + amount + ' from customer #' + customerId);
            return ApiRouter.ok('পেমেন্ট সফলভাবে রেকর্ড করা হয়েছে।', { id });
        },

        'get_payments.php': (p) => {
            const data = DemoDB.table('payments')
                .filter(r => {
                    if (p.customer_id && Number(r.customer_id) !== Number(p.customer_id)) return false;
                    if (p.date_from && r.payment_date < p.date_from) return false;
                    if (p.date_to   && r.payment_date > p.date_to)   return false;
                    return true;
                })
                .map(r => {
                    const sale = r.sale_id ? DemoDB.find('sales', r.sale_id) : null;
                    return Object.assign({}, r, {
                        customer_name:   Compute.customerName(r.customer_id),
                        invoice_number:  sale ? sale.invoice_number : null,
                        created_by_name: Compute.userName(r.created_by),
                    });
                })
                .sort((a, b) =>
                    (a.payment_date < b.payment_date ? 1 : a.payment_date > b.payment_date ? -1 : 0) ||
                    Number(b.id) - Number(a.id)
                );

            return ApiRouter.ok('', { data });
        },

        'get_outstanding_sales.php': (p) => {
            const customerId = Number(p.customer_id || 0);
            if (customerId <= 0) return ApiRouter.fail('সঠিক কাস্টমার নির্বাচন করুন।');

            const data = completedSalesOf(customerId)
                .filter(s => num(s.due_amount) > 0)
                .sort((a, b) =>
                    (a.sale_date > b.sale_date ? 1 : a.sale_date < b.sale_date ? -1 : 0) ||
                    Number(a.id) - Number(b.id)
                )
                .map(s => ({
                    id:             s.id,
                    invoice_number: s.invoice_number,
                    sale_date:      s.sale_date,
                    total_amount:   num(s.total_amount),
                    paid_amount:    num(s.paid_amount),
                    due_amount:     num(s.due_amount),
                }));

            return ApiRouter.ok('', { data });
        },

        'get_customer_ledger.php': (p) => {
            const customerId = Number(p.customer_id || 0);
            if (customerId <= 0) return ApiRouter.fail('সঠিক কাস্টমার নির্বাচন করুন।');

            const customer = DemoDB.find('customers', customerId);
            if (!customer || Number(customer.is_active) !== 1) {
                return ApiRouter.fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
            }

            // Payment::getCustomerLedger() aliases sale_date AS txn_date, but
            // renderLedger() in payments.js reads s.sale_date. Both keys are
            // returned so the ledger renders either way.
            const sales = completedSalesOf(customerId)
                .sort((a, b) =>
                    (a.sale_date > b.sale_date ? 1 : a.sale_date < b.sale_date ? -1 : 0) ||
                    Number(a.id) - Number(b.id)
                )
                .map(s => ({
                    id:             s.id,
                    invoice_number: s.invoice_number,
                    txn_date:       s.sale_date,
                    sale_date:      s.sale_date,
                    total_amount:   num(s.total_amount),
                    paid_amount:    num(s.paid_amount),
                    due_amount:     num(s.due_amount),
                    payment_method: s.payment_method,
                    status:         s.status,
                }));

            const payments = DemoDB.table('payments')
                .filter(r => Number(r.customer_id) === customerId)
                .sort((a, b) =>
                    (a.payment_date > b.payment_date ? 1 : a.payment_date < b.payment_date ? -1 : 0) ||
                    Number(a.id) - Number(b.id)
                )
                .map(r => {
                    const sale = r.sale_id ? DemoDB.find('sales', r.sale_id) : null;
                    return {
                        id:             r.id,
                        txn_date:       r.payment_date,
                        amount:         num(r.amount),
                        payment_method: r.payment_method,
                        reference_no:   r.reference_no,
                        note:           r.note,
                        linked_invoice: sale ? sale.invoice_number : null,
                    };
                });

            const due = Compute.customerDue(customerId);

            return ApiRouter.ok('', {
                data: {
                    customer,
                    sales,
                    payments,
                    summary: {
                        total_purchase: due.total_purchase,
                        total_paid:     due.total_paid,
                        total_due:      due.total_due,
                    },
                },
            });
        },
    });
})();
