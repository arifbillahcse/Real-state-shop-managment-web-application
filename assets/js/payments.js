// ============================================
// Payments / Khata — AJAX
// ============================================

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

// ---- Payment Method: show ref field ----
document.getElementById('payMethod').addEventListener('change', function () {
    const show = this.value === 'cheque' || this.value === 'mobile_banking';
    document.getElementById('refNoSection').classList.toggle('d-none', !show);
});

// ---- Customer Changed ----
function onCustomerChange(sel) {
    const id = sel.value;
    document.getElementById('selectedSaleId').value = '';
    document.getElementById('payAmount').value = '';

    const section = document.getElementById('outstandingSection');
    if (!id) { section.classList.add('d-none'); return; }

    section.classList.remove('d-none');
    document.getElementById('outstandingSalesList').innerHTML =
        '<div class="text-center text-muted small py-2"><div class="spinner-border spinner-border-sm me-1"></div>লোড হচ্ছে...</div>';

    fetch(BASE_URL + '/api/get_outstanding_sales.php?customer_id=' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                document.getElementById('outstandingSalesList').innerHTML =
                    '<div class="text-muted small text-center py-2">লোড করতে সমস্যা হয়েছে</div>';
                return;
            }
            renderOutstandingSales(res.data);
        });
}

function renderOutstandingSales(sales) {
    const el = document.getElementById('outstandingSalesList');
    if (!sales.length) {
        el.innerHTML = '<div class="text-success small text-center py-2"><i class="bi bi-check-circle me-1"></i>এই গ্রাহকের কোনো বাকি নেই</div>';
        return;
    }

    el.innerHTML = sales.map(s => `
        <div class="d-flex align-items-center justify-content-between p-2 rounded
                    border mb-1 sale-row cursor-pointer"
             id="srow_${s.id}"
             onclick="selectSale(${s.id}, ${s.due_amount})"
             style="cursor:pointer">
            <div>
                <span class="fw-semibold small">${esc(s.invoice_number)}</span>
                <span class="text-muted small ms-2">${s.sale_date}</span>
            </div>
            <div class="text-end">
                <span class="text-muted small">বাকি: </span>
                <span class="badge bg-danger">${fmt(s.due_amount)}</span>
            </div>
        </div>
    `).join('');
}

function selectSale(saleId, due) {
    // Deselect all
    document.querySelectorAll('.sale-row').forEach(el => {
        el.classList.remove('border-primary', 'bg-primary', 'text-white');
        el.style.background = '';
    });
    // Select clicked
    const row = document.getElementById('srow_' + saleId);
    if (row) {
        row.style.background = '#e8f0fe';
        row.style.borderColor = '#0d6efd';
    }
    document.getElementById('selectedSaleId').value = saleId;
    document.getElementById('payAmount').value = parseFloat(due).toFixed(2);
    document.getElementById('payAmount').focus();
}

// ---- Submit Payment ----
function submitPayment(e) {
    e.preventDefault();
    const btn = document.getElementById('submitPayBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>অপেক্ষা করুন...';

    const data = {
        customer_id:    document.getElementById('payCustomerId').value,
        amount:         document.getElementById('payAmount').value,
        payment_method: document.getElementById('payMethod').value,
        reference_no:   document.getElementById('payRefNo').value,
        payment_date:   document.getElementById('payDate').value,
        note:           document.getElementById('payNote').value,
        sale_id:        document.getElementById('selectedSaleId').value,
    };

    ajaxPost(BASE_URL + '/api/add_payment.php', data, res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>পেমেন্ট সংরক্ষণ করুন';

        if (res.success) {
            showToast(res.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('paymentModal'))?.hide();
            document.getElementById('paymentForm').reset();
            tsSyncForm('paymentForm');
            document.getElementById('payDate').value = new Date().toISOString().slice(0, 10);
            document.getElementById('outstandingSection').classList.add('d-none');
            document.getElementById('selectedSaleId').value = '';
        } else {
            showToast(res.message, 'danger');
        }
    });
}

// ---- Open payment modal ----
function openPaymentModal(customerId) {
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    if (customerId) {
        const sel = document.getElementById('payCustomerId');
        tsSet(sel, customerId, true);
        onCustomerChange(sel);
    }
    modal.show();
}

function goToPayment(customerId) {
    openPaymentModal(customerId);
}

function goToLedger(customerId) {
    window.location.href = BASE_URL + '/pages/khata.php?customer_id=' + customerId;
}

// ---- Payment History ----
const PAY_PAGE_SIZE = 50;
let _allPayments    = [];
let _payPage        = 1;

function buildPageNav(total, page, pageSize, barId, infoId, navId, onPageFn) {
    const bar        = document.getElementById(barId);
    const totalPages = Math.ceil(total / pageSize);
    const from       = (page - 1) * pageSize + 1;
    const to         = Math.min(page * pageSize, total);
    document.getElementById(infoId).textContent = `${total} টির মধ্যে ${from}–${to} দেখাচ্ছে`;
    if (totalPages <= 1) { bar.style.display = 'none'; return; }
    bar.style.removeProperty('display');
    let html = `<li class="page-item ${page===1?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();${onPageFn}(${page-1})">&#8249;</a></li>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages-1 && Math.abs(i-page) > 1) {
            if (i === 3 || i === totalPages-2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        html += `<li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" onclick="event.preventDefault();${onPageFn}(${i})">${i}</a></li>`;
    }
    html += `<li class="page-item ${page===totalPages?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();${onPageFn}(${page+1})">&#8250;</a></li>`;
    document.getElementById(navId).innerHTML = html;
}

function loadHistory() {
    const params = new URLSearchParams();
    const df  = document.getElementById('hDateFrom').value;
    const dt  = document.getElementById('hDateTo').value;
    const cid = document.getElementById('hCustomer').value;
    if (df)  params.set('date_from',   df);
    if (dt)  params.set('date_to',     dt);
    if (cid) params.set('customer_id', cid);

    document.getElementById('historyBody').innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...</td></tr>';
    document.getElementById('payPaginationBar').style.display = 'none';

    fetch(BASE_URL + '/api/get_payments.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            if (res.success) { _allPayments = res.data; _payPage = 1; renderPayPage(1); }
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderPayPage(page) {
    _payPage = page;
    const tbody  = document.getElementById('historyBody');
    const mLabel = { cash: 'নগদ', mobile_banking: 'মোবাইল ব্যাং', cheque: 'চেক' };

    if (!_allPayments.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">কোনো পেমেন্ট রেকর্ড নেই</td></tr>';
        document.getElementById('payPaginationBar').style.display = 'none';
        return;
    }

    const start    = (page - 1) * PAY_PAGE_SIZE;
    const pageData = _allPayments.slice(start, start + PAY_PAGE_SIZE);

    tbody.innerHTML = pageData.map(p => `
        <tr>
            <td>${p.payment_date}</td>
            <td class="fw-semibold">${esc(p.customer_name)}</td>
            <td>${p.invoice_number ? `<span class="badge bg-secondary">${esc(p.invoice_number)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td><span class="badge bg-info text-dark">${mLabel[p.payment_method] || p.payment_method}</span></td>
            <td class="text-muted small">${esc(p.reference_no || '—')}</td>
            <td class="text-end fw-semibold text-success">${fmt(p.amount)}</td>
            <td class="text-muted small">${esc(p.note || '—')}</td>
        </tr>`).join('');

    buildPageNav(_allPayments.length, page, PAY_PAGE_SIZE, 'payPaginationBar', 'payPageInfo', 'payPagination', 'renderPayPage');
}

function renderHistory(payments) {
    _allPayments = payments;
    _payPage     = 1;
    renderPayPage(1);
}

// ---- Tab event bindings ----
document.getElementById('historyTabBtn')?.addEventListener('click', () => {
    setTimeout(loadHistory, 50);
});

// Paginate server-rendered due list
document.addEventListener('DOMContentLoaded', () => {
    if (typeof paginateTable === 'function') paginateTable('dueListBody', 50);
});

// ---- Due list search ----
// pagination.js hides off-page rows via inline style.display; while a search
// is active we bypass pagination entirely (show every match, hide its nav),
// and restore normal paging once the search is cleared.
const dueSearch = document.getElementById('dueSearch');

function duePaginationNav() {
    const tbody = document.getElementById('dueListBody');
    const table = tbody?.closest('table');
    const anchor = table?.closest('.table-responsive') || table;
    return anchor?.parentElement?.querySelector(':scope > .table-pagination') || null;
}

function applyDueFilter() {
    const q = (dueSearch?.value || '').trim().toLowerCase();
    const rows = document.querySelectorAll('#dueListBody tr.due-row');
    let anyVisible = false;

    if (q) {
        rows.forEach(r => {
            const match = r.dataset.name.includes(q) || r.dataset.phone.includes(q);
            r.style.display = '';
            r.classList.toggle('d-none', !match);
            if (match) anyVisible = true;
        });
        const nav = duePaginationNav();
        if (nav) nav.style.display = 'none';
    } else {
        rows.forEach(r => r.classList.remove('d-none'));
        if (typeof paginateTable === 'function') paginateTable('dueListBody', 50);
        anyVisible = rows.length > 0;
    }

    const noMatch = document.getElementById('dueNoMatch');
    const showNoMatch = !!q && !anyVisible;
    if (noMatch) noMatch.classList.toggle('d-none', !showNoMatch);
    const table = document.getElementById('dueListBody')?.closest('table');
    if (table) table.classList.toggle('d-none', showNoMatch);
}

dueSearch?.addEventListener('input', applyDueFilter);
document.getElementById('btnClearDueSearch')?.addEventListener('click', () => {
    if (dueSearch) dueSearch.value = '';
    applyDueFilter();
});

// Keep due list fresh if revisited (it's server-rendered, but just in case)

// ============================================
// Customer account notes
// ============================================

const notesModal = new bootstrap.Modal(document.getElementById('notesModal'));

function openNotes(customerId, name) {
    document.getElementById('notesCustomerName').textContent = name;
    const idField = document.getElementById('noteCustomerId');
    if (idField) idField.value = customerId;
    const form = document.getElementById('noteForm');
    if (form) form.reset();
    notesModal.show();
    loadNotes(customerId);
}

function loadNotes(customerId) {
    const list = document.getElementById('notesList');
    list.innerHTML = '<div class="text-center text-muted py-3">লোড হচ্ছে...</div>';
    fetch(`${BASE_URL}/api/get_customer_notes.php?customer_id=${customerId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { list.innerHTML = `<div class="text-danger small">${esc(res.message)}</div>`; return; }
            renderNotes(res.notes || (res.data && res.data.notes) || []);
        })
        .catch(() => { list.innerHTML = '<div class="text-danger small">লোড করা যায়নি।</div>'; });
}

function renderNotes(notes) {
    const list = document.getElementById('notesList');
    if (!notes.length) {
        list.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-inbox d-block fs-4 mb-1"></i>কোনো নোট নেই</div>';
        return;
    }
    list.innerHTML = notes.map(n => `
        <div class="border-start border-3 border-info ps-3 py-2 mb-2 bg-light rounded">
            <div class="d-flex justify-content-between align-items-start">
                <div class="small text-muted">
                    <i class="bi bi-person-circle me-1"></i>${esc(n.author || 'অজানা')}
                    <span class="ms-2"><i class="bi bi-clock me-1"></i>${esc(n.created_at)}</span>
                </div>
                ${IS_ADMIN ? `<button class="btn btn-sm btn-link text-danger p-0" onclick="deleteNote(${n.id})" title="মুছুন"><i class="bi bi-trash"></i></button>` : ''}
            </div>
            <div class="mt-1">${esc(n.note).replace(/\n/g, '<br>')}</div>
        </div>
    `).join('');
}

function submitNote(e) {
    e.preventDefault();
    const customerId = document.getElementById('noteCustomerId').value;
    const note       = document.getElementById('noteText').value.trim();
    if (!note) { showToast('নোট লিখুন', 'warning'); return; }

    const btn = document.getElementById('noteSaveBtn');
    btn.disabled = true;
    ajaxPost(BASE_URL + '/api/add_customer_note.php', { customer_id: customerId, note }, res => {
        btn.disabled = false;
        if (res.success) {
            document.getElementById('noteText').value = '';
            showToast(res.message, 'success');
            loadNotes(customerId);
        } else {
            showToast(res.message, 'danger');
        }
    });
}

function deleteNote(id) {
    if (!confirm('এই নোটটি মুছে ফেলবেন?')) return;
    ajaxPost(BASE_URL + '/api/delete_customer_note.php', { id }, res => {
        if (res.success) {
            showToast(res.message, 'success');
            loadNotes(document.getElementById('noteCustomerId').value);
        } else {
            showToast(res.message, 'danger');
        }
    });
}
