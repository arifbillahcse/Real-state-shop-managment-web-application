/* global BASE_URL, PRODUCTS, CAN_WRITE */
let activeStatus = '', searchTimer = null;
const quoteModal = new bootstrap.Modal(document.getElementById('quoteModal'));
const viewModal  = new bootstrap.Modal(document.getElementById('viewQuoteModal'));

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
        const q = res.data;
        body.innerHTML = `
        <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
            <div><h6 class="mb-0 fw-bold">${esc(q.quote_number)}</h6>
                 <span class="text-muted small">${esc(q.quote_date)} | মেয়াদ: ${q.valid_days} দিন</span></div>
            <div><strong>কাস্টমার:</strong> ${esc(q.customer_name)}</div>
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
        if (CAN_WRITE && q.status === 'active') {
            footer.innerHTML = `
            <button class="btn btn-secondary me-auto" onclick="changeStatus(${q.id},'cancelled')">
                <i class="bi bi-x-circle me-1"></i>বাতিল করুন
            </button>
            <button class="btn btn-success" onclick="changeStatus(${q.id},'converted')">
                <i class="bi bi-check-circle me-1"></i>বিক্রয়ে রূপান্তর করুন
            </button>`;
        }
    });
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
