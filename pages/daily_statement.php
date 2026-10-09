<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireBranchStaffOrAbove();

$pageTitle = 'ডেইলি স্টেটমেন্ট';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-journal-check me-2"></i>ডেইলি স্টেটমেন্ট (দৈনিক প্রতিবেদন)</h4>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>প্রিন্ট
    </button>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-8 col-md-3">
          <label class="form-label small text-muted mb-1">তারিখ</label>
          <input type="date" class="form-control form-control-sm" id="stDate" value="<?= today() ?>">
        </div>
        <div class="col-4 col-md-2 d-grid">
          <button class="btn btn-primary btn-sm" onclick="loadStatement()">
            <i class="bi bi-search me-1"></i>দেখুন
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="stLoading" class="text-center py-5 d-none">
    <div class="spinner-border text-primary"></div>
  </div>

  <div id="stContent" class="d-none">

    <!-- Summary -->
    <div class="alert alert-dark" id="stSummary"></div>

    <!-- 8.1 Full account -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-person-vcard me-1 text-danger"></i>৮.১ ফুল একাউন্ট কাস্টমার ডেলিভারি রিপোর্ট
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>কাস্টমারের নাম / ঠিকানা</th>
              <th>পণ্য তালিকা</th>
              <th class="text-end">মূল্য</th>
              <th class="text-end">জমা</th>
              <th class="text-end">পূর্বের বাকি</th>
              <th class="text-end">বর্তমান ব্যালেন্স</th>
              <th>হিসাব ট্রান্সফার</th>
              <th class="text-end">অন্যান্য খরচ</th>
              <th class="text-end">ফেরত টাকা</th>
            </tr>
          </thead>
          <tbody id="stFullBody"></tbody>
        </table>
      </div>
    </div>

    <!-- 8.2 Short account -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-person me-1 text-warning"></i>৮.২ শর্ট একাউন্ট লেনদেন
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>কাস্টমারের নাম / ঠিকানা</th>
              <th>পণ্য তালিকা</th>
              <th class="text-end">মূল্য</th>
              <th class="text-end">জমা</th>
              <th class="text-end">পূর্বের বাকি</th>
              <th class="text-end">বর্তমান ব্যালেন্স</th>
              <th>হিসাব ট্রান্সফার</th>
              <th class="text-end">অন্যান্য খরচ</th>
              <th class="text-end">ফেরত টাকা</th>
            </tr>
          </thead>
          <tbody id="stShortBody"></tbody>
        </table>
      </div>
    </div>

    <!-- 8.3 Cash sales -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-cash me-1 text-success"></i>৮.৩ নগদ বিক্রয়ের রিপোর্ট
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ইনভয়েস</th>
              <th>কাস্টমার</th>
              <th>পণ্য</th>
              <th class="text-end">মোট</th>
              <th class="text-end">পরিশোধ</th>
              <th class="text-end">বাকি</th>
            </tr>
          </thead>
          <tbody id="stCashBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Delivered product stock -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-boxes me-1 text-info"></i>ডেলিভারি পণ্যর স্টক
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ক্রমিক নং</th>
              <th>পণ্যর নাম</th>
              <th class="text-end">আজ বিক্রি</th>
              <th class="text-end">বর্তমান স্টক</th>
              <th class="text-end">দাম (আনুমানিক)</th>
            </tr>
          </thead>
          <tbody id="stStockBody"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</div>

<!-- হিসাব ট্রান্সফার modal -->
<div class="modal fade" id="dueTransferModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-arrow-left-right me-1"></i>হিসাব ট্রান্সফার</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="dtCustomerId">
        <p class="mb-2">কাস্টমার: <strong id="dtCustomerName"></strong></p>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">ব্রাঞ্চ</label>
          <select class="form-select form-select-sm" id="dtBranch">
            <option value="">— ব্রাঞ্চ নির্বাচন করুন —</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">স্টাফ</label>
          <select class="form-select form-select-sm" id="dtStaff" disabled>
            <option value="">— আগে ব্রাঞ্চ নির্বাচন করুন —</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">নোট (ঐচ্ছিক)</label>
          <input type="text" class="form-control form-control-sm" id="dtNote">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-sm btn-primary" id="dtSaveBtn" onclick="saveDueTransfer()">
          <i class="bi bi-check-lg me-1"></i>ট্রান্সফার করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = <?= isAdminOrManager() ? 'true' : 'false' ?>;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

function accountRow(r) {
    return `<tr>
        <td><strong>${esc(r.name)}</strong>${r.account_no ? ` <span class="badge bg-dark">${esc(r.account_no)}</span>` : ''}
            <div class="small text-muted">${esc(r.phone || '')}${r.address ? ' · ' + esc(r.address) : ''}</div></td>
        <td class="small">${(r.items || []).map(esc).join(', ') || '—'}</td>
        <td class="text-end">${parseFloat(r.delivery_value) > 0 ? fmt(r.delivery_value) : '—'}</td>
        <td class="text-end text-success">${parseFloat(r.deposit) > 0 ? fmt(r.deposit) : '—'}</td>
        <td class="text-end">${fmt(r.previous_due)}</td>
        <td class="text-end fw-semibold ${parseFloat(r.current_balance) > 0 ? 'text-danger' : 'text-success'}">${fmt(r.current_balance)}</td>
        <td class="small">
            ${r.collector_id
                ? `<span class="badge bg-info-subtle text-info-emphasis">${esc(r.collector_code)} — ${esc(r.collector)}</span>`
                : `<span class="text-muted">Not Assigned</span>`}
            ${IS_ADMIN ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1"
                onclick='openDueTransfer(${r.id}, ${JSON.stringify(r.name)})' title="হিসাব ট্রান্সফার">
                <i class="bi bi-arrow-left-right"></i></button>` : ''}
        </td>
        <td class="text-end">${parseFloat(r.other_expense) > 0 ? fmt(r.other_expense) : '—'}</td>
        <td class="text-end">${parseFloat(r.money_returned) > 0 ? fmt(r.money_returned) : '—'}</td>
    </tr>`;
}

async function loadStatement() {
    const date = document.getElementById('stDate').value;
    document.getElementById('stLoading').classList.remove('d-none');
    document.getElementById('stContent').classList.add('d-none');
    try {
        const res  = await fetch(`${BASE_URL}/api/get_daily_statement.php?date=${date}`);
        const raw  = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            // The server didn't return JSON — surface the real reason instead
            // of a generic message, so the actual server-side error is visible.
            console.error('get_daily_statement.php non-JSON response:', raw);
            const snippet = raw.trim().slice(0, 200) || '(খালি রেসপন্স)';
            showToast(`স্টেটমেন্ট লোড করা যায়নি (HTTP ${res.status}): ${snippet}`, 'danger');
            return;
        }
        if (!data.success) { showToast(data.message, 'danger'); return; }
        // jsonResponse() merges the payload into the top level, so the
        // statement is data.statement — not data.data.statement.
        const st = data.statement;

        const emptyRow = cols =>
            `<tr><td colspan="${cols}" class="text-center text-muted py-3">এই তারিখে কোনো কার্যক্রম নেই</td></tr>`;

        document.getElementById('stFullBody').innerHTML =
            st.full_account.length ? st.full_account.map(accountRow).join('') : emptyRow(9);
        document.getElementById('stShortBody').innerHTML =
            st.short_account.length ? st.short_account.map(accountRow).join('') : emptyRow(9);

        document.getElementById('stCashBody').innerHTML = st.cash_sales.length
            ? st.cash_sales.map(s => `
                <tr>
                    <td class="fw-semibold">${esc(s.invoice_number)}</td>
                    <td>${esc(s.customer_name)}</td>
                    <td class="small">${esc(s.items || '—')}</td>
                    <td class="text-end">${fmt(s.total_amount)}</td>
                    <td class="text-end text-success">${fmt(s.paid_amount)}</td>
                    <td class="text-end ${parseFloat(s.due_amount) > 0 ? 'text-danger fw-semibold' : ''}">${fmt(s.due_amount)}</td>
                </tr>`).join('')
            : emptyRow(6);

        document.getElementById('stStockBody').innerHTML = st.product_stock.length
            ? st.product_stock.map((p, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td class="fw-semibold">${esc(p.product_name)}</td>
                    <td class="text-end">${parseFloat(p.today_sold)} ${esc(p.unit)}</td>
                    <td class="text-end">${parseFloat(p.current_stock)} ${esc(p.unit)}</td>
                    <td class="text-end">${fmt(parseFloat(p.today_sold) * parseFloat(p.sell_price))}</td>
                </tr>`).join('')
            : emptyRow(5);

        const sm = st.summary;
        document.getElementById('stSummary').innerHTML =
            `<strong>সারাংশ (${st.date}):</strong>
             আজ মোট পণ্য বিক্রি <strong>${sm.product_kinds}</strong> ধরনের ।
             মোট পণ্যর দাম <strong>${fmt(sm.total_value)}</strong> ।
             নগদ আদায় <strong class="text-success">${fmt(sm.cash_received)}</strong> ।
             বাকি <strong class="text-danger">${fmt(sm.due)}</strong> ।`;

        document.getElementById('stContent').classList.remove('d-none');
    } catch (err) {
        // Never swallow the reason — a bare message here once hid a plain
        // typo in this function for far too long.
        console.error('loadStatement failed:', err);
        showToast('স্টেটমেন্ট লোড করা যায়নি: ' + (err && err.message ? err.message : err), 'danger');
    } finally {
        document.getElementById('stLoading').classList.add('d-none');
    }
}

// ── হিসাব ট্রান্সফার ─────────────────────────────────────────────────────
let dueTransferModal = null;
async function loadBranchesIntoTransferModal() {
    const sel = document.getElementById('dtBranch');
    if (sel.dataset.loaded) return;
    try {
        const res  = await fetch(`${BASE_URL}/api/get_branches.php`);
        const data = await res.json();
        (data.data || []).forEach(b => {
            sel.insertAdjacentHTML('beforeend', `<option value="${b.id}">${esc(b.name)}</option>`);
        });
        sel.dataset.loaded = '1';
    } catch (err) {
        console.error('branch list load failed:', err);
    }
}

async function openDueTransfer(customerId, customerName) {
    if (!IS_ADMIN) return;
    document.getElementById('dtCustomerId').value   = customerId;
    document.getElementById('dtCustomerName').textContent = customerName;
    document.getElementById('dtNote').value = '';
    const staffSel = document.getElementById('dtStaff');
    staffSel.innerHTML = '<option value="">— আগে ব্রাঞ্চ নির্বাচন করুন —</option>';
    staffSel.disabled = true;
    await loadBranchesIntoTransferModal();
    document.getElementById('dtBranch').value = '';
    if (!dueTransferModal) {
        dueTransferModal = new bootstrap.Modal(document.getElementById('dueTransferModal'));
    }
    dueTransferModal.show();
}

document.getElementById('dtBranch').addEventListener('change', async function () {
    const staffSel = document.getElementById('dtStaff');
    const branchId = this.value;
    staffSel.innerHTML = '<option value="">লোড হচ্ছে...</option>';
    staffSel.disabled = true;
    if (!branchId) {
        staffSel.innerHTML = '<option value="">— আগে ব্রাঞ্চ নির্বাচন করুন —</option>';
        return;
    }
    try {
        const res  = await fetch(`${BASE_URL}/api/get_branch_staff.php?branch_id=${branchId}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message, 'danger'); return; }
        const staff = data.staff || [];
        staffSel.innerHTML = staff.length
            ? '<option value="">— স্টাফ নির্বাচন করুন —</option>' + staff.map(s =>
                `<option value="${s.id}">STF-${String(s.id).padStart(4, '0')} — ${esc(s.name)}</option>`
              ).join('')
            : '<option value="">এই ব্রাঞ্চে কোনো স্টাফ নেই</option>';
        staffSel.disabled = false;
    } catch (err) {
        console.error('branch staff load failed:', err);
        staffSel.innerHTML = '<option value="">লোড করা যায়নি</option>';
    }
});

async function saveDueTransfer() {
    const customerId = document.getElementById('dtCustomerId').value;
    const branchId    = document.getElementById('dtBranch').value;
    const staffId     = document.getElementById('dtStaff').value;
    const note        = document.getElementById('dtNote').value;
    if (!branchId) { showToast('ব্রাঞ্চ নির্বাচন করুন।', 'danger'); return; }
    if (!staffId)  { showToast('স্টাফ নির্বাচন করুন।', 'danger'); return; }

    const btn = document.getElementById('dtSaveBtn');
    btn.disabled = true;
    try {
        const res  = await fetch(`${BASE_URL}/api/assign_due.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ customer_id: customerId, staff_id: staffId, branch_id: branchId, note })
        });
        const data = await res.json();
        if (!data.success) { showToast(data.message, 'danger'); return; }
        showToast(data.message, 'success');
        dueTransferModal.hide();
        loadStatement();
    } catch (err) {
        console.error('assign_due failed:', err);
        showToast('ট্রান্সফার করা যায়নি।', 'danger');
    } finally {
        btn.disabled = false;
    }
}

loadStatement();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
