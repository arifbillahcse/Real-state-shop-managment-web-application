/* global BASE_URL, CAN_WRITE */
let activeStatus='', searchTimer=null, currentPlanId=null;
const planModal   = new bootstrap.Modal(document.getElementById('planModal'));
const detailModal = new bootstrap.Modal(document.getElementById('planDetailModal'));
const payModal    = new bootstrap.Modal(document.getElementById('payModal'));

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(v) { return parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' ৳'; }

// ── Load plans ────────────────────────────────────────────────────────────────
function loadPlans() {
    const search = document.getElementById('searchInput').value.trim();
    const p = new URLSearchParams();
    if (activeStatus) p.set('status', activeStatus);
    if (search)       p.set('search', search);
    const el = document.getElementById('planList');
    el.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    fetch(BASE_URL+'/api/get_installment_plans.php?'+p).then(r=>r.json()).then(res=>{
        if (!res.success) { el.innerHTML='<div class="alert alert-danger">লোড ব্যর্থ।</div>'; return; }
        renderPlans(res.data);
    }).catch(()=>{ el.innerHTML='<div class="alert alert-danger">সমস্যা হয়েছে।</div>'; });
}

function renderPlans(plans) {
    const el = document.getElementById('planList');
    if (!plans.length) {
        el.innerHTML='<div class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-1 d-block opacity-25 mb-2"></i>কোনো কিস্তি পরিকল্পনা নেই</div>';
        return;
    }
    const badge = { active:'<span class="badge bg-primary">সক্রিয়</span>',
                    completed:'<span class="badge bg-success">সম্পন্ন</span>',
                    cancelled:'<span class="badge bg-secondary">বাতিল</span>' };
    el.innerHTML = `<div class="table-responsive"><table class="table table-hover shadow-sm">
        <thead class="table-dark"><tr>
            <th>কাস্টমার</th><th>শুরু</th><th class="text-end">মোট</th>
            <th class="text-end">পরিশোধ</th><th>অগ্রগতি</th><th>স্ট্যাটাস</th><th></th>
        </tr></thead><tbody>` +
    plans.map(p => {
        const pct = p.total_inst > 0 ? Math.round((parseInt(p.paid_inst)/parseInt(p.total_inst))*100) : 0;
        return `<tr>
            <td class="fw-semibold">${esc(p.customer_name)}</td>
            <td>${esc(p.start_date)}</td>
            <td class="text-end">${fmt(p.total_amount)}</td>
            <td class="text-end text-success">${fmt(p.total_paid)}</td>
            <td style="min-width:120px">
                <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px">
                        <div class="progress-bar bg-success" style="width:${pct}%"></div>
                    </div>
                    <small class="text-muted">${p.paid_inst}/${p.total_inst}</small>
                </div>
            </td>
            <td>${badge[p.status]||''}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewPlan(${p.id})">
                <i class="bi bi-eye"></i></button></td>
        </tr>`;
    }).join('') + '</tbody></table></div>';
}

// ── View plan detail ──────────────────────────────────────────────────────────
function viewPlan(id) {
    currentPlanId = id;
    const body = document.getElementById('planDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    detailModal.show();
    fetch(BASE_URL+'/api/get_installment_plan.php?id='+id).then(r=>r.json()).then(res=>{
        if (!res.success) { body.innerHTML='<div class="alert alert-danger">'+esc(res.message)+'</div>'; return; }
        renderPlanDetail(res.data);
    });
}

function renderPlanDetail(p) {
    const body = document.getElementById('planDetailBody');
    const statusBadge = { pending:'<span class="badge bg-warning text-dark">পেন্ডিং</span>',
                          paid:'<span class="badge bg-success">পরিশোধ</span>',
                          overdue:'<span class="badge bg-danger">মেয়াদোত্তীর্ণ</span>' };
    const rows = (p.installments||[]).map(inst => `
    <tr class="${inst.status==='overdue'?'table-danger':inst.status==='paid'?'table-success':''}">
        <td>${inst.installment_no}</td>
        <td>${esc(inst.due_date)}</td>
        <td class="text-end">${fmt(inst.amount)}</td>
        <td class="text-end">${inst.status==='paid' ? fmt(inst.paid_amount) : '—'}</td>
        <td>${inst.paid_date||'—'}</td>
        <td>${statusBadge[inst.status]||''}</td>
        <td>${(CAN_WRITE && inst.status!=='paid') ? `<button class="btn btn-sm btn-success" onclick="openPay(${inst.id},${inst.installment_no},${inst.amount})"><i class="bi bi-cash-coin"></i></button>` : ''}</td>
    </tr>`).join('');

    body.innerHTML = `
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3 text-center">
            <div class="text-muted small">কাস্টমার</div><div class="fw-bold">${esc(p.customer_name)}</div>
        </div>
        <div class="col-6 col-md-3 text-center">
            <div class="text-muted small">মোট পরিমাণ</div><div class="fw-bold">${fmt(p.total_amount)}</div>
        </div>
        <div class="col-6 col-md-3 text-center">
            <div class="text-muted small">অগ্রিম</div><div class="fw-bold text-success">${fmt(p.down_payment)}</div>
        </div>
        <div class="col-6 col-md-3 text-center">
            <div class="text-muted small">প্রতি কিস্তি</div><div class="fw-bold">${fmt(p.installment_amount)}</div>
        </div>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="table-dark"><tr>
            <th>#</th><th>নির্ধারিত তারিখ</th><th class="text-end">পরিমাণ</th>
            <th class="text-end">পরিশোধ</th><th>পরিশোধের তারিখ</th><th>স্ট্যাটাস</th><th></th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
}

// ── Pay installment ───────────────────────────────────────────────────────────
function openPay(id, no, amount) {
    document.getElementById('payInstId').value  = id;
    document.getElementById('payInstNo').textContent  = no;
    document.getElementById('payInstAmt').textContent = fmt(amount);
    document.getElementById('payAmount').value  = amount;
    document.getElementById('payNote').value    = '';
    payModal.show();
}
function submitPay(e) {
    e.preventDefault();
    const id     = document.getElementById('payInstId').value;
    const amount = document.getElementById('payAmount').value;
    const note   = document.getElementById('payNote').value.trim();
    const btn    = document.getElementById('paySaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/pay_installment.php',{method:'POST',body:new URLSearchParams({id,amount,note})})
        .then(r=>r.json()).then(res=>{
            btn.disabled=false;
            if (res.success) { payModal.hide(); showToast(res.message,'success'); viewPlan(currentPlanId); loadPlans(); }
            else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

// ── New plan ──────────────────────────────────────────────────────────────────
function calcInstall() {
    const total = parseFloat(document.getElementById('pTotal')?.value||0);
    const down  = parseFloat(document.getElementById('pDown')?.value||0);
    const count = parseInt(document.getElementById('pCount')?.value||0);
    const prev  = document.getElementById('installPreview');
    if (total > 0 && count > 0 && down < total) {
        const amt = ((total - down) / count).toFixed(2);
        prev.classList.remove('d-none');
        prev.innerHTML = `প্রতি মাসে: <strong>${parseFloat(amt).toLocaleString('en-US',{minimumFractionDigits:2})} ৳</strong> &times; ${count} কিস্তি`;
    } else { prev.classList.add('d-none'); }
}
function submitPlan(e) {
    e.preventDefault();
    const data = {
        customer_name:      document.getElementById('pName').value.trim(),
        total_amount:       document.getElementById('pTotal').value,
        down_payment:       document.getElementById('pDown').value,
        installment_count:  document.getElementById('pCount').value,
        start_date:         document.getElementById('pStart').value,
        note:               document.getElementById('pNote').value.trim(),
    };
    const btn = document.getElementById('planSaveBtn');
    btn.disabled = true;
    fetch(BASE_URL+'/api/add_installment_plan.php',{method:'POST',body:new URLSearchParams(data)})
        .then(r=>r.json()).then(res=>{
            btn.disabled=false;
            if (res.success) { planModal.hide(); showToast(res.message,'success'); loadPlans(); document.getElementById('planForm').reset(); document.getElementById('installPreview').classList.add('d-none'); }
            else showToast(res.message,'danger');
        }).catch(()=>{ btn.disabled=false; showToast('সমস্যা হয়েছে।','danger'); });
}

// ── Events ────────────────────────────────────────────────────────────────────
document.getElementById('btnNewPlan')?.addEventListener('click', () => planModal.show());
document.querySelectorAll('#statusFilter button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#statusFilter button').forEach(b=>{
            b.className='btn btn-outline-'+(b.dataset.status===''?'danger':b.dataset.status==='active'?'primary':b.dataset.status==='completed'?'success':'secondary');
        });
        this.classList.remove('btn-outline-danger','btn-outline-primary','btn-outline-success','btn-outline-secondary');
        this.classList.add('active','btn-'+(this.dataset.status===''?'danger':this.dataset.status==='active'?'primary':this.dataset.status==='completed'?'success':'secondary'));
        activeStatus = this.dataset.status; loadPlans();
    });
});
document.getElementById('searchInput').addEventListener('input',function(){ clearTimeout(searchTimer); searchTimer=setTimeout(loadPlans,350); });

loadPlans();
