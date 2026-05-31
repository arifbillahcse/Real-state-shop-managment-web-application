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
        const stock = parseFloat(p.current_stock ?? 0);
        const unit  = p.unit;
        const disabled = stock <= 0 ? 'disabled' : '';
        return `<option value="${pid}" ${disabled}
                     data-price="${price}"
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
            const cur = sel.value;
            sel.innerHTML = '<option value="">-- পণ্য নির্বাচন করুন --</option>' + productOptsHtml;
            sel.value = cur;
        });

        if (!branchId) return;

        try {
            const res  = await fetch(`${BASE_URL}/api/get_branch_stock.php?branch_id=${branchId}`);
            const data = await res.json();
            if (!data.success) return;

            productOptsHtml = buildProductOpts(data.stock);
            document.querySelectorAll('.product-select').forEach(sel => {
                const cur = sel.value;
                sel.innerHTML = '<option value="">-- পণ্য নির্বাচন করুন --</option>' + productOptsHtml;
                sel.value = cur;
            });
        } catch { /* keep global stock on error */ }
    });
}

// ---- Item Rows ----
function addItemRow() {
    rowCounter++;
    const id = rowCounter;

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
            <input type="number" class="form-control form-control-sm qty-input"
                   min="0.01" step="0.01" placeholder="০" required
                   oninput="calcRow(${id})">
            <small class="text-muted unit-label"></small>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm price-input"
                   min="0.01" step="0.01" placeholder="০.০০" required
                   oninput="calcRow(${id})">
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

function onProductSelect(sel, id) {
    const opt = sel.options[sel.selectedIndex];
    const row = document.getElementById('item_row_' + id);
    if (!row || !opt.value) return;
    row.querySelector('.price-input').value = opt.dataset.price || '';
    row.querySelector('.unit-label').textContent =
        opt.dataset.unit ? '(' + opt.dataset.unit + ')' : '';
    calcRow(id);
}

function calcRow(id) {
    const row = document.getElementById('item_row_' + id);
    if (!row) return;
    const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    const total = qty * price;
    document.getElementById('row_total_' + id).textContent = total > 0 ? fmt(total) : '—';
    calcGrandTotal();
}

function calcGrandTotal() {
    let subtotal = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.qty-input')?.value) || 0;
        const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
        subtotal += qty * price;
    });
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total    = Math.max(0, subtotal - discount);
    const paid     = parseFloat(document.getElementById('paidAmount').value) || 0;
    const due      = Math.max(0, total - paid);

    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('totalDisplay').textContent    = fmt(total);
    document.getElementById('dueDisplay').textContent      = fmt(due);
}

function collectItems() {
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
            });
        }
    });
    return items;
}

// ---- Submit Sale ----
function submitSale(e) {
    e.preventDefault();
    const items = collectItems();
    if (items.length === 0) {
        showToast('কমপক্ষে একটি পণ্য যোগ করুন', 'danger');
        return;
    }

    const btn = document.getElementById('submitSaleBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>অপেক্ষা করুন...';

    const data = {
        customer_id:    document.getElementById('saleCustomerId').value,
        branch_id:      document.getElementById('saleBranchId')?.value || '',
        sale_date:      document.getElementById('saleDate').value,
        discount:       document.getElementById('discount').value,
        paid_amount:    document.getElementById('paidAmount').value,
        payment_method: document.getElementById('paymentMethod').value,
        note:           document.getElementById('saleNote').value,
        items:          JSON.stringify(items),
    };

    ajaxPost(BASE_URL + '/api/create_sale.php', data, res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>বিক্রয় সম্পন্ন করুন';

        if (res.success) {
            showToast(res.message + (res.invoice_number ? ' — ' + res.invoice_number : ''), 'success');
            resetSaleForm();
            if (res.sale_id) showInvoice(res.sale_id);
        } else {
            showToast(res.message, 'danger');
        }
    });
}

function resetSaleForm() {
    document.getElementById('saleForm').reset();
    document.getElementById('saleDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('itemsBody').innerHTML = '';
    rowCounter = 0;
    // Reset product opts to global stock after form reset
    productOptsHtml = buildProductOpts(PRODUCTS);
    checkEmptyState();
    calcGrandTotal();
    addItemRow(); // start with one empty row
}

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
    document.getElementById('salesFooter').innerHTML = '';

    fetch(BASE_URL + '/api/get_sales.php?' + params.toString())
        .then(r => r.json())
        .then(res => { if (res.success) renderSalesTable(res.data); })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderSalesTable(sales) {
    const tbody = document.getElementById('salesBody');
    const tfoot = document.getElementById('salesFooter');

    const cols = HAS_BRANCHES ? 10 : 9;

    if (!sales.length) {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-5 text-muted">কোনো বিক্রয় রেকর্ড নেই</td></tr>`;
        tfoot.innerHTML = '';
        return;
    }

    let totTotal = 0, totPaid = 0, totDue = 0;

    tbody.innerHTML = sales.map(s => {
        const cancelled = s.status === 'cancelled';
        totTotal += parseFloat(s.total_amount);
        totPaid  += parseFloat(s.paid_amount);
        totDue   += parseFloat(s.due_amount);
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
            </td>
            <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-info me-1"
                        onclick="showInvoice(${s.id})" title="ইনভয়েস দেখুন">
                    <i class="bi bi-file-text"></i>
                </button>
                ${IS_ADMIN && !IS_STAFF && !cancelled ? `
                <button class="btn btn-sm btn-outline-danger"
                        onclick="cancelSale(${s.id}, '${esc(s.invoice_number)}')"
                        title="বাতিল করুন">
                    <i class="bi bi-x-circle"></i>
                </button>` : ''}
            </td>
        </tr>`;
    }).join('');

    const footCols = HAS_BRANCHES ? 5 : 4;
    tfoot.innerHTML = `
        <tr class="table-dark fw-bold">
            <td colspan="${footCols}">সর্বমোট (${sales.length} টি বিক্রয়)</td>
            <td class="text-end">${fmt(totTotal)}</td>
            <td class="text-end">${fmt(totPaid)}</td>
            <td class="text-end text-warning">${fmt(totDue)}</td>
            <td colspan="2"></td>
        </tr>`;
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
            renderInvoice(res);
        })
        .catch(() => showToast('ইনভয়েস লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderInvoice(res) {
    const s = res.data;
    const payLabel = { cash: 'নগদ', credit: 'বাকি', mobile_banking: 'মোবাইল ব্যাংকিং', cheque: 'চেক' };

    const itemRows = s.items.map(item => `
        <tr>
            <td>${esc(item.product_name)}</td>
            <td class="text-center">${parseFloat(item.quantity)} ${esc(item.unit)}</td>
            <td class="text-end">${fmt(item.unit_price)}</td>
            <td class="text-end fw-semibold">${fmt(item.total_price)}</td>
        </tr>
    `).join('');

    document.getElementById('invoiceContent').innerHTML = `
    <div id="printArea">
        <div class="text-center mb-3">
            <h5 class="fw-bold mb-1">${esc(res.shop_name)}</h5>
            ${res.shop_address ? `<div class="small text-muted">${esc(res.shop_address)}</div>` : ''}
            ${res.shop_phone   ? `<div class="small text-muted"><i class="bi bi-telephone"></i> ${esc(res.shop_phone)}</div>` : ''}
        </div>
        <hr>
        <div class="row mb-3">
            <div class="col-6">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><th>ইনভয়েস নং:</th><td class="fw-semibold">${esc(s.invoice_number)}</td></tr>
                    <tr><th>তারিখ:</th><td>${s.sale_date}</td></tr>
                    ${s.branch_name ? `<tr><th>ব্রাঞ্চ:</th><td>${esc(s.branch_name)}</td></tr>` : ''}
                    <tr><th>পেমেন্ট:</th><td>${payLabel[s.payment_method] || s.payment_method}</td></tr>
                    <tr><th>স্ট্যাটাস:</th>
                        <td><span class="badge bg-${s.status==='cancelled'?'secondary':'success'}">
                            ${s.status==='cancelled'?'বাতিল':'সম্পন্ন'}
                        </span></td>
                    </tr>
                </table>
            </div>
            <div class="col-6 text-end">
                <div class="fw-semibold">${esc(s.customer_name)}</div>
                ${s.customer_phone ? `<div class="text-muted small">${esc(s.customer_phone)}</div>` : ''}
            </div>
        </div>

        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>পণ্য</th>
                    <th class="text-center">পরিমাণ</th>
                    <th class="text-end">একক মূল্য</th>
                    <th class="text-end">মোট</th>
                </tr>
            </thead>
            <tbody>${itemRows}</tbody>
        </table>

        <div class="row justify-content-end">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><td class="text-muted">সাবটোটাল</td>
                        <td class="text-end">${fmt(s.subtotal)}</td></tr>
                    ${parseFloat(s.discount) > 0 ? `
                    <tr><td class="text-muted">ছাড়</td>
                        <td class="text-end text-danger">- ${fmt(s.discount)}</td></tr>` : ''}
                    <tr class="table-dark">
                        <td class="fw-bold">মোট</td>
                        <td class="text-end fw-bold">${fmt(s.total_amount)}</td>
                    </tr>
                    <tr><td class="text-muted">পরিশোধ</td>
                        <td class="text-end">${fmt(s.paid_amount)}</td></tr>
                    <tr class="table-warning">
                        <td class="fw-semibold">বাকি</td>
                        <td class="text-end fw-bold text-danger">${fmt(s.due_amount)}</td>
                    </tr>
                </table>
            </div>
        </div>
        ${s.note ? `<div class="text-muted small mt-2 border-top pt-2">নোট: ${esc(s.note)}</div>` : ''}
    </div>`;
}

function printInvoice() {
    const el = document.getElementById('printArea');
    if (!el) return;
    const win = window.open('', '_blank', 'width=700,height=800');
    win.document.write(`<!DOCTYPE html>
    <html><head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Invoice</title>
    <style>
        body { padding: 20px; font-size: 13px; font-family: sans-serif; }
        @media print { body { padding: 5px; } .badge { border: 1px solid #666; } }
    </style>
    </head><body>
    ${el.innerHTML}
    <script>window.onload = function(){ window.print(); window.close(); };<\/script>
    </body></html>`);
    win.document.close();
}

// ---- History tab trigger ----
document.getElementById('historyTabBtn')?.addEventListener('click', () => {
    setTimeout(loadSalesHistory, 50);
});

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
    if (!IS_STAFF) {
        addItemRow();
        calcGrandTotal();
    }
    // History tab is always default — load on page load
    loadSalesHistory();
});
