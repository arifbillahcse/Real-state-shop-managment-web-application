/* global BASE_URL, PRE_SEL, SHOP_NAME, TomSelect */

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

let _currentLedgerData = null;

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

function buildRows(data) {
    const { sales, payments } = data;
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
    return allRows;
}

function renderLedger(data) {
    _currentLedgerData = data;
    const { customer, summary } = data;

    const allRows = buildRows(data);

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
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="printLedger()">
                    <i class="bi bi-printer me-1"></i>প্রিন্ট করুন
                </button>
                <a href="${BASE_URL}/pages/payments.php" class="btn btn-sm btn-success">
                    <i class="bi bi-cash-coin me-1"></i>পেমেন্ট নিন
                </a>
            </div>
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

function printLedger() {
    if (!_currentLedgerData) return;

    const { customer, summary } = _currentLedgerData;
    const allRows = buildRows(_currentLedgerData);
    const sum = summary || {};
    const due = parseFloat(sum.total_due || 0);
    const printDate = new Date().toLocaleDateString('bn-BD', {
        year: 'numeric', month: 'long', day: 'numeric'
    });

    let sl = 0;
    const tableRows = allRows.map(r => {
        sl++;
        if (r.type === 'sale') {
            return `<tr>
                <td style="text-align:center">${sl}</td>
                <td>${r.date}</td>
                <td><span class="badge-sale">বিক্রয়</span></td>
                <td>${r.invoice || '—'}</td>
                <td style="text-align:right">${fmt(r.total)}</td>
                <td style="text-align:right">${fmt(r.paid)}</td>
                <td style="text-align:right; ${r.due > 0 ? 'color:#c00;font-weight:600' : 'color:green'}">${fmt(r.due)}</td>
            </tr>`;
        } else {
            return `<tr style="background:#f0fff4">
                <td style="text-align:center">${sl}</td>
                <td>${r.date}</td>
                <td><span class="badge-pay">পেমেন্ট</span></td>
                <td>${r.invoice || 'সাধারণ'}</td>
                <td style="text-align:right">—</td>
                <td style="text-align:right;color:green;font-weight:600">${fmt(r.amount)}</td>
                <td style="text-align:right">—</td>
            </tr>`;
        }
    }).join('');

    const shopName = (typeof SHOP_NAME !== 'undefined' ? SHOP_NAME : '') || 'খাতা';

    const html = `<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>${shopName} — ${customer.name} এর খাতা</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111; background: #fff; padding: 20px; }

  .header { text-align: center; border-bottom: 2px solid #c00; padding-bottom: 10px; margin-bottom: 16px; }
  .header h1 { font-size: 22px; color: #c00; font-weight: 700; }
  .header p  { font-size: 12px; color: #555; margin-top: 2px; }

  .info-box { display: flex; justify-content: space-between; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
  .info-box .left h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .info-box .left p  { font-size: 11px; color: #555; }
  .info-box .right   { text-align: right; }
  .info-box .right p { font-size: 11px; color: #555; }

  .summary { display: flex; gap: 0; margin-bottom: 16px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
  .summary-item { flex: 1; text-align: center; padding: 10px 8px; border-right: 1px solid #ddd; }
  .summary-item:last-child { border-right: none; }
  .summary-item .label { font-size: 10px; color: #666; margin-bottom: 4px; }
  .summary-item .value { font-size: 15px; font-weight: 700; }
  .summary-item .value.green { color: #198754; }
  .summary-item .value.red   { color: #c00; }

  table { width: 100%; border-collapse: collapse; font-size: 11px; }
  thead th { background: #222; color: #fff; padding: 7px 8px; text-align: left; }
  thead th.right { text-align: right; }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
  tfoot td { padding: 7px 8px; background: #222; color: #fff; font-weight: 700; }
  tfoot td.right { text-align: right; }
  tfoot td.green { color: #4caf50; }
  tfoot td.yellow { color: #ffc107; }

  .badge-sale { background: #0d6efd; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; }
  .badge-pay  { background: #198754; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; }

  .footer { margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 10px; color: #888; }

  @media print {
    body { padding: 0; }
    @page { margin: 15mm; size: A4 portrait; }
  }
</style>
</head>
<body>

<div class="header">
  <h1>${shopName}</h1>
  <p>কাস্টমার খাতা — লেনদেনের সম্পূর্ণ বিবরণ</p>
</div>

<div class="info-box">
  <div class="left">
    <h2>${customer.name}</h2>
    ${customer.phone    ? `<p>মোবাইল: ${customer.phone}</p>` : ''}
    ${customer.address  ? `<p>ঠিকানা: ${customer.address}</p>` : ''}
    ${customer.email    ? `<p>ইমেইল: ${customer.email}</p>` : ''}
  </div>
  <div class="right">
    <p><strong>প্রিন্টের তারিখ:</strong> ${printDate}</p>
    <p><strong>মোট লেনদেন:</strong> ${allRows.length} টি</p>
  </div>
</div>

<div class="summary">
  <div class="summary-item">
    <div class="label">মোট ক্রয়</div>
    <div class="value">${fmt(sum.total_purchase || 0)}</div>
  </div>
  <div class="summary-item">
    <div class="label">মোট পরিশোধ</div>
    <div class="value green">${fmt(sum.total_paid || 0)}</div>
  </div>
  <div class="summary-item">
    <div class="label">বর্তমান বাকি</div>
    <div class="value ${due > 0 ? 'red' : 'green'}">${fmt(due)}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th style="width:30px;text-align:center">#</th>
      <th style="width:90px">তারিখ</th>
      <th style="width:70px">ধরন</th>
      <th>ইনভয়েস</th>
      <th class="right" style="width:110px">বিক্রয় (৳)</th>
      <th class="right" style="width:110px">পরিশোধ (৳)</th>
      <th class="right" style="width:110px">বাকি (৳)</th>
    </tr>
  </thead>
  <tbody>
    ${tableRows || '<tr><td colspan="7" style="text-align:center;padding:20px;color:#888">কোনো লেনদেন নেই</td></tr>'}
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4">সারসংক্ষেপ</td>
      <td class="right">${fmt(sum.total_purchase || 0)}</td>
      <td class="right green">${fmt(sum.total_paid || 0)}</td>
      <td class="right ${due > 0 ? 'yellow' : 'green'}">${fmt(due)}</td>
    </tr>
  </tfoot>
</table>

<div class="footer">
  <span>${shopName} — সফটওয়্যার দ্বারা মুদ্রিত</span>
  <span>মুদ্রণের তারিখ: ${printDate}</span>
</div>

<script>
  window.onload = function() { window.print(); };
<\/script>
</body>
</html>`;

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(html);
    win.document.close();
}

// Auto-load if customer_id passed in URL
if (PRE_SEL > 0) {
    if (ts) ts.setValue(String(PRE_SEL));
    loadLedger();
}

sel.addEventListener('change', () => {
    if (sel.value) loadLedger();
});
