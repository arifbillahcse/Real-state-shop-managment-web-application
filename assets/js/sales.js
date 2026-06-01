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
    tsSyncForm('saleForm');
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

    const discountRow = parseFloat(s.discount) > 0 ? `
        <tr>
            <td colspan="2" style="padding:6px 14px;text-align:right;color:#888;font-size:13px">ছাড়</td>
            <td style="padding:6px 14px;text-align:right;color:#e74c3c;font-size:13px">− ${fmt(s.discount)}</td>
        </tr>` : '';

    const stampColor = isCancelled ? '#95a5a6' : isPaid ? '#27ae60' : '#e74c3c';
    const stampText  = isCancelled ? 'বাতিল' : isPaid ? 'পরিশোধিত' : 'বাকি আছে';
    const stampHTML  = `
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);
                    font-size:52px;font-weight:900;color:${stampColor};opacity:0.08;
                    white-space:nowrap;pointer-events:none;letter-spacing:2px;z-index:0">
            ${stampText}
        </div>`;

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
        <div style="padding:0 32px 8px;position:relative">
            ${stampHTML}
            <table style="width:100%;border-collapse:collapse;position:relative;z-index:1">
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
                ${discountRow}
                <tr style="border-top:2px solid #eee">
                    <td style="padding:8px 16px 8px 0;font-weight:800;font-size:15px;color:#222">মোট</td>
                    <td style="padding:8px 0;text-align:right;font-weight:800;font-size:15px;color:#222">${fmt(s.total_amount)}</td>
                </tr>
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
