/* global BASE_URL, PRE_SEL, TomSelect */

const sel = document.getElementById('ledgerCustomer');
let ts;
if (window.TomSelect) {
    ts = new TomSelect(sel, { maxOptions: 200 });
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function fmt(v) {
    return parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ৳';
}

function loadLedger() {
    const cid = sel.value;
    if (!cid) {
        document.getElementById('ledgerContent').innerHTML =
            '<div class="alert alert-warning">কাস্টমার নির্বাচন করুন।</div>';
        return;
    }

    document.getElementById('ledgerContent').innerHTML =
        '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

    fetch(BASE_URL + '/api/get_customer_ledger.php?customer_id=' + cid)
        .then(r => r.json())
        .then(res => {
            if (res.success) renderLedger(res.data);
            else document.getElementById('ledgerContent').innerHTML =
                `<div class="alert alert-danger">${esc(res.message)}</div>`;
        })
        .catch(() => {
            document.getElementById('ledgerContent').innerHTML =
                '<div class="alert alert-danger">ডেটা লোড করতে সমস্যা হয়েছে।</div>';
        });
}

function renderLedger(data) {
    const { customer, sales, payments, summary } = data;
    const mLabel = { cash: 'নগদ', credit: 'বাকি', mobile_banking: 'মো.ব্যাং', cheque: 'চেক' };

    const allRows = [];

    (sales || []).forEach(s => {
        allRows.push({
            date:    s.sale_date,
            type:    'sale',
            invoice: s.invoice_number,
            total:   parseFloat(s.total_amount),
            paid:    parseFloat(s.paid_amount),
            due:     parseFloat(s.due_amount),
        });
    });

    (payments || []).forEach(p => {
        allRows.push({
            date:    p.txn_date,
            type:    'payment',
            invoice: p.linked_invoice || '',
            amount:  parseFloat(p.amount),
        });
    });

    allRows.sort((a, b) => a.date.localeCompare(b.date));

    const rows = allRows.map(r => {
        if (r.type === 'sale') {
            return `<tr>
                <td>${r.date}</td>
                <td><span class="badge bg-primary">বিক্রয়</span></td>
                <td>${r.invoice ? `<span class="badge bg-secondary">${esc(r.invoice)}</span>` : '—'}</td>
                <td class="text-end">${fmt(r.total)}</td>
                <td class="text-end text-success">${fmt(r.paid)}</td>
                <td class="text-end ${r.due > 0 ? 'text-danger fw-semibold' : 'text-success'}">${fmt(r.due)}</td>
            </tr>`;
        } else {
            return `<tr class="table-success">
                <td>${r.date}</td>
                <td><span class="badge bg-success">পেমেন্ট</span></td>
                <td>${r.invoice ? `<span class="text-muted small">${esc(r.invoice)}</span>` : '<span class="text-muted small">সাধারণ</span>'}</td>
                <td class="text-end text-muted">—</td>
                <td class="text-end fw-semibold text-success">${fmt(r.amount)}</td>
                <td class="text-end text-muted">—</td>
            </tr>`;
        }
    }).join('');

    const sum = summary || {};
    const due = parseFloat(sum.total_due || 0);

    document.getElementById('ledgerContent').innerHTML = `
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold fs-6">
                <i class="bi bi-person-circle me-1"></i>${esc(customer.name)}
                ${customer.phone ? `<span class="text-muted small ms-2">${esc(customer.phone)}</span>` : ''}
            </span>
            <a href="${BASE_URL}/pages/payments.php" class="btn btn-sm btn-success">
                <i class="bi bi-cash-coin me-1"></i>পেমেন্ট নিন
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="text-muted small">মোট ক্রয়</div>
                    <div class="fw-bold fs-5">${fmt(sum.total_purchase || 0)}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">মোট পরিশোধ</div>
                    <div class="fw-bold fs-5 text-success">${fmt(sum.total_paid || 0)}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">বর্তমান বাকি</div>
                    <div class="fw-bold fs-5 ${due > 0 ? 'text-danger' : 'text-success'}">${fmt(due)}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-list-ul me-1"></i>লেনদেনের ইতিহাস
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>তারিখ</th>
                        <th>ধরন</th>
                        <th>ইনভয়েস</th>
                        <th class="text-end">বিক্রয় (৳)</th>
                        <th class="text-end">পরিশোধ (৳)</th>
                        <th class="text-end">বাকি (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows || '<tr><td colspan="6" class="text-center text-muted py-4">কোনো লেনদেন নেই</td></tr>'}
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="3">সারসংক্ষেপ</td>
                        <td class="text-end">${fmt(sum.total_purchase || 0)}</td>
                        <td class="text-end text-success">${fmt(sum.total_paid || 0)}</td>
                        <td class="text-end ${due > 0 ? 'text-warning' : 'text-success'}">${fmt(due)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>`;
}

// Auto-load if customer_id passed in URL
if (PRE_SEL > 0) {
    if (ts) ts.setValue(String(PRE_SEL));
    loadLedger();
}

sel.addEventListener('change', () => {
    if (sel.value) loadLedger();
});
