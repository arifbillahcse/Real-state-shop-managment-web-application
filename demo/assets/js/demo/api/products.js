// Products — ports classes/Product.php

(() => {

    const TYPES = ['rod', 'cement'];

    function activeProducts() {
        return DemoDB.table('products').filter(p => Number(p.is_active) === 1);
    }

    function duplicateExists(type, name, sizeBrand, exceptId) {
        return activeProducts().some(p =>
            p.type === type &&
            p.name === name &&
            String(p.size_brand || '') === sizeBrand &&
            Number(p.id) !== Number(exceptId)
        );
    }

    // Mirrors Product::errorMessage()
    function validate(type, name, buyPrice, sellPrice, minStock) {
        if (!TYPES.includes(type))  return 'পণ্যের ধরন সঠিক নয় (rod / cement)।';
        if (name === '')            return 'পণ্যের নাম দিন।';
        if (!(buyPrice  > 0))       return 'ক্রয় দাম ০ এর বেশি হতে হবে।';
        if (!(sellPrice > 0))       return 'বিক্রয় দাম ০ এর বেশি হতে হবে।';
        if (!(minStock >= 0))       return 'মিনিমাম স্টক ঋণাত্মক হতে পারবে না।';
        return null;
    }

    ApiRouter.register({

        'get_products.php': (p) => {
            const id = Number(p.id || 0);
            if (id > 0) {
                const product = activeProducts().find(x => Number(x.id) === id);
                return product
                    ? ApiRouter.ok('OK', { product })
                    : ApiRouter.fail('পণ্যটি খুঁজে পাওয়া যায়নি।');
            }

            const type = TYPES.includes(p.type) ? p.type : null;
            const products = activeProducts()
                .filter(x => !type || x.type === type)
                .sort((a, b) =>
                    (type ? 0 : a.type.localeCompare(b.type)) ||
                    a.name.localeCompare(b.name)
                );

            return ApiRouter.ok('OK', { products, count: products.length });
        },

        'add_product.php': ApiRouter.adminOnly((p) => {
            const type      = String(p.type || '').toLowerCase().trim();
            const name      = String(p.name || '').trim();
            const sizeBrand = String(p.size_brand || '').trim();
            const unit      = String(p.unit || 'pcs').trim();
            const buyPrice  = Compute.num(p.buy_price);
            const sellPrice = Compute.num(p.sell_price);
            const minStock  = Compute.num(p.min_stock);

            const error = validate(type, name, buyPrice, sellPrice, minStock);
            if (error) return ApiRouter.fail(error);

            if (duplicateExists(type, name, sizeBrand, 0)) {
                return ApiRouter.fail('এই পণ্যটি ইতিমধ্যে আছে।');
            }

            const id = DemoDB.insert('products', {
                type, name, size_brand: sizeBrand, unit,
                buy_price: buyPrice, sell_price: sellPrice, min_stock: minStock,
                is_active: 1,
            });
            DemoDB.log('create_product', 'products', id, 'Added product: ' + name);
            return ApiRouter.ok('পণ্য সফলভাবে যোগ হয়েছে।', { id });
        }),

        'update_product.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');

            const product = activeProducts().find(x => Number(x.id) === id);
            if (!product) return ApiRouter.fail('পণ্যটি খুঁজে পাওয়া যায়নি।');

            const pick = (key, fallback) =>
                (p[key] === undefined || p[key] === null || p[key] === '') ? fallback : p[key];

            const type      = String(pick('type', product.type)).toLowerCase().trim();
            const name      = String(pick('name', product.name)).trim();
            const sizeBrand = String(p.size_brand !== undefined ? p.size_brand : (product.size_brand || '')).trim();
            const unit      = String(pick('unit', product.unit)).trim();
            const buyPrice  = Compute.num(pick('buy_price',  product.buy_price));
            const sellPrice = Compute.num(pick('sell_price', product.sell_price));
            const minStock  = Compute.num(p.min_stock !== undefined && p.min_stock !== ''
                ? p.min_stock : product.min_stock);

            const error = validate(type, name, buyPrice, sellPrice, minStock);
            if (error) return ApiRouter.fail(error);

            if (duplicateExists(type, name, sizeBrand, id)) {
                return ApiRouter.fail('এই পণ্যটি ইতিমধ্যে আছে।');
            }

            DemoDB.update('products', id, {
                type, name, size_brand: sizeBrand, unit,
                buy_price: buyPrice, sell_price: sellPrice, min_stock: minStock,
            });
            DemoDB.log('update_product', 'products', id, 'Updated product: ' + name);
            return ApiRouter.ok('পণ্য সফলভাবে আপডেট হয়েছে।');
        }),

        'delete_product.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');

            const product = activeProducts().find(x => Number(x.id) === id);
            if (!product) return ApiRouter.fail('পণ্যটি খুঁজে পাওয়া যায়নি।');

            const inStock = DemoDB.table('stock_inbound').some(r => Number(r.product_id) === id);
            const inSales = DemoDB.table('sale_items').some(r => Number(r.product_id) === id);
            if (inStock || inSales) {
                return ApiRouter.fail('এই পণ্যের স্টক/বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।');
            }

            DemoDB.update('products', id, { is_active: 0 });
            DemoDB.log('delete_product', 'products', id, 'Deleted product: ' + product.name);
            return ApiRouter.ok('পণ্য ডিলিট হয়েছে।');
        }),
    });
})();
