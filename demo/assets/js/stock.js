// ============================================
// Stock Management — AJAX CRUD
//
// Adapted from the original assets/js/stock.js. pages/stock.php rendered
// both tables, the low-stock banner and the modal's product/supplier
// dropdowns server-side, so this version loads them from the API first.
// The modal, live total preview and submit logic are unchanged.
// ============================================

const BASE = BASE_URL;

const inboundModal = new bootstrap.Modal(document.getElementById('inboundModal'));
const form         = document.getElementById('inboundForm');
const formError    = document.getElementById('inboundError');
const modalTitle   = document.getElementById('inboundModalTitle');
const qtyInput     = document.getElementById('inboundQty');
const priceInput   = document.getElementById('inboundPrice');
const totalPreview = document.getElementById('totalPreview');
const totalAmt     = document.getElementById('totalPreviewAmt');

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function money(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

// Matches rtrim(rtrim($value,'0'),'.') in the PHP view
function trimZeros(n) {
    return String(parseFloat(n || 0));
}

function fmtDate(iso) {
    if (!iso) return '';
    const [y, m, d] = String(iso).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
}

const typeLabel = t => (t === 'rod' ? 'রড' : 'সিমেন্ট');

// --- Live total cost preview ---
function updatePreview() {
    const qty   = parseFloat(qtyInput.value)   || 0;
    const price = parseFloat(priceInput.value) || 0;
    if (qty > 0 && price > 0) {
        totalPreview.style.display = '';
        totalAmt.textContent = (qty * price).toLocaleString('bn-BD',
            { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ৳';
    } else {
        totalPreview.style.display = 'none';
    }
}
[qtyInput, priceInput].forEach(el => el.addEventListener('input', updatePreview));

// --- Open modal for ADD ---
document.getElementById('btnAddInbound').addEventListener('click', () => {
    form.reset();
    document.getElementById('inboundId').value   = '';
    document.getElementById('inboundDate').value = todayISO();
    modalTitle.innerHTML = '<i class="bi bi-arrow-down-circle me-1 text-danger"></i>পণ্য কেনা (Stock In)';
    formError.classList.add('d-none');
    totalPreview.style.display = 'none';
    inboundModal.show();
});

function todayISO() {
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}

// --- Populate the modal dropdowns (were PHP loops) ---
function loadDropdowns() {
    fetch(`${BASE}/api/get_products.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const select = document.getElementById('inboundProduct');
            const group  = (label, items) => items.length ? `
                <optgroup label="${label}">
                    ${items.map(p => `<option value="${p.id}">${esc(p.name)} (${esc(p.size_brand)})</option>`).join('')}
                </optgroup>` : '';

            select.innerHTML =
                '<option value="">— পণ্য নির্বাচন করুন —</option>' +
                group('রড',     (res.products || []).filter(p => p.type === 'rod')) +
                group('সিমেন্ট', (res.products || []).filter(p => p.type === 'cement'));
        });

    fetch(`${BASE}/api/get_suppliers.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            document.getElementById('inboundSupplier').innerHTML =
                '<option value="">— নির্বাচন করুন (ঐচ্ছিক) —</option>' +
                (res.suppliers || []).map(s =>
                    `<option value="${s.id}">${esc(s.name)}</option>`).join('');
        });
}

// --- Current stock table + low-stock banner ---
function loadCurrentStock() {
    fetch(`${BASE}/api/get_stock.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.message, 'danger'); return; }
            renderCurrentStock(res.stock || []);
        })
        .catch(() => showToast('ডাটা লোড হয়নি।', 'danger'));
}

function renderCurrentStock(rows) {
    document.getElementById('stockCount').textContent = rows.length;

    const tbody = document.getElementById('currentStockBody');
    const tfoot = document.getElementById('currentStockFoot');
    const banner = document.getElementById('lowStockBanner');

    const lowRows = rows.filter(r => r.min_stock > 0 && r.current_stock <= r.min_stock);

    banner.innerHTML = lowRows.length ? `
        <div class="alert alert-warning alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>${lowRows.length}টি পণ্যের স্টক কম!</strong>
            ${lowRows.map(r => `<span class="badge bg-danger ms-1">${esc(r.product_name)} (${trimZeros(r.current_stock)} ${esc(r.unit)})</span>`).join('')}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>` : '';

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">
            এখনো কোনো পণ্য যোগ হয়নি।</td></tr>`;
        tfoot.innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const low   = r.min_stock > 0 && r.current_stock <= r.min_stock;
        const total = r.current_stock * r.buy_price;
        return `
        <tr class="${low ? 'table-danger' : ''}">
            <td class="fw-semibold">${esc(r.product_name)}</td>
            <td>
                <span class="badge ${r.product_type === 'rod' ? 'bg-primary' : 'bg-warning text-dark'}">
                    ${typeLabel(r.product_type)}
                </span>
            </td>
            <td>${esc(r.size_brand || '—')}</td>
            <td class="text-end ${low ? 'low-stock' : ''}">${trimZeros(r.current_stock)} ${esc(r.unit)}</td>
            <td class="text-end">${trimZeros(r.min_stock)} ${esc(r.unit)}</td>
            <td class="text-end">${money(r.buy_price)}</td>
            <td class="text-end">${money(total)}</td>
            <td class="text-center">
                ${low
                    ? '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>কম</span>'
                    : '<span class="badge bg-success"><i class="bi bi-check me-1"></i>ঠিক আছে</span>'}
            </td>
        </tr>`;
    }).join('');

    const grandTotal = rows.reduce((a, r) => a + r.current_stock * r.buy_price, 0);
    tfoot.innerHTML = `
        <tr>
            <td colspan="6" class="text-end fw-bold">মোট স্টক মূল্য:</td>
            <td class="text-end fw-bold text-danger">${money(grandTotal)}</td>
            <td></td>
        </tr>`;
}

// --- Inbound history table ---
function loadHistory() {
    fetch(`${BASE}/api/get_stock_inbound.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.message, 'danger'); return; }
            renderHistory(res.history || []);
            bindRowButtons();
        })
        .catch(() => showToast('ডাটা লোড হয়নি।', 'danger'));
}

function renderHistory(rows) {
    document.getElementById('historyCount').textContent = rows.length;
    const tbody = document.getElementById('inboundBody');

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">
            এখনো কোনো ক্রয় রেকর্ড নেই।</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(h => `
        <tr>
            <td>${fmtDate(h.inbound_date)}</td>
            <td>
                <div class="fw-semibold">${esc(h.product_name)}</div>
                <small class="text-muted">${typeLabel(h.product_type)}</small>
            </td>
            <td>${esc(h.supplier_name || '—')}</td>
            <td class="text-end">${trimZeros(h.quantity)} ${esc(h.unit)}</td>
            <td class="text-end">${money(h.buy_price)}</td>
            <td class="text-end fw-semibold">${money(h.total_cost)}</td>
            <td><small class="text-muted">${esc(h.note || '')}</small></td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary btn-edit-inbound"
                        data-id="${h.id}" title="সম্পাদনা">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger btn-delete-inbound"
                        data-id="${h.id}" data-name="${esc(h.product_name)}" title="ডিলিট">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`).join('');
}

function reloadAll() {
    loadCurrentStock();
    loadHistory();
}

function bindRowButtons() {
    // --- Open modal for EDIT ---
    document.querySelectorAll('.btn-edit-inbound').forEach(btn => {
        btn.addEventListener('click', async () => {
            formError.classList.add('d-none');
            totalPreview.style.display = 'none';
            try {
                const res  = await fetch(`${BASE}/api/get_stock_inbound.php?id=${btn.dataset.id}`);
                const data = await res.json();
                if (!data.success) { showToast(data.message, 'danger'); return; }

                const r = data.record;
                document.getElementById('inboundId').value       = r.id;
                document.getElementById('inboundProduct').value  = r.product_id;
                qtyInput.value                                   = r.quantity;
                priceInput.value                                 = r.buy_price;
                document.getElementById('inboundSupplier').value = r.supplier_id || '';
                document.getElementById('inboundDate').value     = r.inbound_date;
                document.getElementById('inboundNote').value     = r.note || '';

                modalTitle.innerHTML = '<i class="bi bi-pencil me-1 text-danger"></i>স্টক সম্পাদনা';
                updatePreview();
                inboundModal.show();
            } catch {
                showToast('ডাটা লোড হয়নি।', 'danger');
            }
        });
    });

    // --- Delete ---
    document.querySelectorAll('.btn-delete-inbound').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`“${btn.dataset.name}” এর এই ক্রয় রেকর্ডটি কি ডিলিট করতে চান?`)) return;
            try {
                const res  = await fetch(`${BASE}/api/delete_stock_inbound.php`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({ id: btn.dataset.id })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    reloadAll();
                } else {
                    showToast(data.message, 'danger');
                }
            } catch {
                showToast('ডিলিট করা যায়নি।', 'danger');
            }
        });
    });
}

// --- Submit (Add or Update) ---
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.classList.add('d-none');

    if (!document.getElementById('inboundProduct').value) {
        formError.textContent = 'পণ্য নির্বাচন করুন।';
        formError.classList.remove('d-none');
        return;
    }
    if ((parseFloat(qtyInput.value) || 0) <= 0 || (parseFloat(priceInput.value) || 0) <= 0) {
        formError.textContent = 'পরিমাণ ও ক্রয় দাম ০ এর বেশি হতে হবে।';
        formError.classList.remove('d-none');
        return;
    }

    const id  = document.getElementById('inboundId').value;
    const url = id ? `${BASE}/api/update_stock_inbound.php` : `${BASE}/api/add_stock_inbound.php`;
    const btn = document.getElementById('btnSaveInbound');
    const orig = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>সেভ হচ্ছে...';

    try {
        const res  = await fetch(url, { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            inboundModal.hide();
            reloadAll();
        } else {
            formError.textContent = data.message;
            formError.classList.remove('d-none');
        }
    } catch {
        formError.textContent = 'সার্ভারে সমস্যা হয়েছে।';
        formError.classList.remove('d-none');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
});

loadDropdowns();
reloadAll();
