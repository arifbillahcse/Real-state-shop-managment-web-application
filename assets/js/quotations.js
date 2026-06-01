/* global BASE_URL, PRODUCTS, CAN_WRITE */
let activeStatus = '', searchTimer = null;
let _lastQuoteRes = null, editQuoteRowCount = 0;
const quoteModal     = new bootstrap.Modal(document.getElementById('quoteModal'));
const viewModal      = new bootstrap.Modal(document.getElementById('viewQuoteModal'));
const editQuoteModal = new bootstrap.Modal(document.getElementById('editQuoteModal'));

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(v) { return parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' ৳'; }

// ── Load list ─────────────────────────────────────────────────────────────────
function loadList() {
    const search = document.getElementById('searchInput').value.trim();
    const p = new URLSearchParams();
    if (activeStatus) p.set('status', activeStatus);
    if (search)       p.set('search', search);
    const el = document.getElementById('quoteList');
    el.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    fetch(BASE_URL + '/api/get_quotations.php?' + p)
        .then(r=>r.json()).then(res => {
            if (!res.success) { el.innerHTML = '<div class="alert alert-danger">লোড ব্যর্থ।</div>'; return; }
            renderList(res.data);
        }).catch(()=>{ el.innerHTML = '<div class="alert alert-danger">সমস্যা হয়েছে।</div>'; });
}

function renderList(rows) {
    const el = document.getElementById('quoteList');
    if (!rows.length) {
        el.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-file-earmark-x fs-1 d-block opacity-25 mb-2"></i>কোনো কোটেশন নেই</div>';
        return;
    }
    const badge = { active:'<span class="badge bg-primary">সক্রিয়</span>',
                    converted:'<span class="badge bg-success">রূপান্তরিত</span>',
                    cancelled:'<span class="badge bg-secondary">বাতিল</span>' };
    el.innerHTML = `<div class="table-responsive"><table class="table table-hover shadow-sm">
        <thead class="table-dark"><tr>
            <th>কোটেশন নং</th><th>কাস্টমার</th><th>তারিখ</th>
            <th>মেয়াদ</th><th class="text-end">মোট</th><th>স্ট্যাটাস</th><th></th>
        </tr></thead><tbody>` +
    rows.map(q => `<tr>
        <td class="fw-semibold">${esc(q.quote_number)}</td>
        <td>${esc(q.customer_name)}</td>
        <td>${esc(q.quote_date)}</td>
        <td class="text-muted small">${q.valid_days} দিন</td>
        <td class="text-end fw-semibold">${fmt(q.total_amount)}</td>
        <td>${badge[q.status]||''}</td>
        <td><button class="btn btn-sm btn-outline-primary" onclick="viewQuote(${q.id})">
            <i class="bi bi-eye"></i></button></td>
    </tr>`).join('') + '</tbody></table></div>';
}

// ── View single ───────────────────────────────────────────────────────────────
function viewQuote(id) {
    const body   = document.getElementById('viewQuoteBody');
    const footer = document.getElementById('viewQuoteFooter');
    body.innerHTML   = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    footer.innerHTML = '';
    viewModal.show();
    fetch(BASE_URL + '/api/get_quotation.php?id=' + id).then(r=>r.json()).then(res => {
        if (!res.success) { body.innerHTML = '<div class="alert alert-danger">'+esc(res.message)+'</div>'; return; }
        _lastQuoteRes = res;
        const q = res.data;
        const badge = { active:'<span class="badge bg-primary">সক্রিয়</span>',
                        converted:'<span class="badge bg-success">রূপান্তরিত</span>',
                        cancelled:'<span class="badge bg-secondary">বাতিল</span>' };
        body.innerHTML = `
        <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
            <div><h6 class="mb-0 fw-bold">${esc(q.quote_number)}</h6>
                 <span class="text-muted small">${esc(q.quote_date)} | মেয়াদ: ${q.valid_days} দিন</span></div>
            <div class="d-flex align-items-center gap-2">
                ${badge[q.status]||''}
                <strong>কাস্টমার:</strong> ${esc(q.customer_name)}
            </div>
        </div>
        <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered">
            <thead class="table-dark"><tr><th>পণ্য</th><th>পরিমাণ</th><th class="text-end">ইউনিট মূল্য</th><th class="text-end">মোট</th></tr></thead>
            <tbody>${(q.items||[]).map(it=>`<tr><td>${esc(it.product_name)}</td><td>${it.quantity}</td><td class="text-end">${fmt(it.unit_price)}</td><td class="text-end">${fmt(it.total_price)}</td></tr>`).join('')}</tbody>
            <tfoot class="table-light fw-bold">
                <tr><td colspan="3" class="text-end">সাবটোটাল</td><td class="text-end">${fmt(q.subtotal)}</td></tr>
                ${parseFloat(q.discount)>0?`<tr><td colspan="3" class="text-end text-danger">ছাড়</td><td class="text-end text-danger">-${fmt(q.discount)}</td></tr>`:''}
                <tr class="table-success"><td colspan="3" class="text-end">মোট</td><td class="text-end">${fmt(q.total_amount)}</td></tr>
            </tfoot>
        </table></div>
        ${q.note ? `<p class="text-muted small"><strong>নোট:</strong> ${esc(q.note)}</p>` : ''}`;

        footer.innerHTML = `
            <button class="btn btn-outline-secondary me-auto" onclick="printQuote()">
                <i class="bi bi-printer me-1"></i>প্রিন্ট
            </button>`;
        if (CAN_WRITE && q.status === 'active') {
            footer.innerHTML += `
            <button class="btn btn-warning" onclick="openEditQuote()">
                <i class="bi bi-pencil-square me-1"></i>সম্পাদনা
            </button>
            <button class="btn btn-secondary" onclick="changeStatus(${q.id},'cancelled')">
                <i class="bi bi-x-circle me-1"></i>বাতিল করুন
            </button>
            <button class="btn btn-success" onclick="changeStatus(${q.id},'converted')">
                <i class="bi bi-check-circle me-1"></i>বিক্রয়ে রূপান্তর করুন
            </button>`;
        }
    });
}

// ── Print quotation ───────────────────────────────────────────────────────────
function buildQuoteHTML(res) {
    const q    = res.data;
    const disc = parseFloat(q.discount) || 0;

    const itemRows = (q.items||[]).map((it, i) => `
        <tr style="background:${i%2===0?'#fff':'#fafafa'}">
            <td style="padding:9px 14px;font-size:13px;color:#222;border-bottom:1px solid #eee">${esc(it.product_name)}</td>
            <td style="padding:9px 14px;text-align:center;font-size:13px;color:#555;border-bottom:1px solid #eee">${it.quantity}</td>
            <td style="padding:9px 14px;text-align:right;font-size:13px;color:#555;border-bottom:1px solid #eee">${fmt(it.unit_price)}</td>
            <td style="padding:9px 14px;text-align:right;font-size:13px;font-weight:600;color:#222;border-bottom:1px solid #eee">${fmt(it.total_price)}</td>
        </tr>`).join('');

    const statusLabel = { active:'সক্রিয়', converted:'রূপান্তরিত', cancelled:'বাতিল' };
    const statusColor = { active:'#2563eb', converted:'#16a34a', cancelled:'#6b7280' };
    const sc = statusColor[q.status] || '#6b7280';

    return `
    <div style="font-family:'Hind Siliguri','Segoe UI',sans-serif;max-width:680px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:none">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#1d4ed8 0%,#1e3a8a 100%);padding:28px 32px 22px;position:relative;overflow:hidden">
            <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.06)"></div>
            <div style="position:absolute;bottom:-50px;left:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04)"></div>
            <div style="position:relative;z-index:1;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:1px;text-shadow:0 1px 4px rgba(0,0,0,0.3)">${esc(res.shop_name)}</div>
                ${res.shop_address ? `<div style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:4px">${esc(res.shop_address)}</div>` : ''}
                ${res.shop_phone   ? `<div style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:2px">&#9990; ${esc(res.shop_phone)}</div>` : ''}
            </div>
        </div>

        <!-- Label strip -->
        <div style="display:flex;align-items:center;background:#f8f8f8;border-top:2px dashed #ddd;border-bottom:2px dashed #ddd;padding:0 12px">
            <div style="width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 0 0 2px #ddd;flex-shrink:0;margin-left:-22px"></div>
            <div style="flex:1;text-align:center;padding:6px 0;font-size:11px;font-weight:700;letter-spacing:3px;color:#aaa;text-transform:uppercase">কোটেশন / এস্টিমেট</div>
            <div style="width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 0 0 2px #ddd;flex-shrink:0;margin-right:-22px"></div>
        </div>

        <!-- Meta -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:20px 32px 12px;gap:16px">
            <div style="flex:1">
                <div style="font-size:11px;font-weight:700;color:#aaa;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">কোটেশন তথ্য</div>
                <table style="border-collapse:collapse;font-size:13px">
                    <tr><td style="color:#888;padding:2px 12px 2px 0;white-space:nowrap">কোটেশন নং</td>
                        <td style="font-weight:700;color:#1d4ed8">${esc(q.quote_number)}</td></tr>
                    <tr><td style="color:#888;padding:2px 12px 2px 0">তারিখ</td>
                        <td style="color:#333">${esc(q.quote_date)}</td></tr>
                    <tr><td style="color:#888;padding:2px 12px 2px 0">মেয়াদ</td>
                        <td style="color:#333">${q.valid_days} দিন</td></tr>
                    <tr><td style="color:#888;padding:2px 12px 2px 0">স্ট্যাটাস</td>
                        <td><span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:${sc}20;color:${sc};border:1px solid ${sc}60">${statusLabel[q.status]||''}</span></td></tr>
                </table>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-size:11px;font-weight:700;color:#aaa;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">কাস্টমার</div>
                <div style="font-weight:700;font-size:16px;color:#222">${esc(q.customer_name)}</div>
            </div>
        </div>

        <!-- Items -->
        <div style="padding:0 32px 8px">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:linear-gradient(90deg,#1d4ed8,#2563eb)">
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
                    <td style="padding:5px 0;text-align:right;color:#333">${fmt(q.subtotal)}</td>
                </tr>
                ${disc>0?`<tr>
                    <td style="padding:5px 16px 5px 0;color:#e74c3c">ছাড়</td>
                    <td style="padding:5px 0;text-align:right;color:#e74c3c">− ${fmt(disc)}</td>
                </tr>`:''}
                <tr style="border-top:2px solid #eee">
                    <td colspan="2" style="padding:4px 0">
                        <div style="background:linear-gradient(90deg,#1d4ed8,#2563eb);border-radius:8px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;margin-top:4px">
                            <span style="color:#fff;font-weight:700;font-size:14px">মোট</span>
                            <span style="color:#fff;font-weight:900;font-size:20px">${fmt(q.total_amount)}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        ${q.note ? `
        <div style="margin:0 32px 16px;padding:10px 14px;background:#fffbf0;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;font-size:13px;color:#78350f">
            <strong>নোট:</strong> ${esc(q.note)}
        </div>` : ''}

        <!-- Footer -->
        <div style="background:#1a1a1a;padding:14px 32px;text-align:center">
            <div style="color:#888;font-size:12px;letter-spacing:1px">এই কোটেশনটি ${q.valid_days} দিনের জন্য প্রযোজ্য</div>
            ${res.shop_phone ? `<div style="color:#aaa;font-size:12px;margin-top:3px">&#9990; ${esc(res.shop_phone)}</div>` : ''}
        </div>

    </div>`;
}

function printQuote() {
    if (!_lastQuoteRes) return;
    const q   = _lastQuoteRes.data;
    const win = window.open('', '_blank', 'width=780,height=900');
    win.document.write(`<!DOCTYPE html>
    <html><head>
    <meta charset="UTF-8">
    <title>কোটেশন — ${esc(q.quote_number)}</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { background:#f0f0f0; padding:24px; font-family:'Hind Siliguri','Segoe UI',sans-serif; }
        @media print {
            body { background:#fff; padding:0; }
        }
    </style>
    </head><body>
    ${buildQuoteHTML(_lastQuoteRes)}
    <script>window.onload = function(){ window.print(); window.close(); };<\/script>
    </body></html>`);
    win.document.close();
}

// ── Edit quotation ────────────────────────────────────────────────────────────
function openEditQuote() {
    if (!_lastQuoteRes) return;
    const q = _lastQuoteRes.data;
    document.getElementById('eqId').value          = q.id;
    document.getElementById('eqCustomer').value    = q.customer_name;
    document.getElementById('eqDate').value        = q.quote_date;
    document.getElementById('eqValidDays').value   = q.valid_days;
    document.getElementById('eqDiscount').value    = parseFloat(q.discount)||0;
    document.getElementById('eqNote').value        = q.note||'';

    const tbody = document.getElementById('editQuoteItemsBody');
    tbody.innerHTML = '';
    editQuoteRowCount = 0;
    (q.items||[]).forEach(it => addEditQuoteRow(it));
    calcEditTotal();
    viewModal.hide();
    editQuoteModal.show();
}

function addEditQuoteRow(prefill = null) {
    editQuoteRowCount++;
    const n    = editQuoteRowCount;
    const opts = PRODUCTS.map(p =>
        `<option value="${p.id}" data-price="${p.sell_price}" data-name="${esc(p.name)}"
            ${prefill && p.id == prefill.product_id ? 'selected' : ''}>
            ${esc(p.name)}</option>`
    ).join('');
    const tr = document.createElement('tr');
    tr.id = 'eqrow' + n;
    tr.innerHTML = `
        <td><select class="form-select form-select-sm" onchange="onEditQuoteProductChange(this,${n})">
            <option value="">-- পণ্য --</option>${opts}</select></td>
        <td><input type="number" class="form-control form-control-sm" id="eqqty${n}"
                   value="${prefill ? prefill.quantity : 1}" min="0.01" step="0.01"
                   oninput="calcEditRow(${n});calcEditTotal()"></td>
        <td><input type="number" class="form-control form-control-sm" id="eqprice${n}"
                   value="${prefill ? prefill.unit_price : 0}" min="0" step="0.01"
                   oninput="calcEditRow(${n});calcEditTotal()"></td>
        <td class="align-middle fw-semibold" id="eqrowtotal${n}">০.০০ ৳</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="document.getElementById('eqrow${n}').remove();calcEditTotal()">
                <i class="bi bi-x"></i></button></td>`;
    document.getElementById('editQuoteItemsBody').appendChild(tr);
    calcEditRow(n);
}
function onEditQuoteProductChange(sel, n) {
    const opt = sel.selectedOptions[0];
    if (opt?.dataset.price) document.getElementById('eqprice'+n).value = opt.dataset.price;
    calcEditRow(n); calcEditTotal();
}
function calcEditRow(n) {
    const q  = parseFloat(document.getElementById('eqqty'+n)?.value||0);
    const p  = parseFloat(document.getElementById('eqprice'+n)?.value||0);
    const el = document.getElementById('eqrowtotal'+n);
    if (el) el.textContent = (q*p).toFixed(2)+' ৳';
}
function calcEditTotal() {
    let sub = 0;
    document.querySelectorAll('#editQuoteItemsBody tr').forEach(tr => {
        const n = tr.id.replace('eqrow','');
        sub += parseFloat(document.getElementById('eqqty'+n)?.value||0)
             * parseFloat(document.getElementById('eqprice'+n)?.value||0);
    });
    const disc = parseFloat(document.getElementById('eqDiscount')?.value||0);
    document.getElementById('eqSubtotal').textContent = sub.toFixed(2)+' ৳';
    document.getElementById('eqTotal').textContent    = Math.max(0,sub-disc).toFixed(2)+' ৳';
}
function submitEditQuote(e) {
    e.preventDefault();
    const id    = document.getElementById('eqId').value;
    const items = [];
    document.querySelectorAll('#editQuoteItemsBody tr').forEach(tr => {
        const n   = tr.id.replace('eqrow','');
        const sel = tr.querySelector('select');
        const pid = parseInt(sel?.value||0);
        if (!pid) return;
        const opt   = sel.selectedOptions[0];
        const qty   = parseFloat(document.getElementById('eqqty'+n)?.value||0);
        const price = parseFloat(document.getElementById('eqprice'+n)?.value||0);
        if (qty > 0 && price > 0)
            items.push({product_id:pid, product_name:opt?.dataset.name||'', quantity:qty, unit_price:price});
    });
    const data = {
        id,
        customer_name: document.getElementById('eqCustomer').value.trim(),
        quote_date:    document.getElementById('eqDate').value,
        valid_days:    document.getElementById('eqValidDays').value,
        discount:      document.getElementById('eqDiscount').value,
        note:          document.getElementById('eqNote').value.trim(),
        items:         JSON.stringify(items),
    };
    const btn = document.getElementById('editQuoteSaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/update_quotation.php',{method:'POST',body:new URLSearchParams(data)})
        .then(r=>r.json()).then(res=>{
            btn.disabled = false;
            if (res.success) {
                editQuoteModal.hide();
                showToast(res.message,'success');
                loadList();
            } else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

function changeStatus(id, status) {
    if (!confirm(status==='cancelled' ? 'কোটেশন বাতিল করবেন?' : 'বিক্রয়ে রূপান্তর চিহ্নিত করবেন?')) return;
    fetch(BASE_URL+'/api/update_quotation_status.php',{method:'POST',body:new URLSearchParams({id,status})})
        .then(r=>r.json()).then(res=>{
            showToast(res.message, res.success?'success':'danger');
            if (res.success) { viewModal.hide(); loadList(); }
        });
}

// ── New quotation form ────────────────────────────────────────────────────────
let rowCount = 0;
function addQuoteRow() {
    rowCount++;
    const opts = PRODUCTS.map(p=>`<option value="${p.id}" data-price="${p.sell_price}" data-name="${esc(p.name)}">${esc(p.name)} (${esc(p.unit)})</option>`).join('');
    const tr = document.createElement('tr');
    tr.id = 'qrow'+rowCount;
    tr.innerHTML = `
        <td><select class="form-select form-select-sm" onchange="onProductChange(this,${rowCount})"><option value="">-- পণ্য --</option>${opts}</select></td>
        <td><input type="number" class="form-control form-control-sm" id="qty${rowCount}" value="1" min="0.01" step="0.01" oninput="calcRow(${rowCount});calcTotal()"></td>
        <td><input type="number" class="form-control form-control-sm" id="price${rowCount}" value="0" min="0" step="0.01" oninput="calcRow(${rowCount});calcTotal()"></td>
        <td class="align-middle fw-semibold" id="rowtotal${rowCount}">০.০০ ৳</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('qrow${rowCount}').remove();calcTotal()"><i class="bi bi-x"></i></button></td>`;
    document.getElementById('quoteItemsBody').appendChild(tr);
}
function onProductChange(sel, idx) {
    const opt = sel.selectedOptions[0];
    if (opt) { document.getElementById('price'+idx).value = opt.dataset.price||0; }
    calcRow(idx); calcTotal();
}
function calcRow(idx) {
    const q = parseFloat(document.getElementById('qty'+idx)?.value||0);
    const p = parseFloat(document.getElementById('price'+idx)?.value||0);
    const el = document.getElementById('rowtotal'+idx);
    if (el) el.textContent = (q*p).toFixed(2)+' ৳';
}
function calcTotal() {
    let sub = 0;
    document.querySelectorAll('#quoteItemsBody tr').forEach(tr => {
        const id = tr.id.replace('qrow','');
        const q  = parseFloat(document.getElementById('qty'+id)?.value||0);
        const p  = parseFloat(document.getElementById('price'+id)?.value||0);
        sub += q*p;
    });
    const disc = parseFloat(document.getElementById('qDiscount').value||0);
    document.getElementById('qSubtotal').textContent = sub.toFixed(2)+' ৳';
    document.getElementById('qTotal').textContent    = Math.max(0,sub-disc).toFixed(2)+' ৳';
}
function submitQuote(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#quoteItemsBody tr').forEach(tr => {
        const id  = tr.id.replace('qrow','');
        const sel = tr.querySelector('select');
        const pid = parseInt(sel?.value||0);
        if (!pid) return;
        const opt  = sel.selectedOptions[0];
        const qty  = parseFloat(document.getElementById('qty'+id)?.value||0);
        const price= parseFloat(document.getElementById('price'+id)?.value||0);
        if (qty > 0 && price > 0) items.push({product_id:pid,product_name:opt?.dataset.name||'',quantity:qty,unit_price:price});
    });
    const data = {
        customer_name: document.getElementById('qCustomer').value.trim(),
        quote_date:    document.getElementById('qDate').value,
        valid_days:    document.getElementById('qValidDays').value,
        discount:      document.getElementById('qDiscount').value,
        note:          document.getElementById('qNote').value.trim(),
        items:         JSON.stringify(items)
    };
    const btn = document.getElementById('quoteSaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/add_quotation.php',{method:'POST',body:new URLSearchParams(data)})
        .then(r=>r.json()).then(res=>{
            btn.disabled = false;
            if (res.success) { quoteModal.hide(); showToast(res.message,'success'); loadList(); document.getElementById('quoteForm').reset(); document.getElementById('quoteItemsBody').innerHTML=''; rowCount=0; calcTotal(); }
            else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

// ── Events ────────────────────────────────────────────────────────────────────
document.getElementById('btnNewQuote')?.addEventListener('click', () => {
    document.getElementById('quoteForm').reset();
    document.getElementById('quoteItemsBody').innerHTML = '';
    rowCount = 0; calcTotal();
    quoteModal.show();
    setTimeout(() => addQuoteRow(), 100);
});

document.querySelectorAll('#statusFilter button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#statusFilter button').forEach(b => {
            b.className = 'btn btn-outline-' + (b.dataset.status===''?'danger':b.dataset.status==='active'?'primary':b.dataset.status==='converted'?'success':'secondary');
        });
        this.classList.remove('btn-outline-danger','btn-outline-primary','btn-outline-success','btn-outline-secondary');
        this.classList.add('active','btn-'+(this.dataset.status===''?'danger':this.dataset.status==='active'?'primary':this.dataset.status==='converted'?'success':'secondary'));
        activeStatus = this.dataset.status;
        loadList();
    });
});

document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 350);
});

loadList();
