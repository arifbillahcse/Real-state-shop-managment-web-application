// ============================================
// Sales Management — New Sale + History
// ============================================

let rowCounter = 0;
let invoiceModal = null;

// ---- Helpers ----
function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

// Build product <option> HTML from a stock list
function buildProductOpts(stockList) {
    return stockList.map(p => {
        const pid   = p.product_id  ?? p.product_id;
        const name  = p.product_name;
        const price = p.sell_price;
        const wholesale = parseFloat(p.wholesale_price ?? 0) || 0;
        const stock = parseFloat(p.current_stock ?? 0);
        const unit  = p.unit;
        const disabled = stock <= 0 ? 'disabled' : '';
        return `<option value="${pid}" ${disabled}
                     data-price="${price}"
                     data-wholesale="${wholesale}"
                     data-stock="${stock}"
                     data-unit="${esc(unit)}">
            ${esc(name)} (স্টক: ${stock} ${esc(unit)})
         </option>`;
    }).join('');
}

let productOptsHtml = buildProductOpts(PRODUCTS);

// When branch changes, reload product stock from that branch
if (HAS_BRANCHES) {
    document.getElementById('saleBranchId')?.addEventListener('change', async function () {
        const branchId = this.value;
        // Reset all product selects
        document.querySelectorAll('.product-select').forEach(sel => {
            tsRebuild(sel, '<option value="">-- পণ্য নির্বাচন করুন --</option>' + productOptsHtml, sel.value);
        });

        if (!branchId) return;

        try {
            const res  = await fetch(`${BASE_URL}/api/get_branch_stock.php?branch_id=${branchId}`);
            const data = await res.json();
            if (!data.success) return;

            productOptsHtml = buildProductOpts(data.stock);
            document.querySelectorAll('.product-select').forEach(sel => {
                tsRebuild(sel, '<option value="">-- পণ্য নির্বাচন করুন --</option>' + productOptsHtml, sel.value);
            });
        } catch { /* keep global stock on error */ }
    });
}

// ---- Charge mode (combined vs per-item) ----
function salePerItemMode() {
    return document.getElementById('saleChargePerItem')?.checked === true;
}

function applySaleChargeMode() {
    const perItem = salePerItemMode();
    document.querySelectorAll('.sale-charge-col, .sale-charge-cell').forEach(el =>
        el.classList.toggle('d-none', !perItem));
    const combined = document.getElementById('combinedSaleCharges');
    if (combined) {
        // Delivery charge stays visible in both modes; hide only the 3 charge inputs
        combined.querySelectorAll('input').forEach(inp => {
            if (inp.id !== 'saleDelivery') {
                inp.closest('div.col-6, div.col-md-3, div[class*=col]')?.classList.toggle('d-none', perItem);
            }
        });
    }
    calcGrandTotal();
}
document.getElementById('saleChargeCombined')?.addEventListener('change', applySaleChargeMode);
document.getElementById('saleChargePerItem')?.addEventListener('change', applySaleChargeMode);

// ---- Item Rows ----
function addItemRow() {
    rowCounter++;
    const id = rowCounter;
    const perItem = salePerItemMode();

    const tr = document.createElement('tr');
    tr.id = 'item_row_' + id;
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm product-select" required
                    onchange="onProductSelect(this, ${id})">
                <option value="">-- পণ্য নির্বাচন করুন --</option>
                ${productOptsHtml}
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm rate-type-select" data-no-search="1"
                    onchange="onRateTypeChange(this, ${id})">
                <option value="retail">খুচরা</option>
                <option value="wholesale">পাইকারি</option>
                <option value="custom">কাস্টম</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm qty-input"
                   min="0.01" step="0.01" placeholder="০" required
                   oninput="calcRow(${id})">
            <small class="text-muted unit-label"></small>
            <small class="text-danger d-block stock-warn"></small>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm price-input"
                   min="0.01" step="0.01" placeholder="০.০০" required
                   oninput="calcRow(${id})">
        </td>
        <td class="sale-charge-cell ${perItem ? '' : 'd-none'}">
            <input type="number" class="form-control form-control-sm item-unload"
                   min="0" step="0.01" value="0" oninput="calcRow(${id})">
        </td>
        <td class="sale-charge-cell ${perItem ? '' : 'd-none'}">
            <input type="number" class="form-control form-control-sm item-labor"
                   min="0" step="0.01" value="0" oninput="calcRow(${id})">
        </td>
        <td class="sale-charge-cell ${perItem ? '' : 'd-none'}">
            <input type="number" class="form-control form-control-sm item-transport"
                   min="0" step="0.01" value="0" oninput="calcRow(${id})">
        </td>
        <td class="text-end fw-semibold row-total-cell" id="row_total_${id}">—</td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="removeRow(${id})">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    checkEmptyState();
}

function removeRow(id) {
    document.getElementById('item_row_' + id)?.remove();
    checkEmptyState();
    calcGrandTotal();
}

function checkEmptyState() {
    const hasRows = document.querySelectorAll('#itemsBody tr').length > 0;
    document.getElementById('noItemsAlert').classList.toggle('d-none', hasRows);
}

// Resolve price from the selected rate type (retail/wholesale/custom)
function applyRatePrice(row) {
    const sel  = row.querySelector('.product-select');
    const opt  = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    const rateType   = row.querySelector('.rate-type-select').value;
    const priceInput = row.querySelector('.price-input');
    if (rateType === 'retail') {
        priceInput.value = opt.dataset.price || '';
        priceInput.readOnly = false;
    } else if (rateType === 'wholesale') {
        const w = parseFloat(opt.dataset.wholesale || 0);
        if (w > 0) {
            priceInput.value = w;
        } else {
            showToast('এই পণ্যের পাইকারি দাম সেট করা নেই — খুচরা দাম ব্যবহার হচ্ছে', 'warning');
            priceInput.value = opt.dataset.price || '';
        }
        priceInput.readOnly = false;
    } else {
        priceInput.readOnly = false;
        priceInput.focus();
    }
}

function onProductSelect(sel, id) {
    const opt = sel.options[sel.selectedIndex];
    const row = document.getElementById('item_row_' + id);
    if (!row || !opt.value) return;
    applyRatePrice(row);
    row.querySelector('.unit-label').textContent =
        opt.dataset.unit ? '(' + opt.dataset.unit + ')' : '';
    calcRow(id);
}

function onRateTypeChange(sel, id) {
    const row = document.getElementById('item_row_' + id);
    if (!row) return;
    applyRatePrice(row);
    calcRow(id);
}

function rowLineTotal(tr) {
    const qty   = parseFloat(tr.querySelector('.qty-input')?.value)   || 0;
    const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
    let line = qty * price;
    if (salePerItemMode()) {
        line += (parseFloat(tr.querySelector('.item-unload')?.value)    || 0)
              + (parseFloat(tr.querySelector('.item-labor')?.value)     || 0)
              + (parseFloat(tr.querySelector('.item-transport')?.value) || 0);
    }
    return line;
}

function calcRow(id) {
    const row = document.getElementById('item_row_' + id);
    if (!row) return;
    const total = rowLineTotal(row);
    document.getElementById('row_total_' + id).textContent = total > 0 ? fmt(total) : '—';
    calcGrandTotal();
}

// ---- Stock warning (§৭: "স্টকের বেশি পরিমাণ দিলে সতর্কতা দেখাবে") ----
// Totalled per product, not per row: the same product listed twice draws from
// one shelf, so two rows of 6 against a stock of 10 is over the limit even
// though neither row is on its own.
function stockShortfalls() {
    const wanted = new Map(); // product_id -> {name, unit, stock, qty, rows[]}
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const sel = tr.querySelector('.product-select');
        const qty = parseFloat(tr.querySelector('.qty-input')?.value) || 0;
        if (!sel?.value || qty <= 0) return;
        const opt = sel.options[sel.selectedIndex];
        const key = sel.value;
        if (!wanted.has(key)) {
            wanted.set(key, {
                name:  (opt?.text || '').replace(/\s*\(স্টক:.*$/, '').trim(),
                unit:  opt?.dataset.unit || '',
                stock: parseFloat(opt?.dataset.stock ?? 0) || 0,
                qty:   0,
                rows:  [],
            });
        }
        const e = wanted.get(key);
        e.qty += qty;
        e.rows.push(tr);
    });
    return [...wanted.values()].filter(e => e.qty > e.stock);
}

function applyStockWarnings() {
    const short = stockShortfalls();
    const bad   = new Set();
    short.forEach(e => e.rows.forEach(tr => bad.add(tr)));

    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const input = tr.querySelector('.qty-input');
        const warn  = tr.querySelector('.stock-warn');
        const over  = bad.has(tr);
        input?.classList.toggle('is-invalid', over);
        if (warn) warn.textContent = over ? 'স্টকের চেয়ে বেশি' : '';
    });

    const box = document.getElementById('stockWarning');
    if (box) {
        box.classList.toggle('d-none', short.length === 0);
        box.innerHTML = short.length === 0 ? '' :
            '<i class="bi bi-exclamation-triangle me-1"></i>' +
            '<strong>স্টকের চেয়ে বেশি পরিমাণ দেওয়া হয়েছে:</strong><ul class="mb-0 mt-1 ps-3">' +
            short.map(e =>
                `<li>${esc(e.name)} — চাওয়া হয়েছে ${e.qty} ${esc(e.unit)}, স্টকে আছে ${e.stock} ${esc(e.unit)}</li>`
            ).join('') + '</ul>';
    }
    return short;
}

function calcGrandTotal() {
    applyStockWarnings();
    let subtotal = 0;       // items only (qty × price)
    let itemCharges = 0;    // per-item charges
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.qty-input')?.value)   || 0;
        const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
        subtotal += qty * price;
        if (salePerItemMode()) {
            itemCharges += (parseFloat(tr.querySelector('.item-unload')?.value)    || 0)
                         + (parseFloat(tr.querySelector('.item-labor')?.value)     || 0)
                         + (parseFloat(tr.querySelector('.item-transport')?.value) || 0);
        }
    });

    const perItem = salePerItemMode();
    const combinedCharges = perItem ? 0 :
          (parseFloat(document.getElementById('saleUnload')?.value)    || 0)
        + (parseFloat(document.getElementById('saleLabor')?.value)     || 0)
        + (parseFloat(document.getElementById('saleTransport')?.value) || 0);
    const delivery = parseFloat(document.getElementById('saleDelivery')?.value) || 0;
    const charges  = itemCharges + combinedCharges + delivery;

    // Discount: taka or percent of (subtotal + charges)
    const discInput = parseFloat(document.getElementById('discount').value) || 0;
    const isPercent = document.getElementById('discPercent')?.checked === true;
    const base      = subtotal + charges;
    const discountAmt = isPercent
        ? Math.min(base, base * Math.min(discInput, 100) / 100)
        : Math.min(base, discInput);
    const hint = document.getElementById('discountCalcHint');
    if (hint) hint.textContent = isPercent && discInput > 0 ? `= ${fmt(discountAmt)}` : '';

    const total = Math.max(0, base - discountAmt);
    const paid  = parseFloat(document.getElementById('paidAmount').value) || 0;
    const due   = Math.max(0, total - paid);

    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    const chargesEl = document.getElementById('chargesDisplay');
    if (chargesEl) chargesEl.textContent = fmt(charges);
    document.getElementById('totalDisplay').textContent = fmt(total);
    document.getElementById('dueDisplay').textContent   = fmt(due);

    const wordsEl = document.getElementById('totalInWords');
    if (wordsEl) {
        wordsEl.textContent = (total > 0 && typeof bnMoneyWords === 'function')
            ? 'কথায়: ' + bnMoneyWords(total) : '';
    }

    updatePreviousDue(due);
    updateDueLimitWarning(due);
    return { subtotal, charges, discountAmt, total, paid, due };
}

// Live due-limit hint under the totals
function updateDueLimitWarning(newDue) {
    const box = document.getElementById('dueLimitWarning');
    if (!box) return;
    const custId = document.getElementById('saleCustomerId')?.value;
    box.classList.add('d-none');
    if (!custId || newDue <= 0) return;
    const cust = (CUSTOMERS || []).find(c => String(c.id) === String(custId));
    if (!cust) return;
    const limit      = parseFloat(cust.due_limit || 0);
    const currentDue = parseFloat(cust.total_due || 0);
    if (limit <= 0) return;
    const projected = currentDue + newDue;
    if (projected > limit) {
        box.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>
            সতর্কতা: বর্তমান বাকি ${fmt(currentDue)} + নতুন বাকি ${fmt(newDue)}
            = ${fmt(projected)}, যা নির্ধারিত সীমা ${fmt(limit)} ছাড়িয়ে যাচ্ছে।
            বিক্রয় সম্পন্ন করতে ম্যানেজার অনুমোদন লাগবে।`;
        box.classList.remove('d-none');
    }
}
// Previous balance preview. It is shown on the memo only — the amount stays
// on the older invoices that actually carry it, so nothing is counted twice.
function updatePreviousDue(newDue) {
    const chk  = document.getElementById('includePrevDue');
    const wrap = document.getElementById('prevDueWrap');
    const custId = document.getElementById('saleCustomerId')?.value;
    const cust = (CUSTOMERS || []).find(c => String(c.id) === String(custId));
    const prev = cust ? parseFloat(cust.total_due || 0) : 0;

    if (wrap) wrap.classList.toggle('d-none', !(cust && prev > 0));
    const on = !!(chk && chk.checked && cust && prev > 0);
    document.getElementById('prevDueRow')?.classList.toggle('d-none', !on);
    document.getElementById('grandDueRow')?.classList.toggle('d-none', !on);
    if (on) {
        document.getElementById('prevDueDisplay').textContent  = fmt(prev);
        document.getElementById('grandDueDisplay').textContent = fmt(prev + newDue);
    }
    return on;
}

// The two blocks are mutually exclusive: typed buyer details only mean
// something without an account, and only an account has a khata to push to.
function applyCustomerMode() {
    const hasCustomer = !!document.getElementById('saleCustomerId')?.value;
    document.getElementById('walkInWrap')?.classList.toggle('d-none', hasCustomer);
    document.getElementById('addToLedgerWrap')?.classList.toggle('d-none', !hasCustomer);
    if (hasCustomer) {
        ['walkinName', 'walkinMobile', 'walkinAddress']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    } else {
        const chk = document.getElementById('addToLedger');
        if (chk) chk.checked = false;
    }
}

document.getElementById('saleCustomerId')?.addEventListener('change', () => {
    applyCustomerMode();
    calcGrandTotal();
});
applyCustomerMode();
document.getElementById('discTaka')?.addEventListener('change', () => calcGrandTotal());
document.getElementById('discPercent')?.addEventListener('change', () => calcGrandTotal());

function collectItems() {
    const perItem = salePerItemMode();
    const items = [];
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const sel   = tr.querySelector('.product-select');
        const qty   = tr.querySelector('.qty-input');
        const price = tr.querySelector('.price-input');
        if (sel?.value && parseFloat(qty?.value) > 0 && parseFloat(price?.value) > 0) {
            items.push({
                product_id: parseInt(sel.value),
                quantity:   parseFloat(qty.value),
                unit_price: parseFloat(price.value),
                rate_type:  tr.querySelector('.rate-type-select')?.value || 'retail',
                unload_bill:    perItem ? (parseFloat(tr.querySelector('.item-unload')?.value)    || 0) : 0,
                labor_bill:     perItem ? (parseFloat(tr.querySelector('.item-labor')?.value)     || 0) : 0,
                transport_bill: perItem ? (parseFloat(tr.querySelector('.item-transport')?.value) || 0) : 0,
            });
        }
    });
    return items;
}

// ---- Submit Sale ----
let _pendingApproval = null; // sale payload awaiting manager approval

function buildSalePayload() {
    const items = collectItems();
    if (items.length === 0) return null;
    const totals    = calcGrandTotal();
    const perItem   = salePerItemMode();
    return {
        customer_id:    document.getElementById('saleCustomerId').value,
        branch_id:      document.getElementById('saleBranchId')?.value || '',
        sale_date:      document.getElementById('saleDate').value,
        discount:       totals.discountAmt.toFixed(2),
        discount_note:  document.getElementById('discountNote')?.value || '',
        paid_amount:    document.getElementById('paidAmount').value,
        payment_method: document.getElementById('paymentMethod').value,
        note:           document.getElementById('saleNote').value,
        unload_bill:    perItem ? 0 : (document.getElementById('saleUnload')?.value    || 0),
        labor_bill:     perItem ? 0 : (document.getElementById('saleLabor')?.value     || 0),
        transport_bill: perItem ? 0 : (document.getElementById('saleTransport')?.value || 0),
        delivery_charge: document.getElementById('saleDelivery')?.value || 0,
        include_previous_due: document.getElementById('includePrevDue')?.checked ? 1 : '',
        add_to_ledger:  document.getElementById('addToLedger')?.checked ? 1 : '',
        sold_by_name:   document.getElementById('soldByName')?.value || '',
        sold_by_mobile: document.getElementById('soldByMobile')?.value || '',
        walkin_name:    document.getElementById('walkinName')?.value || '',
        walkin_mobile:  document.getElementById('walkinMobile')?.value || '',
        walkin_address: document.getElementById('walkinAddress')?.value || '',
        items:          JSON.stringify(items),
    };
}

function sendSale(data) {
    const btn = document.getElementById('submitSaleBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>অপেক্ষা করুন...';

    ajaxPost(BASE_URL + '/api/create_sale.php', data, res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>বিক্রয় সম্পন্ন করুন';

        if (res.success) {
            showToast(res.message + (res.invoice_number ? ' — ' + res.invoice_number : ''), 'success');
            resetSaleForm();
            if (res.sale_id) showInvoice(res.sale_id);
            return;
        }
        // Over-limit credit sale → ask for manager approval, then retry
        if (res.code === 'LIMIT_EXCEEDED') {
            _pendingApproval = data;
            document.getElementById('approvalInfo').innerHTML =
                `বর্তমান বাকি: <strong>${fmt(res.current_due)}</strong><br>
                 নির্ধারিত সীমা: <strong>${fmt(res.due_limit)}</strong><br>
                 সীমার বেশি বাকিতে বিক্রয় করতে ম্যানেজারের অনুমোদন দিন।`;
            document.getElementById('approverUsername').value = '';
            document.getElementById('approverPassword').value = '';
            document.getElementById('approvalError').classList.add('d-none');
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
            return;
        }
        showToast(res.message, 'danger');
    });
}

function submitSale(e) {
    e.preventDefault();
    const data = buildSalePayload();
    if (!data) {
        showToast('কমপক্ষে একটি পণ্য যোগ করুন', 'danger');
        return;
    }
    // Sale::createSale refuses this anyway; naming the product here beats a
    // round trip that comes back saying only "পর্যাপ্ত স্টক নেই"।
    const short = applyStockWarnings();
    if (short.length) {
        showToast(
            `${short[0].name} — স্টকে আছে ${short[0].stock} ${short[0].unit}, ` +
            `চাওয়া হয়েছে ${short[0].qty} ${short[0].unit}`, 'danger');
        document.getElementById('stockWarning')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    sendSale(data);
}

// ---- New buyer (§৭: নতুন ক্রেতা → ফুল / শর্ট একাউন্ট) ----
let _newCustomerModal = null;

function ncIsShort() {
    return document.getElementById('ncTypeShort')?.checked === true;
}

function applyNcType() {
    const short = ncIsShort();
    document.querySelectorAll('.nc-full-only')
        .forEach(el => el.classList.toggle('d-none', short));
    document.querySelectorAll('.nc-short-only')
        .forEach(el => el.classList.toggle('d-none', !short));
}

function openNewCustomerModal() {
    ['ncName', 'ncPhone', 'ncAddress', 'ncBookNo', 'ncWhatsapp', 'ncImo']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const lim = document.getElementById('ncDueLimit');
    if (lim) lim.value = 0;
    const full = document.getElementById('ncTypeFull');
    if (full) full.checked = true;
    applyNcType();
    document.getElementById('ncError')?.classList.add('d-none');
    _newCustomerModal ??= new bootstrap.Modal(document.getElementById('newCustomerModal'));
    _newCustomerModal.show();
}

document.getElementById('ncTypeFull')?.addEventListener('change', applyNcType);
document.getElementById('ncTypeShort')?.addEventListener('change', applyNcType);

function saveNewCustomer() {
    const err = document.getElementById('ncError');
    err.classList.add('d-none');

    const name    = document.getElementById('ncName').value.trim();
    const phone   = document.getElementById('ncPhone').value.trim();
    const address = document.getElementById('ncAddress').value.trim();
    const bookNo  = document.getElementById('ncBookNo').value.trim();

    // §৫ marks all four বাধ্যতামূলক, for a short account as much as a full one.
    const missing = [];
    if (!name)    missing.push('নাম');
    if (!phone)   missing.push('মোবাইল নাম্বার');
    if (!address) missing.push('ঠিকানা');
    if (!bookNo)  missing.push('বই নাম্বার');
    if (missing.length) {
        err.textContent = missing.join(', ') + ' দিন।';
        err.classList.remove('d-none');
        return;
    }

    const short = ncIsShort();
    const btn = document.getElementById('btnSaveNewCustomer');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/add_customer.php`, {
        name, phone, address,
        book_no:      bookNo,
        account_type: short ? 'short' : 'full',
        whatsapp:     short ? '' : document.getElementById('ncWhatsapp').value.trim(),
        imo:          short ? '' : document.getElementById('ncImo').value.trim(),
        due_limit:    short ? 0  : (document.getElementById('ncDueLimit').value || 0),
    }, res => {
        btn.disabled = false;
        if (!res.success) {
            err.textContent = res.message;
            err.classList.remove('d-none');
            return;
        }
        addCustomerToSale(res.id, name, phone, short ? 0 : parseFloat(
            document.getElementById('ncDueLimit').value || 0));
        _newCustomerModal?.hide();
        showToast(res.message + (res.account_no ? ' — একাউন্ট নং ' + res.account_no : ''), 'success');
    });
}

// Put the fresh account into the dropdown and select it, so the sale carries
// straight on. CUSTOMERS backs the due-limit and previous-balance hints, so
// the new row goes in there too — with no dues, being brand new.
function addCustomerToSale(id, name, phone, dueLimit) {
    if (typeof CUSTOMERS !== 'undefined') {
        CUSTOMERS.push({ id, name, phone, due_limit: dueLimit, total_due: 0 });
    }
    const sel = document.getElementById('saleCustomerId');
    if (!sel) return;
    const label = name + (phone ? ' — ' + phone : '');
    if (sel.tomselect) {
        sel.tomselect.addOption({ value: String(id), text: label });
        sel.tomselect.refreshOptions(false);
        sel.tomselect.setValue(String(id));
    } else {
        const opt = document.createElement('option');
        opt.value = String(id);
        opt.textContent = label;
        sel.appendChild(opt);
        sel.value = String(id);
    }
    applyCustomerMode();
    calcGrandTotal();
}

function confirmApproval() {
    if (!_pendingApproval) return;
    const username = document.getElementById('approverUsername').value.trim();
    const password = document.getElementById('approverPassword').value;
    const errBox   = document.getElementById('approvalError');
    if (!username || !password) {
        errBox.textContent = 'ইউজারনেম ও পাসওয়ার্ড দিন।';
        errBox.classList.remove('d-none');
        return;
    }
    const data = { ..._pendingApproval, approver_username: username, approver_password: password };
    bootstrap.Modal.getInstance(document.getElementById('approvalModal'))?.hide();
    _pendingApproval = null;
    sendSale(data);
}

// ---- Share invoice via WhatsApp / SMS ----
function shareInvoice(channel) {
    const res = window._lastInvoiceRes;
    if (!res || !res.data) { showToast('আগে ইনভয়েস লোড করুন', 'warning'); return; }
    const s = res.data;
    const lines = [
        `${res.shop_name}`,
        `ইনভয়েস: ${s.invoice_number}`,
        `তারিখ: ${s.sale_date}`,
        `কাস্টমার: ${s.customer_name}`,
        ...s.items.map(it => `- ${it.product_name} ${parseFloat(it.quantity)} ${it.unit} × ${parseFloat(it.unit_price)} = ${parseFloat(it.total_price)}`),
        `মোট: ${parseFloat(s.total_amount)} ৳`,
        `পরিশোধ: ${parseFloat(s.paid_amount)} ৳`,
        `বাকি: ${parseFloat(s.due_amount)} ৳`,
    ];
    const text  = encodeURIComponent(lines.join('\n'));
    const phone = String(s.customer_phone || '').replace(/[^0-9]/g, '');

    if (channel === 'whatsapp') {
        const target = phone ? (phone.startsWith('88') ? phone : '88' + phone) : '';
        window.open(`https://wa.me/${target}?text=${text}`, '_blank');
    } else if (channel === 'sms') {
        window.open(`sms:${phone}?body=${text}`, '_self');
    } else if (channel === 'imo') {
        // Imo publishes no web share link the way WhatsApp does with wa.me, so
        // hand the text to the device's own share sheet — Imo shows up there
        // when it is installed. Where that is unavailable (most desktops),
        // copy it instead so it can be pasted into Imo by hand.
        shareViaDevice(lines.join('\n'));
    }
}

function resetSaleForm() {
    document.getElementById('saleForm').reset();
    tsSyncForm('saleForm');
    document.getElementById('saleDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('itemsBody').innerHTML = '';
    rowCounter = 0;
    // Reset product opts to global stock after form reset
    productOptsHtml = buildProductOpts(PRODUCTS);
    checkEmptyState();
    applyCustomerMode();
    calcGrandTotal();
    // Whoever is logged in served this sale by default; still editable.
    const sb = document.getElementById('soldByName');
    if (sb && !sb.value && typeof CURRENT_USER_NAME === 'string') sb.value = CURRENT_USER_NAME;
    addItemRow(); // start with one empty row
}

// ---- Pagination state ----
const PAGE_SIZE   = 50;
let _allSales     = [];
let _currentPage  = 1;

// ---- Sales History ----
function loadSalesHistory() {
    const params = new URLSearchParams();
    const dateFrom   = document.getElementById('filterDateFrom').value;
    const dateTo     = document.getElementById('filterDateTo').value;
    const customerId = document.getElementById('filterCustomer').value;
    const branchId   = document.getElementById('filterBranch')?.value
                       || (IS_STAFF && STAFF_BRANCH ? String(STAFF_BRANCH) : '');
    const status     = document.getElementById('filterStatus')?.value || '';
    if (dateFrom)   params.set('date_from',   dateFrom);
    if (dateTo)     params.set('date_to',     dateTo);
    if (customerId) params.set('customer_id', customerId);
    if (branchId)   params.set('branch_id',   branchId);
    if (status)     params.set('status',      status);

    document.getElementById('salesBody').innerHTML =
        '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...</td></tr>';
    document.getElementById('salesPaginationBar').style.display = 'none';

    fetch(BASE_URL + '/api/get_sales.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                _allSales    = res.data;
                _currentPage = 1;
                renderSalesPage(_currentPage);
            }
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderSalesPage(page) {
    _currentPage = page;
    const cols  = HAS_BRANCHES ? 10 : 9;
    const tbody = document.getElementById('salesBody');

    if (!_allSales.length) {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-5 text-muted">কোনো বিক্রয় রেকর্ড নেই</td></tr>`;
        document.getElementById('salesPaginationBar').style.display = 'none';
        return;
    }

    const totalPages = Math.ceil(_allSales.length / PAGE_SIZE);
    const start      = (page - 1) * PAGE_SIZE;
    const pageData   = _allSales.slice(start, start + PAGE_SIZE);

    tbody.innerHTML = pageData.map(s => {
        const cancelled = s.status === 'cancelled';
        // Once the khata owns this memo, Sale.php refuses to edit or cancel it
        // — so the buttons come off rather than failing when clicked.
        const inKhata = s.ledger_id !== null && s.ledger_id !== undefined;
        const branchCell = HAS_BRANCHES
            ? `<td>${s.branch_name ? `<span class="badge bg-secondary"><i class="bi bi-shop me-1"></i>${esc(s.branch_name)}</span>` : '<span class="text-muted">—</span>'}</td>`
            : '';
        return `
        <tr class="${cancelled ? 'text-muted' : ''}">
            <td class="fw-semibold">${esc(s.invoice_number)}</td>
            <td>${s.sale_date}</td>
            <td>${esc(s.customer_name)}</td>
            ${branchCell}
            <td class="text-center">
                <span class="badge bg-secondary">${s.item_count}</span>
            </td>
            <td class="text-end">${fmt(s.total_amount)}</td>
            <td class="text-end">${fmt(s.paid_amount)}</td>
            <td class="text-end ${!cancelled && parseFloat(s.due_amount) > 0 ? 'text-danger fw-semibold' : ''}">
                ${fmt(s.due_amount)}
            </td>
            <td class="text-center">
                <span class="badge bg-${cancelled ? 'secondary' : 'success'}">
                    ${cancelled ? 'বাতিল' : 'সম্পন্ন'}
                </span>
                ${inKhata ? '<div class="badge bg-info text-dark mt-1">খাতায় যুক্ত</div>' : ''}
            </td>
            <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-info me-1"
                        onclick="showInvoice(${s.id})" title="ইনভয়েস দেখুন">
                    <i class="bi bi-file-text"></i>
                </button>
                ${IS_ADMIN && !IS_STAFF && !cancelled && !inKhata ? `
                <button class="btn btn-sm btn-outline-warning me-1"
                        onclick="openEditSale(${s.id})"
                        title="সম্পাদনা করুন">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger"
                        onclick="cancelSale(${s.id}, '${esc(s.invoice_number)}')"
                        title="বাতিল করুন">
                    <i class="bi bi-x-circle"></i>
                </button>` : ''}
            </td>
        </tr>`;
    }).join('');

    // Pagination bar
    const bar  = document.getElementById('salesPaginationBar');
    const info = document.getElementById('salesPageInfo');
    const nav  = document.getElementById('salesPagination');

    const from = start + 1;
    const to   = Math.min(start + PAGE_SIZE, _allSales.length);
    info.textContent = `${_allSales.length} টির মধ্যে ${from}–${to} দেখাচ্ছে`;

    if (totalPages <= 1) {
        bar.style.display = 'none';
        return;
    }

    bar.style.removeProperty('display');

    let pages = '';
    pages += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault();renderSalesPage(${page - 1})">&#8249;</a></li>`;

    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
            if (i === 3 || i === totalPages - 2) pages += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        pages += `<li class="page-item ${i === page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault();renderSalesPage(${i})">${i}</a></li>`;
    }

    pages += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault();renderSalesPage(${page + 1})">&#8250;</a></li>`;

    nav.innerHTML = pages;
}

function renderSalesTable(sales) {
    _allSales    = sales;
    _currentPage = 1;
    renderSalesPage(1);
}

function cancelSale(id, invoiceNo) {
    if (!confirm(`ইনভয়েস #${invoiceNo} বাতিল করবেন?\nএই কাজটি পূর্বাবস্থায় ফেরানো যাবে না।`)) return;
    ajaxPost(BASE_URL + '/api/cancel_sale.php', { id }, res => {
        showToast(res.message, res.success ? 'warning' : 'danger');
        if (res.success) loadSalesHistory();
    });
}

// ---- Invoice ----
function showInvoice(saleId) {
    document.getElementById('invoiceContent').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    if (!invoiceModal) {
        invoiceModal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    }
    invoiceModal.show();

    fetch(BASE_URL + '/api/get_sale_detail.php?id=' + saleId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                document.getElementById('invoiceContent').innerHTML =
                    `<div class="alert alert-danger">${esc(res.message)}</div>`;
                return;
            }
            window._lastInvoiceRes = res;
            renderInvoice(res);
        })
        .catch(() => showToast('ইনভয়েস লোড করতে সমস্যা হয়েছে', 'danger'));
}

function buildInvoiceHTML(res, forPrint = false) {
    const s = res.data;
    const payLabel = { cash: 'নগদ', credit: 'বাকি', mobile_banking: 'মোবাইল ব্যাংকিং', cheque: 'চেক' };
    const due      = parseFloat(s.due_amount);
    const isPaid   = due <= 0;
    const isCancelled = s.status === 'cancelled';

    const itemRows = s.items.map((item, i) => `
        <tr style="background:${i%2===0?'#fff':'#fafafa'}">
            <td style="padding:10px 14px;border-bottom:1px solid #eee;font-weight:600;color:#222">${esc(item.product_name)}</td>
            <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:center;color:#555">${parseFloat(item.quantity)} ${esc(item.unit)}</td>
            <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:right;color:#555">${fmt(item.unit_price)}</td>
            <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:right;font-weight:700;color:#c0392b">${fmt(item.total_price)}</td>
        </tr>`).join('');

    const chargeRows = [
        ['আনলোড বিল',    s.unload_bill],
        ['লেবার বিল',     s.labor_bill],
        ['গাড়িভাড়া',      s.transport_bill],
        ['ডেলিভারি চার্জ', s.delivery_charge],
    ].filter(([, v]) => parseFloat(v || 0) > 0).map(([label, v]) => `
        <tr>
            <td style="padding:5px 16px 5px 0;color:#888;font-size:13px">${label}</td>
            <td style="padding:5px 0;text-align:right;color:#555;font-size:13px">+ ${fmt(v)}</td>
        </tr>`).join('');

    const discountRow = parseFloat(s.discount) > 0 ? `
        <tr>
            <td style="padding:5px 16px 5px 0;color:#888;font-size:13px">ছাড়${s.discount_note ? ` <span style="color:#bbb">(${esc(s.discount_note)})</span>` : ''}</td>
            <td style="padding:5px 0;text-align:right;color:#e74c3c;font-size:13px">− ${fmt(s.discount)}</td>
        </tr>` : '';

    const totalWords = (typeof bnMoneyWords === 'function')
        ? `<tr><td colspan="2" style="padding:4px 0;text-align:right;color:#999;font-size:11px;font-style:italic">
             কথায়: ${bnMoneyWords(parseFloat(s.total_amount))}</td></tr>`
        : '';

    return `
    <div id="printArea" style="font-family:'Hind Siliguri','Segoe UI',sans-serif;max-width:680px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:${forPrint?'none':'0 4px 24px rgba(0,0,0,0.13)'}">

        <!-- Header gradient -->
        <div style="background:linear-gradient(135deg,#c0392b 0%,#8e1a0e 100%);padding:28px 32px 22px;position:relative;overflow:hidden">
            <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.06)"></div>
            <div style="position:absolute;bottom:-50px;left:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04)"></div>
            <div style="position:relative;z-index:1;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:1px;text-shadow:0 1px 4px rgba(0,0,0,0.3)">${esc(res.shop_name)}</div>
                ${res.shop_address ? `<div style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:4px">${esc(res.shop_address)}</div>` : ''}
                ${res.shop_phone   ? `<div style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:2px">&#9990; ${esc(res.shop_phone)}</div>` : ''}
            </div>
        </div>

        <!-- Tear-line divider -->
        <div style="display:flex;align-items:center;background:#f8f8f8;border-top:2px dashed #ddd;border-bottom:2px dashed #ddd;padding:0 12px">
            <div style="width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 0 0 2px #ddd;flex-shrink:0;margin-left:-22px"></div>
            <div style="flex:1;text-align:center;padding:6px 0;font-size:11px;font-weight:700;letter-spacing:3px;color:#aaa;text-transform:uppercase">ইনভয়েস</div>
            <div style="width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 0 0 2px #ddd;flex-shrink:0;margin-right:-22px"></div>
        </div>

        <!-- Invoice meta + customer -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:20px 32px 12px;gap:16px">
            <div style="flex:1">
                <div style="font-size:11px;font-weight:700;color:#aaa;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">ইনভয়েস তথ্য</div>
                <table style="border-collapse:collapse;font-size:13px">
                    <tr><td style="color:#888;padding:2px 12px 2px 0;white-space:nowrap">ইনভয়েস নং</td>
                        <td style="font-weight:700;color:#c0392b">${esc(s.invoice_number)}</td></tr>
                    <tr><td style="color:#888;padding:2px 12px 2px 0">তারিখ</td>
                        <td style="color:#333">${s.sale_date}</td></tr>
                    ${s.branch_name ? `<tr><td style="color:#888;padding:2px 12px 2px 0">ব্রাঞ্চ</td>
                        <td style="color:#333">${esc(s.branch_name)}</td></tr>` : ''}
                    <tr><td style="color:#888;padding:2px 12px 2px 0">পেমেন্ট</td>
                        <td style="color:#333">${payLabel[s.payment_method] || s.payment_method}</td></tr>
                </table>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-size:11px;font-weight:700;color:#aaa;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">কাস্টমার</div>
                <div style="font-weight:700;font-size:15px;color:#222">${esc(s.customer_name)}</div>
                ${s.customer_address ? `<div style="color:#888;font-size:12px;margin-top:2px">${esc(s.customer_address)}</div>` : ''}
                ${s.customer_phone ? `<div style="color:#888;font-size:13px;margin-top:2px">&#9990; ${esc(s.customer_phone)}</div>` : ''}
                <div style="margin-top:8px">
                    <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;
                        background:${isCancelled?'#ecf0f1':isPaid?'#e8f8f0':'#fff3f3'};
                        color:${isCancelled?'#7f8c8d':isPaid?'#27ae60':'#c0392b'};
                        border:1.5px solid ${isCancelled?'#bdc3c7':isPaid?'#a9dfbf':'#f5b7b1'}">
                        ${isCancelled?'বাতিল':isPaid?'✓ পরিশোধিত':'● বাকি আছে'}
                    </span>
                </div>
            </div>
        </div>

        <!-- Items table -->
        <div style="padding:0 32px 8px">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:linear-gradient(90deg,#c0392b,#e74c3c)">
                        <th style="padding:10px 14px;text-align:left;color:#fff;font-size:12px;font-weight:700;letter-spacing:1px;border-radius:6px 0 0 0">পণ্য</th>
                        <th style="padding:10px 14px;text-align:center;color:#fff;font-size:12px;font-weight:700;letter-spacing:1px">পরিমাণ</th>
                        <th style="padding:10px 14px;text-align:right;color:#fff;font-size:12px;font-weight:700;letter-spacing:1px">একক মূল্য</th>
                        <th style="padding:10px 14px;text-align:right;color:#fff;font-size:12px;font-weight:700;letter-spacing:1px;border-radius:0 6px 0 0">মোট</th>
                    </tr>
                </thead>
                <tbody>${itemRows}</tbody>
            </table>
        </div>

        <!-- Totals -->
        <div style="display:flex;justify-content:flex-end;padding:8px 32px 20px">
            <table style="min-width:260px;border-collapse:collapse;font-size:14px">
                <tr>
                    <td style="padding:5px 16px 5px 0;color:#888">সাবটোটাল</td>
                    <td style="padding:5px 0;text-align:right;color:#333">${fmt(s.subtotal)}</td>
                </tr>
                ${chargeRows}
                ${discountRow}
                <tr style="border-top:2px solid #eee">
                    <td style="padding:8px 16px 8px 0;font-weight:800;font-size:15px;color:#222">মোট</td>
                    <td style="padding:8px 0;text-align:right;font-weight:800;font-size:15px;color:#222">${fmt(s.total_amount)}</td>
                </tr>
                ${totalWords}
                <tr>
                    <td style="padding:5px 16px 5px 0;color:#27ae60;font-weight:600">পরিশোধ</td>
                    <td style="padding:5px 0;text-align:right;color:#27ae60;font-weight:600">${fmt(s.paid_amount)}</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:4px 0">
                        <div style="background:${due>0?'linear-gradient(90deg,#c0392b,#e74c3c)':'linear-gradient(90deg,#27ae60,#2ecc71)'};
                                    border-radius:8px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center">
                            <span style="color:#fff;font-weight:700;font-size:14px">${due>0?'বাকি':'সম্পূর্ণ পরিশোধ'}</span>
                            <span style="color:#fff;font-weight:900;font-size:18px">${due>0?fmt(due):'✓'}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        ${(s.previous_due !== null && s.previous_due !== undefined) ? `
        <div style="margin:0 32px 12px;padding:10px 14px;background:#fdf3f3;border-left:3px solid #c0392b;border-radius:0 6px 6px 0;font-size:13px">
            <div style="display:flex;justify-content:space-between"><span>এই মেমোর বাকি</span><strong>${fmt(due)}</strong></div>
            <div style="display:flex;justify-content:space-between"><span>পূর্বের বাকি</span><strong>${fmt(s.previous_due)}</strong></div>
            <div style="display:flex;justify-content:space-between;border-top:1px solid #e0b4b4;margin-top:5px;padding-top:5px">
                <span style="font-weight:700">সর্বমোট দেয়</span>
                <strong>${fmt(due + parseFloat(s.previous_due))}</strong></div>
        </div>` : ''}

        ${s.sold_by_name ? `
        <div style="margin:0 32px 10px;font-size:13px;color:#555">
            <strong>বিক্রয়কারী:</strong> ${esc(s.sold_by_name)}${s.sold_by_mobile ? ' — ' + esc(s.sold_by_mobile) : ''}
        </div>` : ''}

        ${s.note ? `
        <div style="margin:0 32px 16px;padding:10px 14px;background:#fffbf0;border-left:3px solid #f39c12;border-radius:0 6px 6px 0;font-size:13px;color:#7f6a00">
            <strong>নোট:</strong> ${esc(s.note)}
        </div>` : ''}

        <!-- Footer -->
        <div style="background:#1a1a1a;padding:14px 32px;text-align:center">
            <div style="color:#888;font-size:12px;letter-spacing:1px">ধন্যবাদ আপনার কেনাকাটার জন্য</div>
            ${res.shop_phone ? `<div style="color:#aaa;font-size:12px;margin-top:3px">&#9990; ${esc(res.shop_phone)}</div>` : ''}
        </div>

    </div>`;
}

function renderInvoice(res) {
    document.getElementById('invoiceContent').innerHTML = buildInvoiceHTML(res, false);
}

function printInvoice() {
    const res = window._lastInvoiceRes;
    if (!res) return;
    const win = window.open('', '_blank', 'width=780,height=900');
    win.document.write(`<!DOCTYPE html>
    <html><head>
    <meta charset="UTF-8">
    <title>Invoice — ${esc(res.data.invoice_number)}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important; }
        body { background: #f0f0f0; padding: 24px; font-family: 'Hind Siliguri','Segoe UI',sans-serif; }
        @media print {
            body { background: #fff; padding: 0; }
            #printArea { box-shadow: none !important; border-radius: 0 !important; }
        }
    </style>
    </head><body>
    ${buildInvoiceHTML(res, true)}
    <script>window.onload = function(){ window.print(); window.close(); };<\/script>
    </body></html>`);
    win.document.close();
}

// ---- Edit Sale ----
let editSaleRowCount = 0;
let editSaleModal = null;

function openEditSale(saleId) {
    if (saleId) {
        fetch(BASE_URL + '/api/get_sale_detail.php?id=' + saleId)
            .then(r => r.json())
            .then(res => { if (res.success) { window._lastInvoiceRes = res; openEditSale(); } else showToast(res.message, 'danger') });
        return;
    }
    const res = window._lastInvoiceRes;
    if (!res) return;
    const s = res.data;
    if (s.status === 'cancelled') { showToast('বাতিল বিক্রয় সম্পাদনা করা যাবে না।','warning'); return; }

    if (!editSaleModal) editSaleModal = new bootstrap.Modal(document.getElementById('editSaleModal'));

    document.getElementById('esSaleId').value      = s.id;
    document.getElementById('esSaleDate').value    = s.sale_date;
    document.getElementById('esDiscount').value    = parseFloat(s.discount)||0;
    document.getElementById('esPaid').value        = parseFloat(s.paid_amount)||0;
    document.getElementById('esNote').value        = s.note||'';
    document.getElementById('esPayMethod').value   = s.payment_method||'cash';

    const custSel = document.getElementById('esCustomer');
    custSel.value = s.customer_id || 0;

    const tbody = document.getElementById('editSaleItemsBody');
    tbody.innerHTML = '';
    editSaleRowCount = 0;
    (s.items||[]).forEach(it => addEditSaleRow(it));
    calcEditSaleTotal();

    if (invoiceModal) invoiceModal.hide();
    editSaleModal.show();
}

function addEditSaleRow(prefill = null) {
    editSaleRowCount++;
    const n    = editSaleRowCount;
    const opts = PRODUCTS.map(p =>
        `<option value="${p.product_id}" data-price="${p.sell_price}" data-name="${esc(p.product_name)}">
            ${esc(p.product_name)}</option>`
    ).join('');
    const tr = document.createElement('tr');
    tr.id = 'esrow' + n;
    tr.innerHTML = `
        <td><select class="form-select form-select-sm" onchange="onEditSaleProductChange(this,${n})">
            <option value="">-- পণ্য --</option>${opts}</select></td>
        <td><input type="number" class="form-control form-control-sm" id="esqty${n}"
                   value="${prefill ? prefill.quantity : 1}" min="0.01" step="0.01"
                   oninput="calcEditSaleRow(${n});calcEditSaleTotal()"></td>
        <td><input type="number" class="form-control form-control-sm" id="esprice${n}"
                   value="${prefill ? prefill.unit_price : 0}" min="0" step="0.01"
                   oninput="calcEditSaleRow(${n});calcEditSaleTotal()"></td>
        <td class="align-middle fw-semibold" id="esrowtotal${n}">০.০০ ৳</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="document.getElementById('esrow${n}').remove();calcEditSaleTotal()">
                <i class="bi bi-x"></i></button></td>`;
    document.getElementById('editSaleItemsBody').appendChild(tr);

    // MutationObserver is async — explicitly init Tom Select and set value
    const sel = tr.querySelector('select');
    if (typeof initTomSelect === 'function') initTomSelect(sel);
    if (prefill?.product_id) {
        const val = String(prefill.product_id);
        if (sel.tomselect) sel.tomselect.setValue(val, true);
        else sel.value = val;
    }

    calcEditSaleRow(n);
}
function onEditSaleProductChange(sel, n) {
    const opt = sel.selectedOptions[0];
    if (opt?.dataset.price) document.getElementById('esprice'+n).value = opt.dataset.price;
    calcEditSaleRow(n); calcEditSaleTotal();
}
function calcEditSaleRow(n) {
    const q  = parseFloat(document.getElementById('esqty'+n)?.value||0);
    const p  = parseFloat(document.getElementById('esprice'+n)?.value||0);
    const el = document.getElementById('esrowtotal'+n);
    if (el) el.textContent = (q*p).toFixed(2)+' ৳';
}
function calcEditSaleTotal() {
    let sub = 0;
    document.querySelectorAll('#editSaleItemsBody tr').forEach(tr => {
        const n = tr.id.replace('esrow','');
        sub += parseFloat(document.getElementById('esqty'+n)?.value||0)
             * parseFloat(document.getElementById('esprice'+n)?.value||0);
    });
    const disc = parseFloat(document.getElementById('esDiscount')?.value||0);
    document.getElementById('esSubtotal').textContent = sub.toFixed(2)+' ৳';
    document.getElementById('esTotal').textContent    = Math.max(0,sub-disc).toFixed(2)+' ৳';
}
function submitEditSale(e) {
    e.preventDefault();
    const id    = document.getElementById('esSaleId').value;
    const items = [];
    document.querySelectorAll('#editSaleItemsBody tr').forEach(tr => {
        const n   = tr.id.replace('esrow','');
        const sel = tr.querySelector('select');
        const pid = parseInt(sel?.value||0);
        if (!pid) return;
        const opt   = sel.selectedOptions[0];
        const qty   = parseFloat(document.getElementById('esqty'+n)?.value||0);
        const price = parseFloat(document.getElementById('esprice'+n)?.value||0);
        if (qty > 0 && price > 0)
            items.push({product_id:pid, product_name:opt?.dataset.name||'', quantity:qty, unit_price:price});
    });
    const data = {
        id,
        customer_id:    document.getElementById('esCustomer').value,
        sale_date:      document.getElementById('esSaleDate').value,
        payment_method: document.getElementById('esPayMethod').value,
        discount:       document.getElementById('esDiscount').value,
        paid_amount:    document.getElementById('esPaid').value,
        note:           document.getElementById('esNote').value.trim(),
        items:          JSON.stringify(items),
    };
    const btn = document.getElementById('editSaleSaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/update_sale.php',{method:'POST',body:new URLSearchParams(data)})
        .then(r=>r.json()).then(res=>{
            btn.disabled = false;
            if (res.success) {
                editSaleModal.hide();
                showToast(res.message,'success');
                loadSalesHistory();
            } else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

// ---- Toggle between New Sale form and Sales History ----
function toggleSaleView() {
    const newPane  = document.getElementById('newSaleTab');
    const histPane = document.getElementById('historyTab');
    const btn      = document.getElementById('btnToggleSaleView');
    if (!newPane || !histPane || !btn) return;

    const showingNew = newPane.classList.contains('active');
    if (showingNew) {
        // Switch to history
        newPane.classList.remove('show', 'active');
        histPane.classList.add('show', 'active');
        btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>নতুন বিক্রয়';
        loadSalesHistory();
    } else {
        // Switch to new sale form
        histPane.classList.remove('show', 'active');
        newPane.classList.add('show', 'active');
        btn.innerHTML = '<i class="bi bi-list-ul me-1"></i>বিক্রয় ইতিহাস';
    }
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
    if (!IS_STAFF) {
        addItemRow();
        calcGrandTotal();
    }
    // History tab is always default — load on page load
    loadSalesHistory();
});


// Share arbitrary text through the device share sheet, falling back to the
// clipboard. Used for apps that offer no web share URL (Imo).
async function shareViaDevice(text, title) {
    if (navigator.share) {
        try {
            await navigator.share({ title: title || 'ইনভয়েস', text });
            return;
        } catch (err) {
            if (err && err.name === 'AbortError') return;   // user closed the sheet
        }
    }
    try {
        await navigator.clipboard.writeText(text);
        showToast('টেক্সট কপি হয়েছে — Imo খুলে পেস্ট করুন।', 'info');
    } catch {
        showToast('শেয়ার করা যায়নি। ইনভয়েসটি প্রিন্ট করে পাঠান।', 'warning');
    }
}
