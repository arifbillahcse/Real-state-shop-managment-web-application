/* global BASE_URL, CAN_WRITE */
let searchTimer = null, currentSale = null;
const returnModal = new bootstrap.Modal(document.getElementById('returnModal'));

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(v) { return parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' ৳'; }

// ── Return list ───────────────────────────────────────────────────────────────
function loadList() {
    const search = document.getElementById('searchInput').value.trim();
    const el = document.getElementById('returnList');
    el.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    const p = new URLSearchParams();
    if (search) p.set('search', search);
    fetch(BASE_URL+'/api/get_sale_returns.php?'+p).then(r=>r.json()).then(res=>{
        if (!res.success) { el.innerHTML='<div class="alert alert-danger">লোড ব্যর্থ।</div>'; return; }
        const rows = res.data;
        if (!rows.length) {
            el.innerHTML='<div class="text-center py-5 text-muted"><i class="bi bi-arrow-return-left fs-1 d-block opacity-25 mb-2"></i>কোনো ফেরত রেকর্ড নেই</div>';
            return;
        }
        el.innerHTML = `<div class="table-responsive"><table class="table table-hover shadow-sm">
            <thead class="table-dark"><tr><th>#</th><th>ইনভয়েস</th><th>কাস্টমার</th>
            <th>ফেরতের তারিখ</th><th>কারণ</th><th class="text-end">মোট ফেরত</th></tr></thead><tbody>`+
        rows.map(r=>`<tr>
            <td>${r.id}</td>
            <td><span class="badge bg-secondary">${esc(r.invoice_number)}</span></td>
            <td>${esc(r.customer_name)}</td>
            <td>${esc(r.return_date)}</td>
            <td class="text-muted small">${esc(r.reason)}</td>
            <td class="text-end fw-semibold text-warning">${fmt(r.total_refund)}</td>
        </tr>`).join('')+'</tbody></table></div>';
    }).catch(()=>{ el.innerHTML='<div class="alert alert-danger">সমস্যা হয়েছে।</div>'; });
}

// ── Search sale by invoice ────────────────────────────────────────────────────
function searchSale() {
    const inv = document.getElementById('invoiceSearch').value.trim();
    if (!inv) return;
    const el = document.getElementById('saleSearchResult');
    el.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div>';
    fetch(BASE_URL+'/api/get_sales.php?invoice='+encodeURIComponent(inv))
        .then(r=>r.json()).then(res=>{
            if (!res.success || !res.data?.length) {
                el.innerHTML='<div class="alert alert-warning py-2">বিক্রয় পাওয়া যায়নি।</div>'; return;
            }
            const sale = res.data[0];
            el.innerHTML = '';
            loadSaleForReturn(sale.id);
        }).catch(()=>{ el.innerHTML='<div class="alert alert-danger">সমস্যা হয়েছে।</div>'; });
}

function loadSaleForReturn(saleId) {
    fetch(BASE_URL+'/api/get_sale.php?id='+saleId).then(r=>r.json()).then(res=>{
        if (!res.success) { document.getElementById('saleSearchResult').innerHTML='<div class="alert alert-danger">'+esc(res.message)+'</div>'; return; }
        currentSale = res.data;
        showReturnForm(res.data);
    });
}

function showReturnForm(sale) {
    document.getElementById('step2').classList.remove('d-none');
    document.getElementById('rSaleId').value = sale.id;
    document.getElementById('returnSaveBtn').classList.remove('d-none');
    document.getElementById('saleInfo').innerHTML =
        `<strong>ইনভয়েস:</strong> ${esc(sale.invoice_number)} &nbsp;|&nbsp;
         <strong>কাস্টমার:</strong> ${esc(sale.customer_name)} &nbsp;|&nbsp;
         <strong>তারিখ:</strong> ${esc(sale.sale_date)}`;

    const tbody = document.getElementById('returnItemsBody');
    tbody.innerHTML = (sale.items||[]).map((it,i)=>`
    <tr>
        <td><input type="checkbox" class="ret-check" data-idx="${i}" onchange="updateRefund(${i})"></td>
        <td>${esc(it.product_name)}</td>
        <td>${it.quantity} ${esc(it.unit)}</td>
        <td><input type="number" class="form-control form-control-sm" id="retQty${i}"
                   value="${it.quantity}" min="0.01" max="${it.quantity}" step="0.01"
                   oninput="updateRefund(${i})" disabled></td>
        <td>${fmt(it.unit_price)}</td>
        <td class="fw-semibold" id="retRefund${i}">০.০০ ৳</td>
        <input type="hidden" id="retPid${i}" value="${it.product_id}">
        <input type="hidden" id="retPname${i}" value="${esc(it.product_name)}">
        <input type="hidden" id="retPrice${i}" value="${it.unit_price}">
    </tr>`).join('');
}

function toggleAll(chk) {
    document.querySelectorAll('.ret-check').forEach(c => {
        c.checked = chk.checked;
        const idx = c.dataset.idx;
        document.getElementById('retQty'+idx).disabled = !chk.checked;
        updateRefund(parseInt(idx));
    });
}
function updateRefund(idx) {
    const chk   = document.querySelector(`.ret-check[data-idx="${idx}"]`);
    const qtyEl = document.getElementById('retQty'+idx);
    qtyEl.disabled = !chk?.checked;
    const qty   = chk?.checked ? parseFloat(qtyEl?.value||0) : 0;
    const price = parseFloat(document.getElementById('retPrice'+idx)?.value||0);
    const el    = document.getElementById('retRefund'+idx);
    if (el) el.textContent = (qty*price).toFixed(2)+' ৳';
}

function submitReturn(e) {
    e.preventDefault();
    const saleId = document.getElementById('rSaleId').value;
    const reason = document.getElementById('rReason').value.trim();
    const note   = document.getElementById('rNote').value.trim();
    const items  = [];
    document.querySelectorAll('.ret-check:checked').forEach(c => {
        const i = c.dataset.idx;
        items.push({
            product_id:   document.getElementById('retPid'+i)?.value,
            product_name: document.getElementById('retPname'+i)?.value,
            quantity:     document.getElementById('retQty'+i)?.value,
            unit_price:   document.getElementById('retPrice'+i)?.value,
        });
    });
    if (!items.length) { showToast('কমপক্ষে একটি পণ্য নির্বাচন করুন।','warning'); return; }

    const btn = document.getElementById('returnSaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/add_sale_return.php',{method:'POST',body:new URLSearchParams({sale_id:saleId,reason,note,items:JSON.stringify(items)})})
        .then(r=>r.json()).then(res=>{
            btn.disabled=false;
            if (res.success) { returnModal.hide(); showToast(res.message,'success'); loadList(); resetReturnModal(); }
            else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

function resetReturnModal() {
    document.getElementById('invoiceSearch').value='';
    document.getElementById('saleSearchResult').innerHTML='';
    document.getElementById('step2').classList.add('d-none');
    document.getElementById('returnSaveBtn').classList.add('d-none');
    document.getElementById('returnItemsBody').innerHTML='';
    document.getElementById('rReason').value='';
    document.getElementById('rNote').value='';
    currentSale = null;
}

document.getElementById('btnNewReturn')?.addEventListener('click', () => { resetReturnModal(); returnModal.show(); });
document.getElementById('invoiceSearch')?.addEventListener('keydown', e => { if (e.key==='Enter') { e.preventDefault(); searchSale(); } });
document.getElementById('searchInput').addEventListener('input', function(){ clearTimeout(searchTimer); searchTimer=setTimeout(loadList,350); });

loadList();
