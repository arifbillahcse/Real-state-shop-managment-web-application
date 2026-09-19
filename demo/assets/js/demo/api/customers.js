// Customers — ports classes/Customer.php

ApiRouter.register({

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

        return ApiRouter.ok('', { data: rows });
    },

    'add_customer.php': (p) => {
        const name = String(p.name || '').trim();
        if (name === '') return ApiRouter.fail('কাস্টমারের নাম দিন।');

        const id = DemoDB.insert('customers', {
            name:      name,
            phone:     String(p.phone   || '').trim(),
            address:   String(p.address || '').trim(),
            is_active: 1,
        });
        DemoDB.log('add_customer', 'customers', id, 'Added customer: ' + name);
        return ApiRouter.ok('কাস্টমার যোগ করা হয়েছে।', { id });
    },

    'update_customer.php': (p) => {
        const id       = Number(p.id);
        const customer = DemoDB.find('customers', id);
        if (!customer || Number(customer.is_active) !== 1) {
            return ApiRouter.fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
        }

        const name = String(p.name !== undefined ? p.name : customer.name).trim();
        if (name === '') return ApiRouter.fail('কাস্টমারের নাম দিন।');

        DemoDB.update('customers', id, {
            name:    name,
            phone:   String(p.phone   !== undefined ? p.phone   : customer.phone   || '').trim(),
            address: String(p.address !== undefined ? p.address : customer.address || '').trim(),
        });
        DemoDB.log('update_customer', 'customers', id, 'Updated customer: ' + name);
        return ApiRouter.ok('কাস্টমার আপডেট হয়েছে।');
    },

    'delete_customer.php': (p) => {
        const id       = Number(p.id);
        const customer = DemoDB.find('customers', id);
        if (!customer || Number(customer.is_active) !== 1) {
            return ApiRouter.fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
        }
        if (id === 1) return ApiRouter.fail('ডিফল্ট কাস্টমার ডিলিট করা যাবে না।');

        const used = DemoDB.table('sales').some(s => Number(s.customer_id) === id);
        if (used) return ApiRouter.fail('এই কাস্টমারের বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।');

        DemoDB.update('customers', id, { is_active: 0 });
        DemoDB.log('delete_customer', 'customers', id, 'Deleted customer: ' + customer.name);
        return ApiRouter.ok('কাস্টমার ডিলিট হয়েছে।');
    },
});
