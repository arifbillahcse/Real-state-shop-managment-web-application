// Suppliers — ports classes/Supplier.php

(() => {

    function activeSuppliers() {
        return DemoDB.table('suppliers').filter(s => Number(s.is_active) === 1);
    }

    function totalPurchase(supplierId) {
        return DemoDB.table('stock_inbound')
            .filter(r => Number(r.supplier_id) === Number(supplierId))
            .reduce((sum, r) => sum + Compute.num(r.total_cost), 0);
    }

    function duplicateName(name, exceptId) {
        return activeSuppliers().some(s =>
            s.name === name && Number(s.id) !== Number(exceptId)
        );
    }

    ApiRouter.register({

        'get_suppliers.php': (p) => {
            const id = Number(p.id || 0);
            if (id > 0) {
                const supplier = activeSuppliers().find(s => Number(s.id) === id);
                return supplier
                    ? ApiRouter.ok('OK', { supplier })
                    : ApiRouter.fail('সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');
            }

            const suppliers = activeSuppliers()
                .map(s => Object.assign({}, s, { total_purchase: totalPurchase(s.id) }))
                .sort((a, b) => a.name.localeCompare(b.name));

            return ApiRouter.ok('OK', { suppliers, count: suppliers.length });
        },

        'add_supplier.php': ApiRouter.adminOnly((p) => {
            const name = String(p.name || '').trim();
            if (name === '') return ApiRouter.fail('সাপ্লাইয়ারের নাম দিন।');
            if (duplicateName(name, 0)) return ApiRouter.fail('এই নামে সাপ্লাইয়ার ইতিমধ্যে আছে।');

            const id = DemoDB.insert('suppliers', {
                name:      name,
                phone:     String(p.phone   || '').trim(),
                address:   String(p.address || '').trim(),
                is_active: 1,
            });
            DemoDB.log('create_supplier', 'suppliers', id, 'Added supplier: ' + name);
            return ApiRouter.ok('সাপ্লাইয়ার যোগ হয়েছে।', { id });
        }),

        'update_supplier.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক সাপ্লাইয়ার নির্বাচন করুন।');

            const supplier = activeSuppliers().find(s => Number(s.id) === id);
            if (!supplier) return ApiRouter.fail('সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');

            const name = String(p.name !== undefined ? p.name : supplier.name).trim();
            if (name === '') return ApiRouter.fail('সাপ্লাইয়ারের নাম দিন।');
            if (duplicateName(name, id)) return ApiRouter.fail('এই নামে সাপ্লাইয়ার ইতিমধ্যে আছে।');

            DemoDB.update('suppliers', id, {
                name:    name,
                phone:   String(p.phone   !== undefined ? p.phone   : supplier.phone   || '').trim(),
                address: String(p.address !== undefined ? p.address : supplier.address || '').trim(),
            });
            DemoDB.log('update_supplier', 'suppliers', id, 'Updated supplier: ' + name);
            return ApiRouter.ok('সাপ্লাইয়ার আপডেট হয়েছে।');
        }),

        'delete_supplier.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক সাপ্লাইয়ার নির্বাচন করুন।');

            const supplier = activeSuppliers().find(s => Number(s.id) === id);
            if (!supplier) return ApiRouter.fail('সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');

            const used = DemoDB.table('stock_inbound').some(r => Number(r.supplier_id) === id);
            if (used) return ApiRouter.fail('এই সাপ্লাইয়ারের ক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।');

            DemoDB.update('suppliers', id, { is_active: 0 });
            DemoDB.log('delete_supplier', 'suppliers', id, 'Deleted supplier: ' + supplier.name);
            return ApiRouter.ok('সাপ্লাইয়ার ডিলিট হয়েছে।');
        }),
    });
})();
