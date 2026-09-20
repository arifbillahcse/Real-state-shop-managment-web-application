// Products, categories, sub-categories and branch prices —
// ports classes/Product.php and classes/Category.php.

(() => {

    const num = (v) => Compute.num(v);
    const eq  = Compute.eq;

    function activeProducts() {
        return DemoDB.table('products').filter(p => Number(p.is_active) === 1);
    }

    function decorate(p) {
        const cat = DemoDB.find('product_categories', p.category_id);
        const sub = p.subcategory_id ? DemoDB.find('product_subcategories', p.subcategory_id) : null;
        return Object.assign({}, p, {
            category_name:    cat ? cat.name : '',
            subcategory_name: sub ? sub.name : null,
        });
    }

    function sortProducts(rows) {
        return rows.sort((a, b) =>
            String(a.category_name).localeCompare(String(b.category_name)) ||
            String(a.name).localeCompare(String(b.name)));
    }

    // Mirrors Product::errorMessage()
    const ERR = {
        INVALID_CATEGORY:        'সঠিক ক্যাটাগরি নির্বাচন করুন।',
        INVALID_SUBCATEGORY:     'সঠিক সাব-ক্যাটাগরি নির্বাচন করুন।',
        NAME_REQUIRED:           'পণ্যের নাম দিন।',
        INVALID_BUY_PRICE:       'ক্রয় দাম ০ এর বেশি হতে হবে।',
        INVALID_SELL_PRICE:      'বিক্রয় দাম ০ এর বেশি হতে হবে।',
        INVALID_WHOLESALE_PRICE: 'পাইকারি দাম ঋণাত্মক হতে পারবে না।',
        INVALID_MIN_STOCK:       'মিনিমাম স্টক ঋণাত্মক হতে পারবে না।',
        INVALID_PRICE:           'দাম ঋণাত্মক হতে পারবে না।',
        DUPLICATE:               'এই পণ্যটি ইতিমধ্যে আছে।',
        DUPLICATE_CODE:          'এই পণ্য কোডটি ইতিমধ্যে ব্যবহৃত হয়েছে।',
        NOT_FOUND:               'পণ্যটি খুঁজে পাওয়া যায়নি।',
        HAS_HISTORY:             'এই পণ্যের স্টক/বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
        HAS_PRODUCTS:            'এই সাব-ক্যাটাগরিতে পণ্য আছে, ডিলিট করা যাবে না।',
    };

    function validate(categoryId, name, buy, sell, wholesale, minStock) {
        if (!(categoryId > 0))  return ERR.INVALID_CATEGORY;
        if (name === '')        return ERR.NAME_REQUIRED;
        if (!(buy  > 0))        return ERR.INVALID_BUY_PRICE;
        if (!(sell > 0))        return ERR.INVALID_SELL_PRICE;
        if (wholesale < 0)      return ERR.INVALID_WHOLESALE_PRICE;
        if (minStock  < 0)      return ERR.INVALID_MIN_STOCK;
        if (!DemoDB.find('product_categories', categoryId)) return ERR.INVALID_CATEGORY;
        return null;
    }

    function duplicateProduct(categoryId, name, sizeBrand, exceptId) {
        return activeProducts().some(p =>
            eq(p.category_id, categoryId) &&
            p.name === name &&
            String(p.size_brand || '') === sizeBrand &&
            !eq(p.id, exceptId));
    }

    function duplicateCode(code, exceptId) {
        if (code === '') return false;
        return DemoDB.table('products').some(p =>
            p.product_code === code && !eq(p.id, exceptId));
    }

    ApiRouter.register({

        // ---- Products ----

        'get_products.php': (p) => {
            const id = Number(p.id || 0);
            if (id > 0) {
                const product = activeProducts().find(x => eq(x.id, id));
                return product
                    ? ApiRouter.ok('OK', { product: decorate(product) })
                    : ApiRouter.fail(ERR.NOT_FOUND);
            }

            const categoryId = (p.category_id !== undefined && p.category_id !== '')
                ? Number(p.category_id) : null;

            const products = sortProducts(
                activeProducts()
                    .filter(x => !categoryId || eq(x.category_id, categoryId))
                    .map(decorate)
            );

            return ApiRouter.ok('OK', { products, count: products.length });
        },

        'add_product.php': ApiRouter.adminOnly((p) => {
            const categoryId = Number(p.category_id || 0);
            const name       = String(p.name || '').trim();
            const code       = String(p.product_code || '').trim();
            const sizeBrand  = String(p.size_brand || '').trim();
            const unit       = String(p.unit || 'pcs').trim();
            const buy        = num(p.buy_price);
            const sell       = num(p.sell_price);
            const wholesale  = num(p.wholesale_price);
            const minStock   = num(p.min_stock);

            const error = validate(categoryId, name, buy, sell, wholesale, minStock);
            if (error) return ApiRouter.fail(error);

            let subcategoryId = Number(p.subcategory_id || 0) || null;
            if (subcategoryId) {
                const sc = DemoDB.find('product_subcategories', subcategoryId);
                if (!sc || !eq(sc.category_id, categoryId)) return ApiRouter.fail(ERR.INVALID_SUBCATEGORY);
            }

            if (duplicateProduct(categoryId, name, sizeBrand, 0)) return ApiRouter.fail(ERR.DUPLICATE);
            if (duplicateCode(code, 0))                           return ApiRouter.fail(ERR.DUPLICATE_CODE);

            const id = DemoDB.insert('products', {
                category_id: categoryId, subcategory_id: subcategoryId,
                name, product_code: code || null, size_brand: sizeBrand, unit,
                image: String(p.image || '') || null,
                buy_price: buy, sell_price: sell, wholesale_price: wholesale,
                min_stock: minStock, is_active: 1,
            });

            // Auto-code if none given
            if (code === '') {
                DemoDB.update('products', id, { product_code: 'P-' + String(id).padStart(4, '0') });
            }

            // Register in every active branch (central prices by default)
            DemoDB.table('branches')
                .filter(b => Number(b.is_active) === 1)
                .forEach(b => {
                    const exists = DemoDB.table('branch_products')
                        .some(bp => eq(bp.branch_id, b.id) && eq(bp.product_id, id));
                    if (!exists) {
                        DemoDB.insert('branch_products', {
                            branch_id: b.id, product_id: id,
                            buy_price: null, sell_price: null, wholesale_price: null,
                            min_stock: null, is_active: 1,
                        });
                    }
                });

            DemoDB.log('create_product', 'products', id, 'Added product: ' + name);
            return ApiRouter.ok('পণ্য সফলভাবে যোগ হয়েছে।', { id });
        }),

        'update_product.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');

            const product = activeProducts().find(x => eq(x.id, id));
            if (!product) return ApiRouter.fail(ERR.NOT_FOUND);

            const given = (key) => p[key] !== undefined && p[key] !== null && p[key] !== '';

            const categoryId = given('category_id') ? Number(p.category_id) : Number(product.category_id);
            const name       = String(given('name') ? p.name : product.name).trim();
            const code       = String(given('product_code') ? p.product_code : (product.product_code || '')).trim();
            const sizeBrand  = String(p.size_brand !== undefined ? p.size_brand : (product.size_brand || '')).trim();
            const unit       = String(given('unit') ? p.unit : product.unit).trim();
            const buy        = num(given('buy_price')       ? p.buy_price       : product.buy_price);
            const sell       = num(given('sell_price')      ? p.sell_price      : product.sell_price);
            const wholesale  = num(p.wholesale_price !== undefined && p.wholesale_price !== ''
                ? p.wholesale_price : product.wholesale_price);
            const minStock   = num(p.min_stock !== undefined && p.min_stock !== ''
                ? p.min_stock : product.min_stock);

            const error = validate(categoryId, name, buy, sell, wholesale, minStock);
            if (error) return ApiRouter.fail(error);

            // A subcategory from another category is cleared, not rejected.
            let subcategoryId = (p.subcategory_id !== undefined)
                ? (Number(p.subcategory_id) || null)
                : (product.subcategory_id || null);
            if (subcategoryId) {
                const sc = DemoDB.find('product_subcategories', subcategoryId);
                if (!sc || !eq(sc.category_id, categoryId)) subcategoryId = null;
            }

            if (duplicateProduct(categoryId, name, sizeBrand, id)) return ApiRouter.fail(ERR.DUPLICATE);
            if (duplicateCode(code, id))                           return ApiRouter.fail(ERR.DUPLICATE_CODE);

            DemoDB.update('products', id, {
                category_id: categoryId, subcategory_id: subcategoryId,
                name, product_code: code || product.product_code,
                size_brand: sizeBrand, unit,
                image: (p.image !== undefined ? String(p.image) : (product.image || '')) || null,
                buy_price: buy, sell_price: sell, wholesale_price: wholesale, min_stock: minStock,
            });

            DemoDB.log('update_product', 'products', id, 'Updated product: ' + name);
            return ApiRouter.ok('পণ্য সফলভাবে আপডেট হয়েছে।');
        }),

        'delete_product.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক পণ্য নির্বাচন করুন।');

            const product = activeProducts().find(x => eq(x.id, id));
            if (!product) return ApiRouter.fail(ERR.NOT_FOUND);

            const inStock = DemoDB.table('stock_inbound').some(r => eq(r.product_id, id));
            const inSales = DemoDB.table('sale_items').some(r => eq(r.product_id, id));
            if (inStock || inSales) return ApiRouter.fail(ERR.HAS_HISTORY);

            DemoDB.update('products', id, { is_active: 0 });
            DemoDB.log('delete_product', 'products', id, 'Deleted product: ' + product.name);
            return ApiRouter.ok('পণ্য ডিলিট হয়েছে।');
        }),

        // Images need a server to write to, so the demo says so plainly
        // instead of failing with a generic error.
        'upload_product_image.php': ApiRouter.adminOnly(() =>
            ApiRouter.fail('ডেমোতে ছবি আপলোড করা যায় না — এর জন্য সার্ভার দরকার।')),

        // ---- Categories ----

        'add_category.php': ApiRouter.adminOnly((p) => {
            const name = String(p.name || '').trim();
            if (name === '') return ApiRouter.fail('ক্যাটাগরির নাম দিন।');

            const dup = DemoDB.table('product_categories').some(c => c.name === name);
            if (dup) return ApiRouter.fail('এই ক্যাটাগরি ইতিমধ্যে আছে।');

            const id = DemoDB.insert('product_categories', { name });
            return ApiRouter.ok('ক্যাটাগরি যোগ করা হয়েছে।', { id });
        }),

        'delete_category.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক ক্যাটাগরি নির্বাচন করুন।');

            const used = activeProducts().some(x => eq(x.category_id, id));
            if (used) return ApiRouter.fail('এই ক্যাটাগরিতে পণ্য আছে, ডিলিট করা যাবে না।');

            DemoDB.remove('product_categories', id);
            return ApiRouter.ok('ক্যাটাগরি মুছে ফেলা হয়েছে।');
        }),

        // ---- Sub-categories ----

        'get_subcategories.php': (p) => {
            const categoryId = Number(p.category_id || 0);
            const rows = DemoDB.table('product_subcategories')
                .filter(s => !categoryId || eq(s.category_id, categoryId))
                .map(s => Object.assign({}, s, {
                    category_name: (DemoDB.find('product_categories', s.category_id) || {}).name || '',
                }))
                .sort((a, b) =>
                    String(a.category_name).localeCompare(String(b.category_name)) ||
                    String(a.name).localeCompare(String(b.name)));

            return ApiRouter.ok('OK', { subcategories: rows });
        },

        'add_subcategory.php': ApiRouter.adminOnly((p) => {
            const categoryId = Number(p.category_id || 0);
            const name       = String(p.name || '').trim();

            if (name === '') return ApiRouter.fail(ERR.NAME_REQUIRED);
            if (!DemoDB.find('product_categories', categoryId)) return ApiRouter.fail(ERR.INVALID_CATEGORY);

            const dup = DemoDB.table('product_subcategories')
                .some(s => eq(s.category_id, categoryId) && s.name === name);
            if (dup) return ApiRouter.fail(ERR.DUPLICATE);

            const id = DemoDB.insert('product_subcategories', { category_id: categoryId, name });
            return ApiRouter.ok('সাব-ক্যাটাগরি যোগ হয়েছে।', { id });
        }),

        'delete_subcategory.php': ApiRouter.adminOnly((p) => {
            const id = Number(p.id || 0);
            const used = activeProducts().some(x => eq(x.subcategory_id, id));
            if (used) return ApiRouter.fail(ERR.HAS_PRODUCTS);

            DemoDB.remove('product_subcategories', id);
            return ApiRouter.ok('সাব-ক্যাটাগরি ডিলিট হয়েছে।');
        }),

        // ---- Branch prices ----

        'get_branch_prices.php': (p) => {
            const productId = Number(p.product_id || 0);
            const branchId  = Number(p.branch_id  || 0);

            if (productId > 0) {
                // One row per active branch; null means "use the central price"
                const prices = DemoDB.table('branches')
                    .filter(b => Number(b.is_active) === 1)
                    .sort((a, b) => String(a.name).localeCompare(String(b.name)))
                    .map(b => {
                        const bp = DemoDB.table('branch_products')
                            .find(r => eq(r.branch_id, b.id) && eq(r.product_id, productId));
                        return {
                            branch_id: b.id, branch_name: b.name,
                            buy_price:       bp ? bp.buy_price       : null,
                            sell_price:      bp ? bp.sell_price      : null,
                            wholesale_price: bp ? bp.wholesale_price : null,
                            min_stock:       bp ? bp.min_stock       : null,
                            is_active:       bp ? bp.is_active       : 1,
                        };
                    });
                return ApiRouter.ok('OK', { prices });
            }

            if (branchId > 0) {
                const prices = DemoDB.table('branch_products')
                    .filter(bp => eq(bp.branch_id, branchId))
                    .map(bp => {
                        const prod = activeProducts().find(x => eq(x.id, bp.product_id));
                        if (!prod) return null;
                        const pick = (k) => (bp[k] !== null && bp[k] !== undefined) ? num(bp[k]) : num(prod[k]);
                        return {
                            product_id: bp.product_id,
                            buy_price: pick('buy_price'),
                            sell_price: pick('sell_price'),
                            wholesale_price: pick('wholesale_price'),
                            is_active: bp.is_active,
                        };
                    })
                    .filter(Boolean);
                return ApiRouter.ok('OK', { prices });
            }

            return ApiRouter.fail('product_id বা branch_id প্রয়োজন।');
        },

        'save_branch_prices.php': ApiRouter.adminOnly((p) => {
            const productId = Number(p.product_id || 0);
            let rows;
            try { rows = JSON.parse(p.rows || '[]'); } catch (e) { rows = null; }

            if (productId <= 0 || !Array.isArray(rows) || !rows.length) {
                return ApiRouter.fail('ভুল ডেটা।');
            }

            const toNullable = (v) =>
                (v === null || v === undefined || v === '' || isNaN(parseFloat(v))) ? null : parseFloat(v);

            for (const r of rows) {
                const branchId = Number(r.branch_id || 0);
                if (branchId <= 0) continue;

                const values = {
                    buy_price:       toNullable(r.buy_price),
                    sell_price:      toNullable(r.sell_price),
                    wholesale_price: toNullable(r.wholesale_price),
                    min_stock:       toNullable(r.min_stock),
                };

                for (const v of Object.values(values)) {
                    if (v !== null && v < 0) return ApiRouter.fail(ERR.INVALID_PRICE);
                }

                values.is_active = (r.is_active === undefined || Number(r.is_active) === 1) ? 1 : 0;

                const existing = DemoDB.table('branch_products')
                    .find(bp => eq(bp.branch_id, branchId) && eq(bp.product_id, productId));

                if (existing) DemoDB.update('branch_products', existing.id, values);
                else DemoDB.insert('branch_products',
                    Object.assign({ branch_id: branchId, product_id: productId }, values));
            }

            DemoDB.log('save_branch_price', 'products', productId, 'Branch prices updated');
            return ApiRouter.ok('ব্রাঞ্চ মূল্য সংরক্ষণ হয়েছে।');
        }),
    });
})();
