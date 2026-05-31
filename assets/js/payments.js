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

// ---- Quick navigation from Due List tab ----
function goToPayment(customerId) {
    // Switch to pay tab and pre-select customer
    const payTabBtn = document.querySelector('[data-bs-target="#payTab"]');
    bootstrap.Tab.getOrCreateInstance(payTabBtn).show();
    const sel = document.getElementById('payCustomerId');
    tsSet(sel, customerId, true);
    onCustomerChange(sel);
}

function goToLedger(customerId) {
    const tabBtn = document.getElementById('ledgerTabBtn');
    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    tsSet('ledgerCustomer', customerId, true);
    loadLedger();
}

// ---- Payment History ----
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
    document.getElementById('historyFooter').innerHTML = '';

    fetch(BASE_URL + '/api/get_payments.php?' + params.toString())
        .then(r => r.json())
        .then(res => { if (res.success) renderHistory(res.data); })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderHistory(payments) {
    const tbody  = document.getElementById('historyBody');
    const tfoot  = document.getElementById('historyFooter');
    const mLabel = { cash: 'নগদ', mobile_banking: 'মোবাইল ব্যাং', cheque: 'চেক' };

    if (!payments.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">কোনো পেমেন্ট রেকর্ড নেই</td></tr>';
        tfoot.innerHTML = '';
        return;
    }

    let total = 0;
    tbody.innerHTML = payments.map(p => {
        total += parseFloat(p.amount);
        return `
        <tr>
            <td>${p.payment_date}</td>
            <td class="fw-semibold">${esc(p.customer_name)}</td>
            <td>${p.invoice_number ? `<span class="badge bg-secondary">${esc(p.invoice_number)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td><span class="badge bg-info text-dark">${mLabel[p.payment_method] || p.payment_method}</span></td>
            <td class="text-muted small">${esc(p.reference_no || '—')}</td>
            <td class="text-end fw-semibold text-success">${fmt(p.amount)}</td>
            <td class="text-muted small">${esc(p.note || '—')}</td>
        </tr>`;
    }).join('');

    tfoot.innerHTML = `
        <tr class="table-dark fw-bold">
            <td colspan="5">মোট (${payments.length} টি)</td>
            <td class="text-end text-success">${fmt(total)}</td>
            <td></td>
        </tr>`;
}

// ---- Customer Ledger ----
function loadLedger() {
    const cid = document.getElementById('ledgerCustomer').value;
    if (!cid) { showToast('কাস্টমার নির্বাচন করুন', 'warning'); return; }

    const el = document.getElementById('ledgerContent');
    el.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    fetch(BASE_URL + '/api/get_customer_ledger.php?customer_id=' + cid)
        .then(r => r.json())
        .then(res => {
            if (res.success) renderLedger(res.data);
            else el.innerHTML = `<div class="alert alert-danger">${esc(res.message)}</div>`;
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderLedger(data) {
    const { customer, sales, payments, summary } = data;
    const mLabel = { cash: 'নগদ', credit: 'বাকি', mobile_banking: 'মো.ব্যাং', cheque: 'চেক' };

    const salesRows = sales.length
        ? sales.map(s => `
            <tr>
                <td>${s.sale_date}</td>
                <td><span class="badge bg-primary">বিক্রয়</span></td>
                <td>${esc(s.invoice_number)}</td>
                <td class="text-end">${fmt(s.total_amount)}</td>
                <td class="text-end text-success">${fmt(s.paid_amount)}</td>
                <td class="text-end ${parseFloat(s.due_amount) > 0 ? 'text-danger fw-semibold' : 'text-success'}">${fmt(s.due_amount)}</td>
            </tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted">কোনো বিক্রয় নেই</td></tr>';

    const payRows = payments.length
        ? payments.map(p => `
            <tr class="table-success bg-opacity-25">
                <td>${p.txn_date}</td>
                <td><span class="badge bg-success">পেমেন্ট</span></td>
                <td>${p.linked_invoice
                    ? `<span class="text-muted small">${esc(p.linked_invoice)}</span>`
                    : '<span class="text-muted small">সাধারণ</span>'}</td>
                <td class="text-end">—</td>
                <td class="text-end fw-semibold text-success">${fmt(p.amount)}</td>
                <td class="text-end text-muted">—</td>
            </tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted">কোনো পেমেন্ট নেই</td></tr>';

    const sum = summary || {};

    document.getElementById('ledgerContent').innerHTML = `
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-person-circle me-1"></i>${esc(customer.name)}
                ${customer.phone ? `<span class="text-muted small ms-2">${esc(customer.phone)}</span>` : ''}
            </span>
            <button class="btn btn-sm btn-success" onclick="goToPayment(${customer.id})">
                <i class="bi bi-cash-coin me-1"></i>পেমেন্ট নিন
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4 text-center">
                    <div class="text-muted small">মোট ক্রয়</div>
                    <div class="fw-bold fs-5">${fmt(sum.total_purchase || 0)}</div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="text-muted small">মোট পরিশোধ</div>
                    <div class="fw-bold fs-5 text-success">${fmt(sum.total_paid || 0)}</div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="text-muted small">বর্তমান বাকি</div>
                    <div class="fw-bold fs-5 ${parseFloat(sum.total_due || 0) > 0 ? 'text-danger' : 'text-success'}">
                        ${fmt(sum.total_due || 0)}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-list-ul me-1"></i>লেনদেনের ইতিহাস
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>তারিখ</th>
                        <th>ধরন</th>
                        <th>ইনভয়েস / বিবরণ</th>
                        <th class="text-end">বিক্রয় (৳)</th>
                        <th class="text-end">পরিশোধ (৳)</th>
                        <th class="text-end">বাকি (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    ${salesRows}
                    ${payments.length ? payRows : ''}
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="3">সারসংক্ষেপ</td>
                        <td class="text-end">${fmt(sum.total_purchase || 0)}</td>
                        <td class="text-end text-success">${fmt(sum.total_paid || 0)}</td>
                        <td class="text-end ${parseFloat(sum.total_due || 0) > 0 ? 'text-warning' : 'text-success'}">
                            ${fmt(sum.total_due || 0)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>`;
}

// ---- Tab event bindings ----
document.getElementById('historyTabBtn')?.addEventListener('click', () => {
    setTimeout(loadHistory, 50);
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
