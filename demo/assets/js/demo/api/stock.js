// ============================================
// Stock endpoints — mirrors classes/Stock.php and the api/*_stock*.php files:
//   get_stock, get_branch_stock, get_all_branch_stock,
//   get_stock_inbound, add/update/delete_stock_inbound,
//   get_stock_adjustments, add/update/delete_stock_adjustment,
//   get_stock_transfers, add/update/delete_stock_transfer
// ============================================

(() => {

    const num = Compute.num;
    const eq  = Compute.eq;

    // Stock::errorMessage()
    const ERR = {
        INVALID_PRODUCT:    'সঠিক পণ্য নির্বাচন করুন।',
        PRODUCT_NOT_FOUND:  'পণ্যটি খুঁজে পাওয়া যায়নি।',
        INVALID_QUANTITY:   'পরিমাণ ০ এর বেশি হতে হবে।',
        INVALID_PRICE:      'ক্রয় দাম ০ এর বেশি হতে হবে।',
        SUPPLIER_NOT_FOUND: 'সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।',
        BRANCH_NOT_FOUND:   'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
        NOT_FOUND:          'রেকর্ডটি খুঁজে পাওয়া যায়নি।',
        WOULD_GO_NEGATIVE:  'এই রেকর্ড ডিলিট করলে স্টক ঋণাত্মক হয়ে যাবে।',
    };

    // Stock::adjustmentErrorMessage() — same codes, different wording
    const ADJ_ERR = {
        PRODUCT_NOT_FOUND:     'পণ্যটি খুঁজে পাওয়া যায়নি।',
        INVALID_QUANTITY:      'পরিমাণ ০ হতে পারবে না।',
        BRANCH_NOT_FOUND:      'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
        WOULD_GO_NEGATIVE:     'এই পরিমাণ কমালে স্টক ঋণাত্মক হয়ে যাবে।',
        SAME_BRANCH:           'উৎস ও গন্তব্য ব্রাঞ্চ একই হতে পারবে না।',
        FROM_BRANCH_NOT_FOUND: 'উৎস ব্রাঞ্চ পাওয়া যায়নি।',
        TO_BRANCH_NOT_FOUND:   'গন্তব্য ব্রাঞ্চ পাওয়া যায়নি।',
        INSUFFICIENT_STOCK:    'উৎস ব্রাঞ্চে পর্যাপ্ত স্টক নেই।',
        NOT_FOUND:             'রেকর্ডটি খুঁজে পাওয়া যায়নি।',
    };

    const product    = (id) => DemoDB.table('products').find(p => eq(p.id, id) && Number(p.is_active) === 1);
    const liveBranch = (id) => DemoDB.table('branches').find(b => eq(b.id, id) && Number(b.is_active) === 1);

    // '' / null / 0 all mean "no branch"; anything else is a branch id.
    function optionalBranch(v) {
        const n = Number(v || 0);
        return n > 0 ? n : null;
    }

    // Current stock the same way Stock::getCurrentStock() /
    // getCurrentBranchStock() read the two views.
    function stockOf(productId, branchId) {
        return branchId === null
            ? Compute.currentStock(productId)
            : Compute.branchStock(productId, branchId);
    }

    function decorateInbound(r) {
        const p = DemoDB.find('products', r.product_id) || {};
        const c = p.category_id ? DemoDB.find('product_categories', p.category_id) : null;
        return Object.assign({}, r, {
            product_name:    p.name || '',
            product_type:    c ? c.name : '',
            unit:            p.unit || '',
            supplier_name:   r.supplier_id ? ((DemoDB.find('suppliers', r.supplier_id) || {}).name || null) : null,
            branch_name:     r.branch_id ? Compute.branchName(r.branch_id) : null,
            created_by_name: r.created_by ? Compute.userName(r.created_by) : null,
            total_cost:      num(r.quantity) * num(r.buy_price),
        });
    }

    function decorateAdjustment(r) {
        const p = DemoDB.find('products', r.product_id) || {};
        return Object.assign({}, r, {
            product_name:    p.name || '',
            unit:            p.unit || '',
            branch_name:     r.branch_id ? Compute.branchName(r.branch_id) : null,
            created_by_name: r.created_by ? Compute.userName(r.created_by) : null,
        });
    }

    function decorateTransfer(r) {
        const p = DemoDB.find('products', r.product_id) || {};
        return Object.assign({}, r, {
            product_name:     p.name || '',
            unit:             p.unit || '',
            from_branch_name: Compute.branchName(r.from_branch_id),
            to_branch_name:   Compute.branchName(r.to_branch_id),
            created_by_name:  r.created_by ? Compute.userName(r.created_by) : null,
        });
    }

    // ORDER BY created_at DESC — ids are monotonic here, so they break ties.
    const byNewest = (a, b) =>
        String(b.created_at || '').localeCompare(String(a.created_at || '')) ||
        Number(b.id) - Number(a.id);

    ApiRouter.register({

        // ---- Current stock ----

        'get_stock.php': () => {
            const stock = Compute.stockRows();
            return ApiRouter.ok('OK', { stock, count: stock.length });
        },

        'get_branch_stock.php': (p) => {
            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            if (branchId === null || branchId <= 0) {
                return ApiRouter.fail('সঠিক ব্রাঞ্চ নির্বাচন করুন।');
            }
            const stock = Compute.branchStockRows(branchId).sort((a, b) =>
                String(a.product_type).localeCompare(String(b.product_type)) ||
                String(a.product_name).localeCompare(String(b.product_name)));
            return ApiRouter.ok('OK', { stock });
        },

        'get_all_branch_stock.php': () => {
            const stock = Compute.branchStockRows().sort((a, b) =>
                String(a.product_type).localeCompare(String(b.product_type)) ||
                String(a.product_name).localeCompare(String(b.product_name)) ||
                String(a.branch_name).localeCompare(String(b.branch_name)));
            const branches = DemoDB.table('branches');
            return ApiRouter.ok('OK', { stock, branches });
        },

        // ---- Inbound (purchases) ----

        'get_stock_inbound.php': (p) => {
            const id = Number(p.id || 0);
            if (id > 0) {
                const row = DemoDB.find('stock_inbound', id);
                return row
                    ? ApiRouter.ok('OK', { record: row })
                    : ApiRouter.fail('রেকর্ডটি খুঁজে পাওয়া যায়নি।');
            }

            const productId = Number(p.product_id || 0);
            const history = DemoDB.table('stock_inbound')
                .filter(r => !productId || eq(r.product_id, productId))
                .map(decorateInbound)
                .sort((a, b) =>
                    String(b.inbound_date).localeCompare(String(a.inbound_date)) ||
                    Number(b.id) - Number(a.id));

            return ApiRouter.ok('OK', { history, count: history.length });
        },

        'add_stock_inbound.php': ApiRouter.branchWriteOnly((p) => {
            const productId = Number(p.product_id || 0);
            const quantity  = num(p.quantity);
            const buyPrice  = num(p.buy_price);

            if (productId <= 0)        return ApiRouter.fail(ERR.INVALID_PRODUCT);
            if (!product(productId))   return ApiRouter.fail(ERR.PRODUCT_NOT_FOUND);
            if (quantity <= 0)         return ApiRouter.fail(ERR.INVALID_QUANTITY);
            if (buyPrice <= 0)         return ApiRouter.fail(ERR.INVALID_PRICE);

            const supplierId = optionalBranch(p.supplier_id);
            if (supplierId !== null && !DemoDB.find('suppliers', supplierId)) {
                return ApiRouter.fail(ERR.SUPPLIER_NOT_FOUND);
            }

            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            if (branchId !== null && !liveBranch(branchId)) return ApiRouter.fail(ERR.BRANCH_NOT_FOUND);

            const id = DemoDB.insert('stock_inbound', {
                product_id: productId, supplier_id: supplierId, branch_id: branchId,
                quantity, buy_price: buyPrice,
                inbound_date: String(p.inbound_date || '') || DemoDB.today(),
                note: String(p.note || '').trim(),
                created_by: DemoAuth.current().id,
            });

            return ApiRouter.ok('স্টক সফলভাবে যোগ হয়েছে।', { id });
        }),

        'update_stock_inbound.php': ApiRouter.branchWriteOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const denied = ApiRouter.ownBranchRecord('stock_inbound', id);
            if (denied) return denied;

            const row = DemoDB.find('stock_inbound', id);
            if (!row) return ApiRouter.fail(ERR.NOT_FOUND);

            const quantity = p.quantity  !== undefined ? num(p.quantity)  : num(row.quantity);
            const buyPrice = p.buy_price !== undefined ? num(p.buy_price) : num(row.buy_price);
            if (quantity <= 0) return ApiRouter.fail(ERR.INVALID_QUANTITY);
            if (buyPrice <= 0) return ApiRouter.fail(ERR.INVALID_PRICE);

            const supplierId = optionalBranch(p.supplier_id);
            if (supplierId !== null && !DemoDB.find('suppliers', supplierId)) {
                return ApiRouter.fail(ERR.SUPPLIER_NOT_FOUND);
            }

            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            if (branchId !== null && !liveBranch(branchId)) return ApiRouter.fail(ERR.BRANCH_NOT_FOUND);

            DemoDB.update('stock_inbound', id, {
                quantity, buy_price: buyPrice, supplier_id: supplierId, branch_id: branchId,
                inbound_date: String(p.inbound_date !== undefined ? p.inbound_date : row.inbound_date).trim(),
                note: String(p.note !== undefined ? p.note : (row.note || '')).trim(),
            });

            return ApiRouter.ok('স্টক আপডেট হয়েছে।');
        }),

        'delete_stock_inbound.php': (p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const row = DemoDB.find('stock_inbound', id);
            if (!row) return ApiRouter.fail(ERR.NOT_FOUND);

            const branchId = optionalBranch(row.branch_id);
            if (stockOf(row.product_id, branchId) - num(row.quantity) < 0) {
                return ApiRouter.fail(ERR.WOULD_GO_NEGATIVE);
            }

            DemoDB.remove('stock_inbound', id);
            return ApiRouter.ok('স্টক রেকর্ড ডিলিট হয়েছে।');
        },

        // ---- Adjustments ----

        'get_stock_adjustments.php': ApiRouter.branchWriteOnly((p) => {
            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            const data = DemoDB.table('stock_adjustments')
                .filter(r => branchId === null || eq(r.branch_id, branchId))
                .map(decorateAdjustment)
                .sort(byNewest);
            return ApiRouter.ok('OK', { data });
        }),

        'add_stock_adjustment.php': ApiRouter.branchWriteOnly((p) => {
            const productId = Number(p.product_id || 0);
            const qty       = num(p.quantity);
            const finalQty  = String(p.direction || 'add') === 'subtract' ? -Math.abs(qty) : Math.abs(qty);

            if (productId <= 0 || !product(productId)) return ApiRouter.fail(ADJ_ERR.PRODUCT_NOT_FOUND);
            if (finalQty === 0)                        return ApiRouter.fail(ADJ_ERR.INVALID_QUANTITY);

            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            if (branchId !== null && !liveBranch(branchId)) return ApiRouter.fail(ADJ_ERR.BRANCH_NOT_FOUND);
            if (stockOf(productId, branchId) + finalQty < 0) return ApiRouter.fail(ADJ_ERR.WOULD_GO_NEGATIVE);

            const id = DemoDB.insert('stock_adjustments', {
                product_id: productId, branch_id: branchId, quantity: finalQty,
                reason: String(p.reason || 'other').trim(),
                note:   String(p.note   || '').trim(),
                created_by: DemoAuth.current().id,
            });

            return ApiRouter.ok('স্টক সংশোধন সফল হয়েছে।', { id });
        }),

        'update_stock_adjustment.php': ApiRouter.branchWriteOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const denied = ApiRouter.ownBranchRecord('stock_adjustments', id);
            if (denied) return denied;

            const row = DemoDB.find('stock_adjustments', id);
            if (!row) return ApiRouter.fail(ADJ_ERR.NOT_FOUND);

            const productId = Number(p.product_id !== undefined ? p.product_id : row.product_id);
            const qty       = num(p.quantity);
            const finalQty  = String(p.direction || 'add') === 'subtract' ? -Math.abs(qty) : Math.abs(qty);

            if (productId <= 0 || !product(productId)) return ApiRouter.fail(ADJ_ERR.PRODUCT_NOT_FOUND);
            if (finalQty === 0)                        return ApiRouter.fail(ADJ_ERR.INVALID_QUANTITY);

            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            if (branchId !== null && !liveBranch(branchId)) return ApiRouter.fail(ADJ_ERR.BRANCH_NOT_FOUND);

            // Credit back this record's own contribution before re-checking.
            const sameSlot = eq(row.product_id, productId) &&
                             Number(row.branch_id || 0) === Number(branchId || 0);
            const old = sameSlot ? num(row.quantity) : 0;
            if (stockOf(productId, branchId) - old + finalQty < 0) {
                return ApiRouter.fail(ADJ_ERR.WOULD_GO_NEGATIVE);
            }

            DemoDB.update('stock_adjustments', id, {
                product_id: productId, branch_id: branchId, quantity: finalQty,
                reason: String(p.reason !== undefined ? p.reason : row.reason).trim(),
                note:   String(p.note   !== undefined ? p.note   : (row.note || '')).trim(),
            });

            return ApiRouter.ok('স্টক সংশোধন আপডেট হয়েছে।');
        }),

        'delete_stock_adjustment.php': ApiRouter.branchWriteOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const denied = ApiRouter.ownBranchRecord('stock_adjustments', id);
            if (denied) return denied;

            const row = DemoDB.find('stock_adjustments', id);
            if (!row) return ApiRouter.fail(ADJ_ERR.NOT_FOUND);

            const branchId = optionalBranch(row.branch_id);
            if (stockOf(row.product_id, branchId) - num(row.quantity) < 0) {
                return ApiRouter.fail(ADJ_ERR.WOULD_GO_NEGATIVE);
            }

            DemoDB.remove('stock_adjustments', id);
            return ApiRouter.ok('স্টক সংশোধন রেকর্ড ডিলিট হয়েছে।');
        }),

        // ---- Branch transfers ----

        'get_stock_transfers.php': ApiRouter.branchWriteOnly((p) => {
            const branchId = DemoAuth.resolveBranchId(p.branch_id);
            const data = DemoDB.table('stock_transfers')
                .filter(r => branchId === null ||
                             eq(r.from_branch_id, branchId) || eq(r.to_branch_id, branchId))
                .map(decorateTransfer)
                .sort(byNewest);
            return ApiRouter.ok('OK', { data });
        }),

        'add_stock_transfer.php': ApiRouter.branchWriteOnly((p) => {
            const productId = Number(p.product_id || 0);
            const locked    = DemoAuth.lockedBranchId();
            const fromId    = Number(locked !== null ? locked : (p.from_branch_id || 0));
            const toId      = Number(p.to_branch_id || 0);
            const quantity  = num(p.quantity);

            if (productId <= 0 || !product(productId)) return ApiRouter.fail(ADJ_ERR.PRODUCT_NOT_FOUND);
            if (quantity <= 0)    return ApiRouter.fail(ADJ_ERR.INVALID_QUANTITY);
            if (fromId === toId)  return ApiRouter.fail(ADJ_ERR.SAME_BRANCH);
            if (!liveBranch(fromId)) return ApiRouter.fail(ADJ_ERR.FROM_BRANCH_NOT_FOUND);
            if (!liveBranch(toId))   return ApiRouter.fail(ADJ_ERR.TO_BRANCH_NOT_FOUND);
            if (Compute.branchStock(productId, fromId) < quantity) {
                return ApiRouter.fail(ADJ_ERR.INSUFFICIENT_STOCK);
            }

            // Legacy instant transfer: both sides move at once.
            const id = DemoDB.insert('stock_transfers', {
                product_id: productId, from_branch_id: fromId, to_branch_id: toId,
                quantity, status: 'received', transfer_date: DemoDB.today(),
                note: String(p.note || '').trim(),
                created_by: DemoAuth.current().id,
            });

            return ApiRouter.ok('স্টক ট্রান্সফার সফল হয়েছে।', { id });
        }),

        'update_stock_transfer.php': ApiRouter.branchWriteOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const denied = ApiRouter.ownTransferSide(id, 'from');
            if (denied) return denied;

            const row = DemoDB.find('stock_transfers', id);
            if (!row) return ApiRouter.fail(ADJ_ERR.NOT_FOUND);

            const locked    = DemoAuth.lockedBranchId();
            const productId = Number(p.product_id !== undefined ? p.product_id : row.product_id);
            const fromId    = Number(locked !== null ? locked
                                   : (p.from_branch_id !== undefined ? p.from_branch_id : row.from_branch_id));
            const toId      = Number(p.to_branch_id !== undefined ? p.to_branch_id : row.to_branch_id);
            const quantity  = p.quantity !== undefined ? num(p.quantity) : num(row.quantity);

            if (productId <= 0 || !product(productId)) return ApiRouter.fail(ADJ_ERR.PRODUCT_NOT_FOUND);
            if (quantity <= 0)   return ApiRouter.fail(ADJ_ERR.INVALID_QUANTITY);
            if (fromId === toId) return ApiRouter.fail(ADJ_ERR.SAME_BRANCH);
            if (!liveBranch(fromId)) return ApiRouter.fail(ADJ_ERR.FROM_BRANCH_NOT_FOUND);
            if (!liveBranch(toId))   return ApiRouter.fail(ADJ_ERR.TO_BRANCH_NOT_FOUND);

            // Credit the old quantity back to the source before checking it.
            let fromStock = Compute.branchStock(productId, fromId);
            if (eq(row.product_id, productId) && eq(row.from_branch_id, fromId)) {
                fromStock += num(row.quantity);
            }
            if (fromStock < quantity) return ApiRouter.fail(ADJ_ERR.INSUFFICIENT_STOCK);

            DemoDB.update('stock_transfers', id, {
                product_id: productId, from_branch_id: fromId, to_branch_id: toId, quantity,
                note: String(p.note !== undefined ? p.note : (row.note || '')).trim(),
            });

            return ApiRouter.ok('ট্রান্সফার আপডেট হয়েছে।');
        }),

        'delete_stock_transfer.php': ApiRouter.branchWriteOnly((p) => {
            const id = Number(p.id || 0);
            if (id <= 0) return ApiRouter.fail('সঠিক রেকর্ড নির্বাচন করুন।');

            const denied = ApiRouter.ownTransferSide(id, 'from');
            if (denied) return denied;

            const row = DemoDB.find('stock_transfers', id);
            if (!row) return ApiRouter.fail(ADJ_ERR.NOT_FOUND);

            // Deleting returns stock to the source and takes it off the destination.
            if (Compute.branchStock(row.product_id, row.to_branch_id) - num(row.quantity) < 0) {
                return ApiRouter.fail(ADJ_ERR.WOULD_GO_NEGATIVE);
            }

            DemoDB.remove('stock_transfers', id);
            return ApiRouter.ok('ট্রান্সফার রেকর্ড ডিলিট হয়েছে।');
        }),
    });

})();
