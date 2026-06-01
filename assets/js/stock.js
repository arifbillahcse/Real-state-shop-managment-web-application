// ============================================
// Stock Management — AJAX CRUD + Adjustments + Transfers + Branch Comparison
// ============================================

const BASE = window.location.origin + window.location.pathname.replace(/\/pages\/.*$/, '');

// ── Modal references (null if staff) ──────────────────────────────────────
const inboundModal  = document.getElementById('inboundModal')  ? new bootstrap.Modal(document.getElementById('inboundModal'))  : null;
const adjustModal   = document.getElementById('adjustModal')   ? new bootstrap.Modal(document.getElementById('adjustModal'))   : null;
const transferModal = document.getElementById('transferModal') ? new bootstrap.Modal(document.getElementById('transferModal')) : null;

const form        = document.getElementById('inboundForm');
const formError   = document.getElementById('inboundError');
const modalTitle  = document.getElementById('inboundModalTitle');

// Edit-state for adjustments / transfers
let editAdjId = null, editTrfId = null;
let adjustmentsCache = {}, transfersCache = {};
const qtyInput    = document.getElementById('inboundQty');
const priceInput  = document.getElementById('inboundPrice');
const totalPreview = document.getElementById('totalPreview');
const totalAmt     = document.getElementById('totalPreviewAmt');

// ── Live cost preview ─────────────────────────────────────────────────────
function updatePreview() {
    if (!qtyInput || !priceInput) return;
    const qty = parseFloat(qtyInput.value) || 0, price = parseFloat(priceInput.value) || 0;
    if (qty > 0 && price > 0) {
        if (totalPreview) totalPreview.style.display = '';
        if (totalAmt) totalAmt.textContent = (qty * price).toLocaleString('bn-BD', { minimumFractionDigits:2, maximumFractionDigits:2 }) + ' ৳';
    } else {
        if (totalPreview) totalPreview.style.display = 'none';
    }
}
if (qtyInput && priceInput) [qtyInput, priceInput].forEach(el => el.addEventListener('input', updatePreview));

// ── Stock In modal ────────────────────────────────────────────────────────
document.getElementById('btnAddInbound')?.addEventListener('click', () => {
    form.reset();
    tsSyncForm(form);
    document.getElementById('inboundId').value   = '';
    document.getElementById('inboundDate').value = new Date().toISOString().slice(0, 10);
    modalTitle.innerHTML = '<i class="bi bi-arrow-down-circle me-1 text-danger"></i>পণ্য কেনা (Stock In)';
    formError.classList.add('d-none');
    if (totalPreview) totalPreview.style.display = 'none';
    inboundModal.show();
});

document.querySelectorAll('.btn-edit-inbound').forEach(btn => {
    btn.addEventListener('click', async () => {
        formError.classList.add('d-none');
        if (totalPreview) totalPreview.style.display = 'none';
        try {
            const data = await fetchJSON(`${BASE}/api/get_stock_inbound.php?id=${btn.dataset.id}`);
            if (!data.success) { showToast(data.message, 'danger'); return; }
            const r = data.record;
            document.getElementById('inboundId').value       = r.id;
            tsSet('inboundProduct', r.product_id, true);
            qtyInput.value                                   = r.quantity;
            priceInput.value                                 = r.buy_price;
            tsSet('inboundSupplier', r.supplier_id || '', true);
            document.getElementById('inboundDate').value     = r.inbound_date;
            document.getElementById('inboundNote').value     = r.note || '';
            const branchSel = document.getElementById('inboundBranch');
            if (branchSel) tsSet(branchSel, r.branch_id || '', true);
            modalTitle.innerHTML = '<i class="bi bi-pencil me-1 text-danger"></i>স্টক সম্পাদনা';
            updatePreview();
            inboundModal.show();
        } catch { showToast('ডাটা লোড হয়নি।', 'danger'); }
    });
});

form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.classList.add('d-none');
    if (!document.getElementById('inboundProduct').value) {
        showErr(formError, 'পণ্য নির্বাচন করুন।'); return;
    }
    if ((parseFloat(qtyInput.value)||0) <= 0 || (parseFloat(priceInput.value)||0) <= 0) {
        showErr(formError, 'পরিমাণ ও ক্রয় দাম ০ এর বেশি হতে হবে।'); return;
    }
    const id  = document.getElementById('inboundId').value;
    const url = id ? `${BASE}/api/update_stock_inbound.php` : `${BASE}/api/add_stock_inbound.php`;
    const btn = document.getElementById('btnSaveInbound');
    await submitWithSpinner(btn, async () => {
        const data = await postForm(url, new FormData(form));
        if (data.success) { showToast(data.message, 'success'); inboundModal.hide(); setTimeout(() => location.reload(), 700); }
        else showErr(formError, data.message);
    });
});

document.querySelectorAll('.btn-delete-inbound').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(`"${btn.dataset.name}" এর এই ক্রয় রেকর্ডটি ডিলিট করবেন?`)) return;
        const data = await postJSON(`${BASE}/api/delete_stock_inbound.php`, { id: btn.dataset.id });
        if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 700); }
        else showToast(data.message, 'danger');
    });
});

// ── Adjustment modal ──────────────────────────────────────────────────────
function setAdjTitle(isEdit) {
    const t = document.getElementById('adjModalTitle');
    if (t) t.innerHTML = isEdit
        ? '<i class="bi bi-pencil me-1"></i>সংশোধন সম্পাদনা'
        : '<i class="bi bi-sliders me-1"></i>স্টক সংশোধন';
    const btn = document.getElementById('btnSaveAdj');
    if (btn) btn.innerHTML = isEdit
        ? '<i class="bi bi-check-lg me-1"></i>আপডেট করুন'
        : '<i class="bi bi-check-lg me-1"></i>সংশোধন করুন';
}

function openAdjustFor(productId) {
    editAdjId = null;
    setAdjTitle(false);
    tsSet('adjProduct', productId, true);
    const adjBranch = document.getElementById('adjBranch');
    if (adjBranch) tsSet(adjBranch, '', true);
    document.getElementById('adjQty').value   = '';
    document.getElementById('adjNote').value  = '';
    document.getElementById('adjAdd').checked = true;
    document.getElementById('adjReason').value = 'count_correction';
    document.getElementById('adjError').classList.add('d-none');
    adjustModal.show();
    updateAdjCurrentStock();
}

function openAdjustForBranch(productId, branchId) {
    editAdjId = null;
    setAdjTitle(false);
    tsSet('adjProduct', productId, true);
    const adjBranch = document.getElementById('adjBranch');
    if (adjBranch) tsSet(adjBranch, branchId, true);
    document.getElementById('adjQty').value    = '';
    document.getElementById('adjNote').value   = '';
    document.getElementById('adjAdd').checked  = true;
    document.getElementById('adjReason').value = 'count_correction';
    document.getElementById('adjError').classList.add('d-none');
    adjustModal.show();
    updateAdjCurrentStock();
}

function openEditAdjustment(id) {
    const r = adjustmentsCache[id];
    if (!r) return;
    editAdjId = id;
    setAdjTitle(true);
    tsSet('adjProduct', r.product_id, true);
    const adjBranch = document.getElementById('adjBranch');
    if (adjBranch) tsSet(adjBranch, r.branch_id || '', true);
    const qty = parseFloat(r.quantity);
    document.getElementById('adjQty').value     = Math.abs(qty);
    document.getElementById('adjAdd').checked    = qty >= 0;
    document.getElementById('adjSub').checked    = qty < 0;
    document.getElementById('adjReason').value   = r.reason || 'other';
    document.getElementById('adjNote').value      = r.note || '';
    document.getElementById('adjError').classList.add('d-none');
    adjustModal.show();
    updateAdjCurrentStock();
}

async function deleteAdjustment(id) {
    if (!confirm('এই স্টক সংশোধন রেকর্ডটি ডিলিট করবেন?')) return;
    const data = await postJSON(`${BASE}/api/delete_stock_adjustment.php`, { id });
    if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 700); }
    else showToast(data.message, 'danger');
}

document.getElementById('btnAdjustStock')?.addEventListener('click', () => {
    editAdjId = null;
    setAdjTitle(false);
    tsSet('adjProduct', '', true);
    const adjBranch = document.getElementById('adjBranch');
    if (adjBranch) tsSet(adjBranch, '', true);
    document.getElementById('adjQty').value     = '';
    document.getElementById('adjNote').value    = '';
    document.getElementById('adjAdd').checked   = true;
    document.getElementById('adjReason').value  = 'count_correction';
    document.getElementById('adjCurrentStock').textContent = '';
    document.getElementById('adjError').classList.add('d-none');
    adjustModal.show();
});

async function updateAdjCurrentStock() {
    const pid      = document.getElementById('adjProduct').value;
    const branchEl = document.getElementById('adjBranch');
    const bid      = branchEl ? branchEl.value : '';
    const info     = document.getElementById('adjCurrentStock');
    if (!pid) { info.textContent = ''; return; }
    info.textContent = '...';
    try {
        if (bid) {
            const data = await fetchJSON(`${BASE}/api/get_branch_stock.php?branch_id=${bid}`);
            if (data.success) {
                const row = (data.stock || []).find(r => String(r.product_id) === String(pid));
                info.innerHTML = row
                    ? `<span class="text-primary fw-semibold">বর্তমান স্টক: ${parseFloat(row.current_stock)} ${row.unit}</span>`
                    : '<span class="text-muted">এই ব্রাঞ্চে স্টক নেই</span>';
            }
        } else {
            const data = await fetchJSON(`${BASE}/api/get_stock.php`);
            if (data.success) {
                const row = (data.stock || []).find(r => String(r.product_id) === String(pid));
                info.innerHTML = row
                    ? `<span class="text-primary fw-semibold">বর্তমান স্টক: ${parseFloat(row.current_stock)} ${row.unit}</span>`
                    : '<span class="text-muted">স্টক তথ্য পাওয়া যায়নি</span>';
            }
        }
    } catch { info.textContent = ''; }
}
document.getElementById('adjProduct')?.addEventListener('change', updateAdjCurrentStock);
document.getElementById('adjBranch')?.addEventListener('change', updateAdjCurrentStock);

document.getElementById('btnSaveAdj')?.addEventListener('click', async () => {
    const errEl    = document.getElementById('adjError');
    errEl.classList.add('d-none');
    const pid      = document.getElementById('adjProduct').value;
    const qty      = parseFloat(document.getElementById('adjQty').value) || 0;
    const dir      = document.querySelector('input[name="adjDir"]:checked')?.value || 'add';
    const reason   = document.getElementById('adjReason').value;
    const note     = document.getElementById('adjNote').value;
    const branchEl = document.getElementById('adjBranch');
    const bid      = branchEl ? branchEl.value : '';
    if (!pid)   { showErr(errEl, 'পণ্য নির্বাচন করুন।'); return; }
    if (qty <= 0) { showErr(errEl, 'পরিমাণ ০ এর বেশি হতে হবে।'); return; }
    const btn = document.getElementById('btnSaveAdj');
    await submitWithSpinner(btn, async () => {
        const url = editAdjId
            ? `${BASE}/api/update_stock_adjustment.php`
            : `${BASE}/api/add_stock_adjustment.php`;
        const payload = { product_id: pid, quantity: qty, direction: dir, reason, note, branch_id: bid };
        if (editAdjId) payload.id = editAdjId;
        const data = await postJSON(url, payload);
        if (data.success) { showToast(data.message, 'success'); adjustModal.hide(); setTimeout(() => location.reload(), 700); }
        else showErr(errEl, data.message);
    });
});

// ── Transfer modal ────────────────────────────────────────────────────────
function setTrfTitle(isEdit) {
    const t = document.getElementById('trfModalTitle');
    if (t) t.innerHTML = isEdit
        ? '<i class="bi bi-pencil me-1"></i>ট্রান্সফার সম্পাদনা'
        : '<i class="bi bi-arrow-left-right me-1"></i>ব্রাঞ্চ ট্রান্সফার';
    const btn = document.getElementById('btnSaveTrf');
    if (btn) btn.innerHTML = isEdit
        ? '<i class="bi bi-check-lg me-1"></i>আপডেট করুন'
        : '<i class="bi bi-check-lg me-1"></i>ট্রান্সফার করুন';
}

function openEditTransfer(id) {
    const r = transfersCache[id];
    if (!r) return;
    editTrfId = id;
    setTrfTitle(true);
    tsSet('trfProduct', r.product_id, true);
    tsSet('trfFrom', r.from_branch_id, true);
    tsSet('trfTo', r.to_branch_id, true);
    document.getElementById('trfQty').value  = parseFloat(r.quantity);
    document.getElementById('trfNote').value = r.note || '';
    document.getElementById('trfError').classList.add('d-none');
    transferModal.show();
    updateTrfFromStock();
}

async function deleteTransfer(id) {
    if (!confirm('এই ট্রান্সফার রেকর্ডটি ডিলিট করবেন?')) return;
    const data = await postJSON(`${BASE}/api/delete_stock_transfer.php`, { id });
    if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 700); }
    else showToast(data.message, 'danger');
}

document.getElementById('btnTransferStock')?.addEventListener('click', () => {
    editTrfId = null;
    setTrfTitle(false);
    tsSet('trfProduct', '', true);
    tsSet('trfFrom', '', true);
    tsSet('trfTo', '', true);
    document.getElementById('trfQty').value     = '';
    document.getElementById('trfNote').value    = '';
    document.getElementById('trfFromStock').textContent = '';
    document.getElementById('trfError').classList.add('d-none');
    transferModal.show();
});

async function updateTrfFromStock() {
    const pid = document.getElementById('trfProduct').value;
    const bid = document.getElementById('trfFrom').value;
    const info = document.getElementById('trfFromStock');
    if (!pid || !bid) { info.textContent = ''; return; }
    try {
        const data = await fetchJSON(`${BASE}/api/get_branch_stock.php?branch_id=${bid}`);
        if (data.success) {
            const row = (data.stock || []).find(r => String(r.product_id) === String(pid));
            info.textContent = row ? `উপলব্ধ: ${parseFloat(row.current_stock)} ${row.unit}` : 'এই ব্রাঞ্চে স্টক নেই';
        }
    } catch { info.textContent = ''; }
}
document.getElementById('trfProduct')?.addEventListener('change', updateTrfFromStock);
document.getElementById('trfFrom')?.addEventListener('change', updateTrfFromStock);

document.getElementById('btnSaveTrf')?.addEventListener('click', async () => {
    const errEl = document.getElementById('trfError');
    errEl.classList.add('d-none');
    const pid  = document.getElementById('trfProduct').value;
    const from = document.getElementById('trfFrom').value;
    const to   = document.getElementById('trfTo').value;
    const qty  = parseFloat(document.getElementById('trfQty').value) || 0;
    const note = document.getElementById('trfNote').value;
    if (!pid)          { showErr(errEl, 'পণ্য নির্বাচন করুন।'); return; }
    if (!from || !to)  { showErr(errEl, 'উৎস ও গন্তব্য ব্রাঞ্চ নির্বাচন করুন।'); return; }
    if (from === to)   { showErr(errEl, 'উৎস ও গন্তব্য ব্রাঞ্চ একই হতে পারবে না।'); return; }
    if (qty <= 0)      { showErr(errEl, 'পরিমাণ ০ এর বেশি হতে হবে।'); return; }
    const btn = document.getElementById('btnSaveTrf');
    await submitWithSpinner(btn, async () => {
        const url = editTrfId
            ? `${BASE}/api/update_stock_transfer.php`
            : `${BASE}/api/add_stock_transfer.php`;
        const payload = { product_id: pid, from_branch_id: from, to_branch_id: to, quantity: qty, note };
        if (editTrfId) payload.id = editTrfId;
        const data = await postJSON(url, payload);
        if (data.success) { showToast(data.message, 'success'); transferModal.hide(); setTimeout(() => location.reload(), 700); }
        else showErr(errEl, data.message);
    });
});

// ── Branch stock: individual view ─────────────────────────────────────────
const branchSelector = document.getElementById('branchStockSelector');
if (branchSelector) {
    branchSelector.addEventListener('change', async () => {
        const bid = branchSelector.value;
        const wrap   = document.getElementById('branchStockTableWrap');
        const prompt = document.getElementById('branchStockPrompt');
        if (!bid) { wrap.style.display='none'; document.getElementById('branchStockEmpty').style.display='none'; if(prompt) prompt.style.display=''; return; }
        if (prompt) prompt.style.display = 'none';
        await loadBranchStockById(bid, true);
    });
}

// Staff auto-load
if (typeof IS_STAFF_VIEW !== 'undefined' && IS_STAFF_VIEW && STAFF_BRANCH_ID) {
    window.addEventListener('DOMContentLoaded', () => loadBranchStockById(STAFF_BRANCH_ID, false));
}

async function loadBranchStockById(branchId, detailed = true) {
    const wrap     = document.getElementById('branchStockTableWrap');
    const emptyMsg = document.getElementById('branchStockEmpty');
    const tbody    = document.getElementById('branchStockBody');
    if (!wrap) return;
    emptyMsg.style.display = 'none';
    tbody.innerHTML = `<tr><td colspan="${detailed?11:8}" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>লোড হচ্ছে...</td></tr>`;
    wrap.style.display = '';
    try {
        const data = await fetchJSON(`${BASE}/api/get_branch_stock.php?branch_id=${branchId}`);
        if (!data.success) { showToast(data.message, 'danger'); return; }
        const rows = (data.stock || []).filter(r => parseFloat(r.current_stock) > 0 || parseFloat(r.total_inbound) > 0);
        if (!rows.length) { wrap.style.display='none'; emptyMsg.style.display=''; return; }
        const adjBtn = CAN_WRITE
            ? `<button class="btn btn-sm btn-outline-warning py-0 px-1" onclick="openAdjustForBranch('{pid}',${branchId})" title="স্টক সংশোধন"><i class="bi bi-sliders"></i></button>`
            : '';
        tbody.innerHTML = rows.map(r => {
            const stock = parseFloat(r.current_stock);
            const low   = parseFloat(r.min_stock) > 0 && stock <= parseFloat(r.min_stock);
            const typeBadge  = `<span class="badge bg-secondary">${r.product_type ?? ''}</span>`;
            const statusBadge = low ? '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>কম</span>' : '<span class="badge bg-success"><i class="bi bi-check me-1"></i>ঠিক আছে</span>';
            const fmt = (v) => parseFloat(v||0).toLocaleString('bn-BD', {maximumFractionDigits:2});
            const action = adjBtn.replace('{pid}', r.product_id);
            if (detailed) {
                const adjVal = parseFloat(r.total_adjustments||0);
                const adjCell = adjVal !== 0
                    ? `<span class="${adjVal>0?'text-success':'text-danger'}">${adjVal>0?'+':''}${fmt(adjVal)} ${r.unit}</span>`
                    : `<span class="text-muted">০</span>`;
                return `<tr class="${low?'table-danger':''}">
                    <td class="fw-semibold">${r.product_name}</td>
                    <td>${typeBadge}</td>
                    <td>${r.size_brand||'—'}</td>
                    <td class="text-end">${fmt(r.total_inbound)} ${r.unit}</td>
                    <td class="text-end">${adjCell}</td>
                    <td class="text-end text-success">${fmt(r.total_transferred_in||0)} ${r.unit}</td>
                    <td class="text-end text-warning">${fmt(r.total_transferred_out||0)} ${r.unit}</td>
                    <td class="text-end">${fmt(r.total_sold)} ${r.unit}</td>
                    <td class="text-end ${low?'low-stock':''} fw-semibold">${fmt(stock)} ${r.unit}</td>
                    <td class="text-center">${statusBadge}</td>
                    ${CAN_WRITE ? `<td class="text-center">${action}</td>` : ''}
                </tr>`;
            } else {
                return `<tr class="${low?'table-danger':''}">
                    <td class="fw-semibold">${r.product_name}</td>
                    <td>${typeBadge}</td>
                    <td>${r.size_brand||'—'}</td>
                    <td class="text-end">${fmt(r.total_inbound)} ${r.unit}</td>
                    <td class="text-end">${fmt(r.total_sold)} ${r.unit}</td>
                    <td class="text-end ${low?'low-stock':''} fw-semibold">${fmt(stock)} ${r.unit}</td>
                    <td class="text-center">${statusBadge}</td>
                    ${CAN_WRITE ? `<td class="text-center">${action}</td>` : ''}
                </tr>`;
            }
        }).join('');
    } catch { showToast('ডাটা লোড হয়নি।', 'danger'); }
}

// ── Branch comparison matrix ──────────────────────────────────────────────
async function loadBranchComparison() {
    const loading    = document.getElementById('branchCompareLoading');
    const table      = document.getElementById('branchCompareTable');
    const thead      = document.getElementById('branchCompareHead');
    const tbody      = document.getElementById('branchCompareBody');
    const cardsEl    = document.getElementById('branchSummaryCards');
    if (!loading) return;

    try {
        const data = await fetchJSON(`${BASE}/api/get_all_branch_stock.php`);
        if (!data.success) { showToast(data.message,'danger'); return; }

        const branches = data.branches || [];
        const stock    = data.stock    || [];

        // Build lookup: [productId][branchId] = row
        const matrix = {};
        const products = {};
        stock.forEach(r => {
            const pid = r.product_id, bid = r.branch_id;
            if (!matrix[pid]) matrix[pid] = {};
            matrix[pid][bid] = r;
            if (!products[pid]) products[pid] = { name: r.product_name, type: r.product_type, unit: r.unit, min_stock: r.min_stock, size_brand: r.size_brand };
        });

        // Summary cards per branch
        if (cardsEl) {
            cardsEl.innerHTML = branches.map(b => {
                const bRows = stock.filter(r => String(r.branch_id) === String(b.id) && parseFloat(r.current_stock) > 0);
                const val   = bRows.reduce((s,r) => s + parseFloat(r.current_stock) * parseFloat(r.buy_price), 0);
                const lowC  = bRows.filter(r => parseFloat(r.min_stock) > 0 && parseFloat(r.current_stock) <= parseFloat(r.min_stock)).length;
                return `<div class="col-sm-6 col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="fw-semibold text-truncate"><i class="bi bi-shop me-1 text-danger"></i>${b.name}</div>
                            <div class="small text-muted">${bRows.length} পণ্য আছে</div>
                            <div class="fw-bold">${val.toLocaleString('bn-BD',{maximumFractionDigits:0})} ৳</div>
                            ${lowC > 0 ? `<div class="badge bg-danger mt-1">${lowC}টি কম স্টক</div>` : '<div class="badge bg-success mt-1">স্টক ঠিক আছে</div>'}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        // Build table header
        thead.innerHTML = `<tr>
            <th>পণ্যের নাম</th>
            <th>ধরন</th>
            <th>ইউনিট</th>
            ${branches.map(b => `<th class="text-end">${b.name}</th>`).join('')}
            <th class="text-end">মোট</th>
        </tr>`;

        // Group by type
        const byType = {};
        Object.entries(products).forEach(([pid, p]) => {
            if (!byType[p.type]) byType[p.type] = [];
            byType[p.type].push({ pid, ...p });
        });

        let rows = '';
        Object.entries(byType).forEach(([type, list]) => {
            rows += `<tr class="table-secondary"><td colspan="${3 + branches.length + 1}" class="fw-bold small py-1 ps-2">
                ${type === 'rod' ? '🔩 রড' : '🧱 সিমেন্ট'}
            </td></tr>`;
            list.forEach(p => {
                let rowTotal = 0;
                const cells = branches.map(b => {
                    const r     = matrix[p.pid]?.[b.id];
                    const stock = parseFloat(r?.current_stock || 0);
                    rowTotal += stock;
                    const low   = parseFloat(p.min_stock) > 0 && stock <= parseFloat(p.min_stock) && stock > 0;
                    const zero  = stock <= 0;
                    const cls   = low ? 'text-danger fw-semibold' : (zero ? 'text-muted' : '');
                    return `<td class="text-end ${cls}">${stock > 0 ? stock.toLocaleString('bn-BD',{maximumFractionDigits:2}) : '—'}</td>`;
                }).join('');
                rows += `<tr>
                    <td class="fw-semibold">${p.name}${p.size_brand ? ` <small class="text-muted">${p.size_brand}</small>` : ''}</td>
                    <td>${type === 'rod' ? '<span class="badge bg-primary">রড</span>' : '<span class="badge bg-warning text-dark">সিমেন্ট</span>'}</td>
                    <td class="text-muted small">${p.unit}</td>
                    ${cells}
                    <td class="text-end fw-semibold">${rowTotal > 0 ? rowTotal.toLocaleString('bn-BD',{maximumFractionDigits:2}) : '—'}</td>
                </tr>`;
            });
        });
        tbody.innerHTML = rows;

        loading.style.display = 'none';
        table.classList.remove('d-none');
    } catch (e) {
        loading.innerHTML = '<div class="alert alert-danger m-3">তুলনা লোড হয়নি।</div>';
    }
}

// Load comparison when tab shows
document.getElementById('btnBranchCompare')?.addEventListener('shown.bs.tab', loadBranchComparison);
// Also load on first entry to branch stock tab (admin)
document.getElementById('btnBranchStockTab')?.addEventListener('shown.bs.tab', () => {
    const table = document.getElementById('branchCompareTable');
    if (table && table.classList.contains('d-none') && document.getElementById('branchCompareLoading')?.style.display !== 'none') {
        loadBranchComparison();
    }
});

window.addEventListener('DOMContentLoaded', () => {
    // Load comparison immediately if branch stock tab is default (staff won't have it)
    if (typeof IS_STAFF_VIEW !== 'undefined' && !IS_STAFF_VIEW && typeof HAS_BRANCHES !== 'undefined' && HAS_BRANCHES) {
        loadBranchComparison();
    }
});

// ── Adjustment history tab ────────────────────────────────────────────────
const reasonLabel = { count_correction:'গণনা সংশোধন', damage:'ক্ষতিগ্রস্ত', return:'রিটার্ন', other:'অন্যান্য' };

document.getElementById('btnAdjustTab')?.addEventListener('shown.bs.tab', loadAdjustments);

async function loadAdjustments() {
    const tbody = document.getElementById('adjustBody');
    if (!tbody || tbody.dataset.loaded) return;
    try {
        const data = await fetchJSON(`${BASE}/api/get_stock_adjustments.php`);
        if (!data.success) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${data.message}</td></tr>`; return; }
        const list = data.data || [];
        adjustmentsCache = {};
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">কোনো সংশোধন রেকর্ড নেই।</td></tr>'; return; }
        tbody.innerHTML = list.map(r => {
            adjustmentsCache[r.id] = r;
            const qty = parseFloat(r.quantity);
            const qtyCell = `<span class="${qty>0?'text-success fw-semibold':'text-danger fw-semibold'}">${qty>0?'+':''}${qty.toLocaleString('bn-BD',{maximumFractionDigits:2})} ${r.unit}</span>`;
            const actions = CAN_WRITE ? `
                <button class="btn btn-sm btn-outline-warning me-1" onclick="openEditAdjustment(${r.id})" title="সম্পাদনা"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAdjustment(${r.id})" title="ডিলিট"><i class="bi bi-trash"></i></button>` : '';
            return `<tr>
                <td class="text-muted small">${new Date(r.created_at).toLocaleDateString('bn-BD')}</td>
                <td class="fw-semibold">${r.product_name}</td>
                <td>${r.branch_name ? `<span class="badge bg-secondary">${r.branch_name}</span>` : '<span class="text-muted">গ্লোবাল</span>'}</td>
                <td>${qtyCell}</td>
                <td><span class="badge bg-light text-dark border">${reasonLabel[r.reason]||r.reason}</span></td>
                <td class="text-muted small">${r.note||'—'}</td>
                <td class="text-muted small">${r.created_by_name||'—'}</td>
                <td class="text-center text-nowrap">${actions}</td>
            </tr>`;
        }).join('');
        tbody.dataset.loaded = '1';
    } catch { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">লোড হয়নি।</td></tr>'; }
}

// ── Transfer history tab ──────────────────────────────────────────────────
document.getElementById('btnTransferTab')?.addEventListener('shown.bs.tab', loadTransfers);

async function loadTransfers() {
    const tbody = document.getElementById('transferBody');
    if (!tbody || tbody.dataset.loaded) return;
    try {
        const data = await fetchJSON(`${BASE}/api/get_stock_transfers.php`);
        if (!data.success) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${data.message}</td></tr>`; return; }
        const list = data.data || [];
        transfersCache = {};
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">কোনো ট্রান্সফার রেকর্ড নেই।</td></tr>'; return; }
        tbody.innerHTML = list.map(r => {
            transfersCache[r.id] = r;
            const actions = CAN_WRITE ? `
                <button class="btn btn-sm btn-outline-warning me-1" onclick="openEditTransfer(${r.id})" title="সম্পাদনা"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteTransfer(${r.id})" title="ডিলিট"><i class="bi bi-trash"></i></button>` : '';
            return `<tr>
                <td class="text-muted small">${new Date(r.created_at).toLocaleDateString('bn-BD')}</td>
                <td class="fw-semibold">${r.product_name}</td>
                <td><span class="badge bg-warning text-dark">${r.from_branch_name}</span></td>
                <td><span class="badge bg-success">${r.to_branch_name}</span></td>
                <td class="text-end fw-semibold">${parseFloat(r.quantity).toLocaleString('bn-BD',{maximumFractionDigits:2})} ${r.unit}</td>
                <td class="text-muted small">${r.note||'—'}</td>
                <td class="text-muted small">${r.created_by_name||'—'}</td>
                <td class="text-center text-nowrap">${actions}</td>
            </tr>`;
        }).join('');
        tbody.dataset.loaded = '1';
    } catch { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">লোড হয়নি।</td></tr>'; }
}

// ── Helpers ───────────────────────────────────────────────────────────────
async function fetchJSON(url) {
    const r = await fetch(url);
    return r.json();
}
async function postJSON(url, data) {
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(data) });
    return r.json();
}
async function postForm(url, formData) {
    const r = await fetch(url, { method:'POST', body: formData });
    return r.json();
}
function showErr(el, msg) { el.textContent = msg; el.classList.remove('d-none'); }
async function submitWithSpinner(btn, fn) {
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
    try { await fn(); } catch { showToast('সার্ভারে সমস্যা হয়েছে।','danger'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
}
