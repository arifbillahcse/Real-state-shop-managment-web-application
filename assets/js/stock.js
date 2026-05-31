// ============================================
// Stock Management — AJAX CRUD
// ============================================

const BASE = window.location.origin + window.location.pathname.replace(/\/pages\/.*$/, '');

const inboundModal = new bootstrap.Modal(document.getElementById('inboundModal'));
const form         = document.getElementById('inboundForm');
const formError    = document.getElementById('inboundError');
const modalTitle   = document.getElementById('inboundModalTitle');
const qtyInput     = document.getElementById('inboundQty');
const priceInput   = document.getElementById('inboundPrice');
const totalPreview = document.getElementById('totalPreview');
const totalAmt     = document.getElementById('totalPreviewAmt');

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
    document.getElementById('inboundDate').value = new Date().toISOString().slice(0, 10);
    modalTitle.innerHTML = '<i class="bi bi-arrow-down-circle me-1 text-danger"></i>পণ্য কেনা (Stock In)';
    formError.classList.add('d-none');
    totalPreview.style.display = 'none';
    inboundModal.show();
});

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
            const branchSel = document.getElementById('inboundBranch');
            if (branchSel) branchSel.value = r.branch_id || '';

            modalTitle.innerHTML = '<i class="bi bi-pencil me-1 text-danger"></i>স্টক সম্পাদনা';
            updatePreview();
            inboundModal.show();
        } catch {
            showToast('ডাটা লোড হয়নি।', 'danger');
        }
    });
});

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
            setTimeout(() => location.reload(), 700);
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

// --- Delete ---
document.querySelectorAll('.btn-delete-inbound').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(`”${btn.dataset.name}” এর এই ক্রয় রেকর্ডটি কি ডিলিট করতে চান?`)) return;
        try {
            const res  = await fetch(`${BASE}/api/delete_stock_inbound.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({ id: btn.dataset.id })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(data.message, 'danger');
            }
        } catch {
            showToast('ডিলিট করা যায়নি।', 'danger');
        }
    });
});

// --- Branch Stock Tab ---
const branchSelector = document.getElementById('branchStockSelector');
if (branchSelector) {
    branchSelector.addEventListener('change', async () => {
        const branchId = branchSelector.value;
        const wrap     = document.getElementById('branchStockTableWrap');
        const emptyMsg = document.getElementById('branchStockEmpty');
        const prompt   = document.getElementById('branchStockPrompt');
        const tbody    = document.getElementById('branchStockBody');

        wrap.style.display   = 'none';
        emptyMsg.style.display = 'none';
        prompt.style.display = 'none';

        if (!branchId) { prompt.style.display = ''; return; }

        tbody.innerHTML = '<tr><td colspan=”7” class=”text-center py-3”><span class=”spinner-border spinner-border-sm me-2”></span>লোড হচ্ছে...</td></tr>';
        wrap.style.display = '';

        try {
            const res  = await fetch(`${BASE}/api/get_branch_stock.php?branch_id=${branchId}`);
            const data = await res.json();
            if (!data.success) { showToast(data.message, 'danger'); return; }

            const rows = data.stock || [];
            const active = rows.filter(r => parseFloat(r.current_stock) > 0 || parseFloat(r.total_inbound) > 0);

            if (!active.length) {
                wrap.style.display   = 'none';
                emptyMsg.style.display = '';
                return;
            }

            tbody.innerHTML = active.map(r => {
                const stock = parseFloat(r.current_stock);
                const low   = parseFloat(r.min_stock) > 0 && stock <= parseFloat(r.min_stock);
                const qty   = stock.toLocaleString('bn-BD', { maximumFractionDigits: 2 });
                const typeBadge = r.product_type === 'rod'
                    ? '<span class=”badge bg-primary”>রড</span>'
                    : '<span class=”badge bg-warning text-dark”>সিমেন্ট</span>';
                const statusBadge = low
                    ? '<span class=”badge bg-danger”><i class=”bi bi-exclamation-triangle me-1”></i>কম</span>'
                    : '<span class=”badge bg-success”><i class=”bi bi-check me-1”></i>ঠিক আছে</span>';
                return `<tr class=”${low ? 'table-danger' : ''}”>
                    <td class=”fw-semibold”>${r.product_name}</td>
                    <td>${typeBadge}</td>
                    <td>${r.size_brand || '—'}</td>
                    <td class=”text-end”>${parseFloat(r.total_inbound).toLocaleString('bn-BD', {maximumFractionDigits:2})} ${r.unit}</td>
                    <td class=”text-end”>${parseFloat(r.total_sold).toLocaleString('bn-BD', {maximumFractionDigits:2})} ${r.unit}</td>
                    <td class=”text-end ${low ? 'low-stock' : ''}”>${qty} ${r.unit}</td>
                    <td class=”text-center”>${statusBadge}</td>
                </tr>`;
            }).join('');
        } catch {
            showToast('ডাটা লোড হয়নি।', 'danger');
        }
    });
}
