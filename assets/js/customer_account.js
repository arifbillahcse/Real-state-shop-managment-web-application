// ============================================
// Customer Account Dashboard (খাতা/লেজার)
// ============================================

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

const TYPE_LABELS = {
    goods:          ['মালামাল',      'primary'],
    deposit:        ['টাকা জমা',     'success'],
    money_return:   ['টাকা ফেরত',    'danger'],
    product_return: ['রিটার্ন পণ্য', 'warning'],
    expense:        ['অন্যান্য খরচ', 'secondary'],
    due_transfer:   ['হিসাব ট্রান্সফার', 'info'],
    opening:        ['পূর্বের জের',  'dark'],
};

let _entries = [];
let _agreements = [];
let _currentAgreement = null;

// ── Load everything ──────────────────────────────────────────────────────────
async function loadLedger() {
    try {
        const res  = await fetch(`${BASE_URL}/api/ledger_get.php?customer_id=${CUSTOMER_ID}`);
        const data = await res.json();
        if (!data.success) return;

        _entries = data.entries || [];
        renderLedger();
        renderDrafts();
        renderSummary(data.product_summary || []);
        updateBalance(parseFloat(data.balance || 0));
    } catch { /* keep old view */ }
}

function updateBalance(bal) {
    const el = document.getElementById('balanceDisplay');
    el.textContent = fmt(Math.abs(bal));
    el.className = 'fw-bold mb-0 ' + (bal > 0 ? 'text-danger' : 'text-success');
    document.getElementById('balanceLabel').textContent =
        bal > 0 ? 'বাকি আছে' : (bal < 0 ? 'অগ্রিম জমা' : 'পরিশোধিত');
}

function entryDescription(e) {
    const [label, color] = TYPE_LABELS[e.entry_type] || [e.entry_type, 'light'];
    let html = `<span class="badge bg-${color}">${label}</span>`;
    if (e.items && e.items.length) {
        html += ' <small>' + e.items.map(it =>
            `${esc(it.product_name)} ${parseFloat(it.quantity)} ${esc(it.unit)} × ${parseFloat(it.unit_price)}`
        ).join(', ') + '</small>';
    }
    const extras = [];
    if (parseFloat(e.unload_bill) > 0)    extras.push(`আনলোড ${fmt(e.unload_bill)}`);
    if (parseFloat(e.labor_bill) > 0)     extras.push(`লেবার ${fmt(e.labor_bill)}`);
    if (parseFloat(e.transport_bill) > 0) extras.push(`ভাড়া ${fmt(e.transport_bill)}`);
    if (extras.length) html += ` <small class="text-muted">(${extras.join(', ')})</small>`;
    if (e.note)        html += ` <small class="text-muted">— ${esc(e.note)}</small>`;
    if (e.received_by) html += ` <small class="text-muted">[গ্রহণ: ${esc(e.received_by)}]</small>`;
    return html;
}

function renderLedger() {
    const tbody  = document.getElementById('ledgerBody');
    const finals = _entries.filter(e => e.status === 'final');
    if (!finals.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">কোনো লেনদেন নেই</td></tr>';
        return;
    }
    tbody.innerHTML = finals.map(e => `
        <tr>
            <td class="text-nowrap">${esc(e.entry_date)}</td>
            <td>${entryDescription(e)}</td>
            <td class="text-end">${parseFloat(e.debit)  > 0 ? fmt(e.debit)  : '—'}</td>
            <td class="text-end">${parseFloat(e.credit) > 0 ? fmt(e.credit) : '—'}</td>
            <td class="text-end fw-semibold ${parseFloat(e.running_balance) > 0 ? 'text-danger' : 'text-success'}">
                ${fmt(e.running_balance)}
            </td>
        </tr>`).join('');
}

function renderDrafts() {
    const drafts = _entries.filter(e => e.status === 'pending');
    document.getElementById('draftCount').textContent = drafts.length;
    const wrap = document.getElementById('draftList');
    if (!drafts.length) {
        wrap.innerHTML = '<p class="text-muted text-center py-4">কোনো খসড়া মেমো নেই</p>';
        return;
    }
    wrap.innerHTML = drafts.map(e => `
        <div class="card shadow-sm mb-2">
            <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="badge bg-warning text-dark me-1">খসড়া</span>
                    <strong>${esc(e.entry_date)}</strong> — ${fmt(e.debit)}
                    <div class="small text-muted">${entryDescription(e)}</div>
                </div>
                ${CAN_WRITE ? `
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success" onclick="finalizeDraft(${e.id})">
                        <i class="bi bi-check-lg me-1"></i>একাউন্টে যুক্ত করুন
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteDraft(${e.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>` : ''}
            </div>
        </div>`).join('');
}

function renderSummary(rows) {
    const tbody = document.getElementById('summaryBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">কোনো পণ্য নেওয়া হয়নি</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="fw-semibold">${esc(r.product_name)}</td>
            <td class="text-end">${parseFloat(r.taken_qty)} ${esc(r.unit)}</td>
            <td class="text-end">${parseFloat(r.returned_qty)} ${esc(r.unit)}</td>
            <td class="text-end">${fmt(r.taken_value)}</td>
        </tr>`).join('');
}

async function finalizeDraft(id) {
    const date = prompt('কোন তারিখে একাউন্টে যুক্ত হবে? (YYYY-MM-DD)', new Date().toISOString().slice(0, 10));
    if (!date) return;
    ajaxPost(`${BASE_URL}/api/ledger_finalize_draft.php`, { id, entry_date: date }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadLedger();
    });
}

function deleteDraft(id) {
    if (!confirm('খসড়া মেমোটি ডিলিট করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/ledger_delete_draft.php`, { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadLedger();
    });
}

// ── Goods entry modal ────────────────────────────────────────────────────────
const goodsModal = new bootstrap.Modal(document.getElementById('goodsModal'));
let gRowCounter = 0;
let gFinalizeMode = true;

function productOptions() {
    return '<option value="">— পণ্য —</option>' + PRODUCTS.map(p =>
        `<option value="${p.id}" data-unit="${esc(p.unit)}" data-price="${p.sell_price}" data-name="${esc(p.name)}">${esc(p.name)}</option>`
    ).join('') + '<option value="0" data-unit="" data-price="0" data-name="">অন্যান্য (নিজে লিখুন)</option>';
}

function openGoodsModal(finalize) {
    gFinalizeMode = finalize;
    document.getElementById('goodsModalTitle').innerHTML = finalize
        ? '<i class="bi bi-cart-plus me-2"></i>মালামাল এন্ট্রি'
        : '<i class="bi bi-journal-text me-2"></i>খসড়া মেমো';
    document.getElementById('gItemsBody').innerHTML = '';
    document.getElementById('gDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('gNote').value = '';
    document.getElementById('gUnload').value = 0;
    document.getElementById('gLabor').value = 0;
    document.getElementById('gTransport').value = 0;
    document.getElementById('chargeCombined').checked = true;
    document.getElementById('gError').classList.add('d-none');
    applyChargeMode();
    addGoodsRow();
    calcGoodsTotal();
    goodsModal.show();
}

function applyChargeMode() {
    const perItem = document.getElementById('chargePerItem').checked;
    document.querySelectorAll('.charge-col, .charge-cell').forEach(el =>
        el.classList.toggle('d-none', !perItem));
    document.getElementById('combinedChargesRow').classList.toggle('d-none', perItem);
    calcGoodsTotal();
}
document.getElementById('chargeCombined').addEventListener('change', applyChargeMode);
document.getElementById('chargePerItem').addEventListener('change', applyChargeMode);

function addGoodsRow() {
    gRowCounter++;
    const id = gRowCounter;
    const perItem = document.getElementById('chargePerItem').checked;
    const tr = document.createElement('tr');
    tr.id = `g_row_${id}`;
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm g-product" onchange="onGProductChange(this, ${id})">
                ${productOptions()}
            </select>
            <input type="text" class="form-control form-control-sm mt-1 g-custom-name d-none" placeholder="পণ্যের নাম লিখুন">
        </td>
        <td><input type="number" class="form-control form-control-sm g-qty" min="0.01" step="0.01" oninput="calcGoodsTotal()"></td>
        <td><input type="number" class="form-control form-control-sm g-price" min="0" step="0.01" oninput="calcGoodsTotal()"></td>
        <td class="charge-cell ${perItem ? '' : 'd-none'}"><input type="number" class="form-control form-control-sm g-unload" min="0" step="0.01" value="0" oninput="calcGoodsTotal()"></td>
        <td class="charge-cell ${perItem ? '' : 'd-none'}"><input type="number" class="form-control form-control-sm g-labor" min="0" step="0.01" value="0" oninput="calcGoodsTotal()"></td>
        <td class="charge-cell ${perItem ? '' : 'd-none'}"><input type="number" class="form-control form-control-sm g-transport" min="0" step="0.01" value="0" oninput="calcGoodsTotal()"></td>
        <td class="text-end align-middle g-line-total">০.০০</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('g_row_${id}').remove();calcGoodsTotal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>`;
    document.getElementById('gItemsBody').appendChild(tr);
}

function onGProductChange(sel, id) {
    const tr  = document.getElementById(`g_row_${id}`);
    const opt = sel.selectedOptions[0];
    const customName = tr.querySelector('.g-custom-name');
    if (sel.value === '0') {
        customName.classList.remove('d-none');
    } else {
        customName.classList.add('d-none');
        tr.querySelector('.g-price').value = opt?.dataset.price || '';
    }
    calcGoodsTotal();
}

function calcGoodsTotal() {
    const perItem = document.getElementById('chargePerItem').checked;
    let total = 0;
    document.querySelectorAll('#gItemsBody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.g-qty').value)   || 0;
        const price = parseFloat(tr.querySelector('.g-price').value) || 0;
        let line = qty * price;
        if (perItem) {
            line += (parseFloat(tr.querySelector('.g-unload').value)    || 0)
                  + (parseFloat(tr.querySelector('.g-labor').value)     || 0)
                  + (parseFloat(tr.querySelector('.g-transport').value) || 0);
        }
        tr.querySelector('.g-line-total').textContent = line.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        total += line;
    });
    if (!perItem) {
        total += (parseFloat(document.getElementById('gUnload').value)    || 0)
               + (parseFloat(document.getElementById('gLabor').value)     || 0)
               + (parseFloat(document.getElementById('gTransport').value) || 0);
    }
    document.getElementById('gGrandTotal').textContent = fmt(total);
}

function collectGoodsItems() {
    const perItem = document.getElementById('chargePerItem').checked;
    const items = [];
    for (const tr of document.querySelectorAll('#gItemsBody tr')) {
        const sel = tr.querySelector('.g-product');
        if (!sel.value && sel.value !== '0') continue;
        const opt  = sel.selectedOptions[0];
        const name = sel.value === '0'
            ? tr.querySelector('.g-custom-name').value.trim()
            : (opt?.dataset.name || '');
        const qty   = parseFloat(tr.querySelector('.g-qty').value)   || 0;
        const price = parseFloat(tr.querySelector('.g-price').value) || 0;
        if (!name || qty <= 0) continue;
        items.push({
            product_id:    sel.value === '0' ? 0 : parseInt(sel.value),
            product_name:  name,
            quantity:      qty,
            unit:          opt?.dataset.unit || '',
            unit_price:    price,
            unload_bill:    perItem ? (parseFloat(tr.querySelector('.g-unload').value)    || 0) : 0,
            labor_bill:     perItem ? (parseFloat(tr.querySelector('.g-labor').value)     || 0) : 0,
            transport_bill: perItem ? (parseFloat(tr.querySelector('.g-transport').value) || 0) : 0,
        });
    }
    return items;
}

function saveGoods(finalize) {
    const err   = document.getElementById('gError');
    err.classList.add('d-none');
    const items = collectGoodsItems();
    if (!items.length) {
        err.textContent = 'কমপক্ষে একটি পণ্য (নাম, পরিমাণ, দর সহ) যোগ করুন।';
        err.classList.remove('d-none');
        return;
    }
    const perItem = document.getElementById('chargePerItem').checked;
    const btn = finalize ? document.getElementById('btnSaveGoods') : document.getElementById('btnSaveDraft');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/ledger_add_goods.php`, {
        customer_id:    CUSTOMER_ID,
        entry_date:     document.getElementById('gDate').value,
        items:          JSON.stringify(items),
        unload_bill:    perItem ? 0 : (document.getElementById('gUnload').value    || 0),
        labor_bill:     perItem ? 0 : (document.getElementById('gLabor').value     || 0),
        transport_bill: perItem ? 0 : (document.getElementById('gTransport').value || 0),
        note:           document.getElementById('gNote').value,
        finalize:       finalize ? '1' : '0',
    }, res => {
        btn.disabled = false;
        if (res.success) {
            goodsModal.hide();
            showToast(res.message, 'success');
            loadLedger();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

// ── Deposit ──────────────────────────────────────────────────────────────────
const depositModal = new bootstrap.Modal(document.getElementById('depositModal'));
function openDepositModal() {
    document.getElementById('dAmount').value = '';
    document.getElementById('dNote').value = '';
    document.getElementById('dDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('dError').classList.add('d-none');
    depositModal.show();
}
function saveDeposit() {
    const err = document.getElementById('dError');
    err.classList.add('d-none');
    const btn = document.getElementById('btnSaveDeposit');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/ledger_add_deposit.php`, {
        customer_id: CUSTOMER_ID,
        entry_date:  document.getElementById('dDate').value,
        amount:      document.getElementById('dAmount').value,
        method:      document.getElementById('dMethod').value,
        note:        document.getElementById('dNote').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            depositModal.hide();
            showToast(res.message, 'success');
            loadLedger();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

// ── Money return ─────────────────────────────────────────────────────────────
const moneyReturnModal = new bootstrap.Modal(document.getElementById('moneyReturnModal'));
function openMoneyReturnModal() {
    document.getElementById('mrAmount').value = '';
    document.getElementById('mrReason').value = '';
    document.getElementById('mrReceivedBy').value = '';
    document.getElementById('mrDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('mrError').classList.add('d-none');
    moneyReturnModal.show();
}
function saveMoneyReturn() {
    const err = document.getElementById('mrError');
    err.classList.add('d-none');
    const btn = document.getElementById('btnSaveMoneyReturn');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/ledger_add_money_return.php`, {
        customer_id: CUSTOMER_ID,
        entry_date:  document.getElementById('mrDate').value,
        amount:      document.getElementById('mrAmount').value,
        reason:      document.getElementById('mrReason').value,
        received_by: document.getElementById('mrReceivedBy').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            moneyReturnModal.hide();
            showToast(res.message, 'success');
            loadLedger();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

// ── Product return ───────────────────────────────────────────────────────────
const productReturnModal = new bootstrap.Modal(document.getElementById('productReturnModal'));
let prItems = [];

function openProductReturnModal() {
    prItems = [];
    renderPrItems();
    document.getElementById('prDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('prNote').value = '';
    document.getElementById('prQty').value = '';
    tsSet(document.getElementById('prProduct'), '', true);
    document.getElementById('prError').classList.add('d-none');
    resetPrRates();
    productReturnModal.show();
}

function resetPrRates() {
    const sel = document.getElementById('prRate');
    tsRebuild(sel, '<option value="">— পণ্য নির্বাচন করুন —</option>', '');
}

document.getElementById('prProduct').addEventListener('change', async function () {
    const productId = this.value;
    const rateSel = document.getElementById('prRate');
    if (!productId) { resetPrRates(); return; }
    tsRebuild(rateSel, '<option value="">লোড হচ্ছে...</option>', '');
    try {
        const res  = await fetch(`${BASE_URL}/api/ledger_product_rates.php?customer_id=${CUSTOMER_ID}&product_id=${productId}`);
        const data = await res.json();
        const rates = (data.rates) || [];
        let html = '';
        if (rates.length) {
            html = rates.map(r =>
                `<option value="${r.rate}">${parseFloat(r.rate)} ৳ (${esc(r.used_on)}, ${esc(r.source)})</option>`
            ).join('') + '<option value="custom">কাস্টম রেট…</option>';
        } else {
            html = '<option value="custom">পূর্বের রেট নেই — কাস্টম রেট দিন</option>';
        }
        tsRebuild(rateSel, html, rates.length ? String(rates[0].rate) : 'custom');
    } catch {
        tsRebuild(rateSel, '<option value="custom">কাস্টম রেট…</option>', 'custom');
    }
});

function addReturnItem() {
    const err = document.getElementById('prError');
    err.classList.add('d-none');
    const prodSel = document.getElementById('prProduct');
    const opt = prodSel.selectedOptions[0];
    if (!prodSel.value) { err.textContent = 'পণ্য নির্বাচন করুন।'; err.classList.remove('d-none'); return; }
    let rate = document.getElementById('prRate').value;
    if (rate === 'custom' || rate === '') {
        rate = prompt('রিটার্ন রেট লিখুন (৳):');
        if (rate === null || isNaN(parseFloat(rate))) return;
    }
    const qty = parseFloat(document.getElementById('prQty').value);
    if (!qty || qty <= 0) { err.textContent = 'পরিমাণ দিন।'; err.classList.remove('d-none'); return; }

    prItems.push({
        product_id:   parseInt(prodSel.value),
        product_name: opt.dataset.name,
        unit:         opt.dataset.unit,
        quantity:     qty,
        unit_price:   parseFloat(rate),
    });
    document.getElementById('prQty').value = '';
    renderPrItems();
}

function renderPrItems() {
    const tbody = document.getElementById('prItemsBody');
    if (!prItems.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">কোনো পণ্য যোগ হয়নি</td></tr>';
        document.getElementById('prTotal').textContent = 'মোট ফেরত: ০.০০ ৳';
        return;
    }
    let total = 0;
    tbody.innerHTML = prItems.map((it, i) => {
        const line = it.quantity * it.unit_price;
        total += line;
        return `<tr>
            <td>${esc(it.product_name)}</td>
            <td class="text-end">${it.quantity} ${esc(it.unit)}</td>
            <td class="text-end">${fmt(it.unit_price)}</td>
            <td class="text-end">${fmt(line)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="prItems.splice(${i},1);renderPrItems()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
    document.getElementById('prTotal').textContent = 'মোট ফেরত: ' + fmt(total);
}

function saveProductReturn() {
    const err = document.getElementById('prError');
    err.classList.add('d-none');
    if (!prItems.length) {
        err.textContent = 'কমপক্ষে একটি পণ্য যোগ করুন।';
        err.classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnSaveProductReturn');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/ledger_add_product_return.php`, {
        customer_id: CUSTOMER_ID,
        entry_date:  document.getElementById('prDate').value,
        items:       JSON.stringify(prItems),
        note:        document.getElementById('prNote').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            productReturnModal.hide();
            showToast(res.message, 'success');
            loadLedger();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

// ── Expense ──────────────────────────────────────────────────────────────────
const expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
function openExpenseModal() {
    document.getElementById('eAmount').value = '';
    document.getElementById('eDescription').value = '';
    document.getElementById('eDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('eError').classList.add('d-none');
    expenseModal.show();
}
function saveExpense() {
    const err = document.getElementById('eError');
    err.classList.add('d-none');
    const btn = document.getElementById('btnSaveExpense');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/ledger_add_expense.php`, {
        customer_id: CUSTOMER_ID,
        entry_date:  document.getElementById('eDate').value,
        amount:      document.getElementById('eAmount').value,
        description: document.getElementById('eDescription').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            expenseModal.hide();
            showToast(res.message, 'success');
            loadLedger();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

// ── Agreements ───────────────────────────────────────────────────────────────
const agreementModal = new bootstrap.Modal(document.getElementById('agreementModal'));
const agViewModal    = new bootstrap.Modal(document.getElementById('agViewModal'));
let agRowCounter = 0;

async function loadAgreements() {
    try {
        const res  = await fetch(`${BASE_URL}/api/get_agreements.php?customer_id=${CUSTOMER_ID}`);
        const data = await res.json();
        _agreements = (data.agreements) || [];
        document.getElementById('agrCount').textContent = _agreements.length;
        renderAgreements();
    } catch { /* noop */ }
}

const AG_STATUS = {
    active:    ['সক্রিয়',   'primary'],
    completed: ['সম্পন্ন',   'success'],
    cancelled: ['বাতিল',    'secondary'],
};

function renderAgreements() {
    const wrap = document.getElementById('agreementList');
    if (!_agreements.length) {
        wrap.innerHTML = '<p class="text-muted text-center py-4">কোনো চুক্তিপত্র নেই</p>';
        return;
    }
    wrap.innerHTML = _agreements.map(a => {
        const [label, color] = AG_STATUS[a.status] || [a.status, 'light'];
        const delivered = a.items.every(it => parseFloat(it.delivered_qty) >= parseFloat(it.quantity));
        return `
        <div class="card shadow-sm mb-2">
            <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>${esc(a.agreement_no)}</strong>
                    <span class="badge bg-${color} ms-1">${label}</span>
                    ${delivered && a.status === 'active' ? '<span class="badge bg-success ms-1">ডেলিভারি সম্পূর্ণ</span>' : ''}
                    <div class="small text-muted">
                        ${esc(a.agreement_date)} · মোট: ${fmt(a.total_amount)} · জমা: ${fmt(a.deposit_amount)}
                        · ${a.items.length}টি পণ্য · ${a.deliveries.length}টি ডেলিভারি
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewAgreement(${a.id})">
                        <i class="bi bi-eye me-1"></i>বিস্তারিত
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function openAgreementModal() {
    document.getElementById('agItemsBody').innerHTML = '';
    document.getElementById('agDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('agDeposit').value = 0;
    document.getElementById('agMethod').value = '';
    document.getElementById('agNote').value = '';
    document.getElementById('agError').classList.add('d-none');
    addAgRow();
    calcAgTotal();
    agreementModal.show();
}

function addAgRow() {
    agRowCounter++;
    const id = agRowCounter;
    const tr = document.createElement('tr');
    tr.id = `ag_row_${id}`;
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm ag-product" onchange="onAgProductChange(this, ${id})">
                ${productOptions()}
            </select>
            <input type="text" class="form-control form-control-sm mt-1 ag-custom-name d-none" placeholder="পণ্যের নাম লিখুন">
        </td>
        <td><input type="number" class="form-control form-control-sm ag-qty" min="0.01" step="0.01" oninput="calcAgTotal()"></td>
        <td><input type="number" class="form-control form-control-sm ag-price" min="0" step="0.01" oninput="calcAgTotal()"></td>
        <td class="text-end align-middle ag-line-total">০.০০</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('ag_row_${id}').remove();calcAgTotal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>`;
    document.getElementById('agItemsBody').appendChild(tr);
}

function onAgProductChange(sel, id) {
    const tr = document.getElementById(`ag_row_${id}`);
    const customName = tr.querySelector('.ag-custom-name');
    if (sel.value === '0') {
        customName.classList.remove('d-none');
    } else {
        customName.classList.add('d-none');
        tr.querySelector('.ag-price').value = sel.selectedOptions[0]?.dataset.price || '';
    }
    calcAgTotal();
}

function calcAgTotal() {
    let total = 0;
    document.querySelectorAll('#agItemsBody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.ag-qty').value)   || 0;
        const price = parseFloat(tr.querySelector('.ag-price').value) || 0;
        const line  = qty * price;
        tr.querySelector('.ag-line-total').textContent = line.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        total += line;
    });
    document.getElementById('agGrandTotal').textContent = fmt(total);
    document.getElementById('agTotalWords').textContent = total > 0 ? 'কথায়: ' + bnMoneyWords(total) : '';
}

function saveAgreement() {
    const err = document.getElementById('agError');
    err.classList.add('d-none');
    const items = [];
    for (const tr of document.querySelectorAll('#agItemsBody tr')) {
        const sel = tr.querySelector('.ag-product');
        if (!sel.value && sel.value !== '0') continue;
        const opt  = sel.selectedOptions[0];
        const name = sel.value === '0'
            ? tr.querySelector('.ag-custom-name').value.trim()
            : (opt?.dataset.name || '');
        const qty   = parseFloat(tr.querySelector('.ag-qty').value)   || 0;
        const price = parseFloat(tr.querySelector('.ag-price').value) || 0;
        if (!name || qty <= 0) continue;
        items.push({
            product_id:   sel.value === '0' ? 0 : parseInt(sel.value),
            product_name: name, quantity: qty,
            unit: opt?.dataset.unit || '', unit_price: price,
        });
    }
    if (!items.length) {
        err.textContent = 'কমপক্ষে একটি পণ্য যোগ করুন।';
        err.classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnSaveAgreement');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/add_agreement.php`, {
        customer_id:    CUSTOMER_ID,
        agreement_date: document.getElementById('agDate').value,
        items:          JSON.stringify(items),
        deposit_amount: document.getElementById('agDeposit').value || 0,
        deposit_method: document.getElementById('agMethod').value,
        note:           document.getElementById('agNote').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            agreementModal.hide();
            showToast(res.message, 'success');
            loadAgreements();
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
}

function viewAgreement(id) {
    const a = _agreements.find(x => parseInt(x.id) === parseInt(id));
    if (!a) return;
    _currentAgreement = a;
    document.getElementById('agvNo').textContent = a.agreement_no;
    const [label, color] = AG_STATUS[a.status] || [a.status, 'light'];

    document.getElementById('agvBody').innerHTML = `
        <div class="d-flex flex-wrap justify-content-between mb-3 gap-2">
            <div>
                <span class="badge bg-${color}">${label}</span>
                <span class="ms-2 text-muted small">তারিখ: ${esc(a.agreement_date)}</span>
            </div>
            ${CAN_WRITE && a.status === 'active' ? `
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-success" onclick="setAgreementStatus(${a.id}, 'completed')">
                    <i class="bi bi-check-lg me-1"></i>সম্পন্ন
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="setAgreementStatus(${a.id}, 'cancelled')">
                    বাতিল
                </button>
            </div>` : ''}
        </div>

        <h6 class="fw-semibold">অগ্রিম ক্রয়কৃত পণ্য</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr><th>পণ্য</th><th class="text-end">পরিমাণ</th><th class="text-end">দাম</th>
                        <th class="text-end">গুণফল</th><th class="text-end">ডেলিভারি হয়েছে</th><th class="text-end">বাকি</th></tr>
                </thead>
                <tbody>
                    ${a.items.map(it => {
                        const remaining = parseFloat(it.quantity) - parseFloat(it.delivered_qty);
                        return `<tr>
                            <td>${esc(it.product_name)}</td>
                            <td class="text-end">${parseFloat(it.quantity)} ${esc(it.unit)}</td>
                            <td class="text-end">${fmt(it.unit_price)}</td>
                            <td class="text-end">${fmt(it.line_total)}</td>
                            <td class="text-end">${parseFloat(it.delivered_qty)} ${esc(it.unit)}</td>
                            <td class="text-end ${remaining > 0 ? 'text-danger fw-semibold' : 'text-success'}">
                                ${remaining} ${esc(it.unit)}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <small class="text-muted d-block">সর্বমোট</small>
                <strong>${fmt(a.total_amount)}</strong>
                <small class="text-muted d-block">${bnMoneyWords(parseFloat(a.total_amount))}</small>
            </div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <small class="text-muted d-block">জমাকৃত টাকা</small>
                <strong class="text-success">${fmt(a.deposit_amount)}</strong>
                <small class="text-muted d-block">${esc(a.deposit_method || '')}</small>
            </div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <small class="text-muted d-block">অবশিষ্ট</small>
                <strong class="text-danger">${fmt(parseFloat(a.total_amount) - parseFloat(a.deposit_amount))}</strong>
            </div></div>
        </div>

        <h6 class="fw-semibold">ডেলিভারি ট্র্যাকিং শিট</h6>
        ${CAN_WRITE && a.status === 'active' ? `
        <div class="card bg-light border mb-2">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">তারিখ</label>
                        <input type="date" class="form-control form-control-sm" id="advDate" value="${new Date().toISOString().slice(0,10)}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">পণ্য</label>
                        <select class="form-select form-select-sm" id="advProduct" data-no-search="1">
                            ${a.items.map(it => `<option value="${it.product_id || 0}" data-name="${esc(it.product_name)}" data-unit="${esc(it.unit)}">${esc(it.product_name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">পরিমাণ</label>
                        <input type="number" class="form-control form-control-sm" id="advQty" min="0.01" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">নোট</label>
                        <input type="text" class="form-control form-control-sm" id="advNote">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary btn-sm" onclick="addAgDelivery(${a.id})"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
            </div>
        </div>` : ''}
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr><th>তারিখ</th><th>পণ্য</th><th class="text-end">পরিমাণ</th><th>নোট</th><th>এন্ট্রি করেছেন</th></tr>
                </thead>
                <tbody>
                    ${a.deliveries.length ? a.deliveries.map(d => `
                        <tr>
                            <td>${esc(d.delivery_date)}</td>
                            <td>${esc(d.product_name)}</td>
                            <td class="text-end">${parseFloat(d.quantity)} ${esc(d.unit)}</td>
                            <td class="small text-muted">${esc(d.note || '—')}</td>
                            <td class="small">${esc(d.created_by_name || '—')}</td>
                        </tr>`).join('')
                    : '<tr><td colspan="5" class="text-center text-muted py-2">এখনো কোনো ডেলিভারি হয়নি</td></tr>'}
                </tbody>
            </table>
        </div>`;
    agViewModal.show();
}

function addAgDelivery(agreementId) {
    const prodSel = document.getElementById('advProduct');
    const opt = prodSel.selectedOptions[0];
    ajaxPost(`${BASE_URL}/api/add_agreement_delivery.php`, {
        agreement_id:  agreementId,
        delivery_date: document.getElementById('advDate').value,
        product_id:    prodSel.value,
        product_name:  opt.dataset.name,
        quantity:      document.getElementById('advQty').value,
        unit:          opt.dataset.unit,
        note:          document.getElementById('advNote').value,
    }, async res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            await loadAgreements();
            viewAgreement(agreementId);
        }
    });
}

function setAgreementStatus(id, status) {
    if (!confirm(status === 'completed' ? 'চুক্তিপত্রটি সম্পন্ন হিসেবে চিহ্নিত করবেন?' : 'চুক্তিপত্রটি বাতিল করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/update_agreement_status.php`, { id, status }, async res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            agViewModal.hide();
            loadAgreements();
        }
    });
}


// ── Print ledger (খাতা) ──────────────────────────────────────────────────────
function printLedger() {
    const finals = _entries.filter(e => e.status === 'final');
    const rows = finals.map(e => {
        const [label] = TYPE_LABELS[e.entry_type] || [e.entry_type];
        const itemsTxt = (e.items || []).map(it =>
            `${it.product_name} ${parseFloat(it.quantity)} ${it.unit} × ${parseFloat(it.unit_price)}`
        ).join(', ');
        return `<tr>
            <td>${esc(e.entry_date)}</td>
            <td>${label}${itemsTxt ? ' — ' + esc(itemsTxt) : ''}${e.note ? ' (' + esc(e.note) + ')' : ''}</td>
            <td style="text-align:right">${parseFloat(e.debit) > 0 ? parseFloat(e.debit).toLocaleString('en-IN', {minimumFractionDigits: 2}) : ''}</td>
            <td style="text-align:right">${parseFloat(e.credit) > 0 ? parseFloat(e.credit).toLocaleString('en-IN', {minimumFractionDigits: 2}) : ''}</td>
            <td style="text-align:right">${parseFloat(e.running_balance).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
        </tr>`;
    }).join('');

    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8">
        <title>খাতা — ${esc(CUSTOMER.name)}</title>
        <style>
            * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
            body { font-family: 'Hind Siliguri', sans-serif; padding: 24px; font-size: 13px; }
            h2, h4 { margin: 0; text-align: center; }
            .meta { text-align: center; color: #555; margin-bottom: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #999; padding: 5px 8px; }
            th { background: #eee; }
        </style></head><body>
        <h2>${esc(SHOP.name)}</h2>
        <div class="meta">${esc(SHOP.address)} · ${esc(SHOP.phone)}</div>
        <h4>কাস্টমার খাতা — ${esc(CUSTOMER.name)} ${CUSTOMER.account_no ? '(' + esc(CUSTOMER.account_no) + ')' : ''}</h4>
        <div class="meta">${esc(CUSTOMER.phone || '')} · ${esc(CUSTOMER.address || '')}</div>
        <table>
            <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>ডেবিট (৳)</th><th>ক্রেডিট (৳)</th><th>ব্যালেন্স (৳)</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <script>window.onload = () => window.print();<\/script>
        </body></html>`);
    w.document.close();
}

function printAgreement() {
    const a = _currentAgreement;
    if (!a) return;
    const itemRows = a.items.map((it, i) => `
        <tr>
            <td style="text-align:center">${i + 1}</td>
            <td>${esc(it.product_name)}</td>
            <td style="text-align:right">${parseFloat(it.quantity)} ${esc(it.unit)}</td>
            <td style="text-align:right">${parseFloat(it.unit_price).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
            <td style="text-align:right">${parseFloat(it.line_total).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
        </tr>`).join('');
    const deliveryRows = a.deliveries.length ? a.deliveries.map(d => `
        <tr>
            <td>${esc(d.delivery_date)}</td>
            <td>${esc(d.product_name)}</td>
            <td style="text-align:right">${parseFloat(d.quantity)} ${esc(d.unit)}</td>
            <td>${esc(d.note || '')}</td>
        </tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#777">—</td></tr>';

    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8">
        <title>${esc(a.agreement_no)}</title>
        <style>
            * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
            body { font-family: 'Hind Siliguri', sans-serif; padding: 24px; font-size: 13px; }
            h2, h3 { margin: 0; text-align: center; }
            .meta { text-align: center; color: #555; margin-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #999; padding: 5px 8px; }
            th { background: #eee; }
            .sign { display: flex; justify-content: space-between; margin-top: 70px; }
            .sign div { border-top: 1px solid #333; padding-top: 4px; width: 200px; text-align: center; }
            .page2 { page-break-before: always; }
        </style></head><body>
        <h2>${esc(SHOP.name)}</h2>
        <div class="meta">${esc(SHOP.address)} · ${esc(SHOP.phone)}</div>
        <h3>অগ্রিম পণ্য ক্রয় রশিদ</h3>
        <div class="meta">নং: ${esc(a.agreement_no)} · তারিখ: ${esc(a.agreement_date)}</div>
        <p><strong>কাস্টমার:</strong> ${esc(CUSTOMER.name)} · ${esc(CUSTOMER.phone || '')} · ${esc(CUSTOMER.address || '')}</p>
        <table>
            <thead><tr><th>#</th><th>পণ্যের নাম</th><th>পরিমাণ</th><th>দাম (৳)</th><th>গুণফল (৳)</th></tr></thead>
            <tbody>${itemRows}</tbody>
            <tfoot>
                <tr><th colspan="4" style="text-align:right">সর্বমোট</th>
                    <th style="text-align:right">${parseFloat(a.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</th></tr>
                <tr><td colspan="5"><em>কথায়: ${bnMoneyWords(parseFloat(a.total_amount))}</em></td></tr>
                <tr><td colspan="4" style="text-align:right">জমাকৃত টাকা ${a.deposit_method ? '(' + esc(a.deposit_method) + ')' : ''}</td>
                    <td style="text-align:right">${parseFloat(a.deposit_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
            </tfoot>
        </table>
        <div class="sign">
            <div>কাস্টমারের স্বাক্ষর</div>
            <div>কর্তৃপক্ষের স্বাক্ষর</div>
        </div>
        <div class="page2">
            <h3>ডেলিভারি ট্র্যাকিং শিট</h3>
            <div class="meta">চুক্তিপত্র নং: ${esc(a.agreement_no)}</div>
            <table>
                <thead><tr><th>তারিখ</th><th>পণ্য</th><th>পরিমাণ</th><th>নোট</th></tr></thead>
                <tbody>${deliveryRows}</tbody>
            </table>
        </div>
        <script>window.onload = () => window.print();<\/script>
        </body></html>`);
    w.document.close();
}

// ── Init ─────────────────────────────────────────────────────────────────────
loadLedger();
loadAgreements();
