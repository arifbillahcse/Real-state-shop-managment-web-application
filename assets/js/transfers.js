// ============================================
// Product Transfer Workflow (§3–4)
// ============================================

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const STATUS_BADGE = {
    pending:  ['পেন্ডিং',  'warning text-dark'],
    sent:     ['পথে আছে', 'info text-dark'],
    received: ['সম্পন্ন',   'success'],
    returned: ['রিটার্ন',  'danger'],
};

function statusBadge(st) {
    const [label, cls] = STATUS_BADGE[st] || [st, 'secondary'];
    return `<span class="badge bg-${cls}">${label}</span>`;
}

// ── Entry form (add / edit) ──────────────────────────────────────────────────
let _editingEntry = null;

function submitTransferEntry(e) {
    e.preventDefault();
    const isEdit = !!document.getElementById('tEntryId').value;
    const data = {
        id:               document.getElementById('tEntryId').value,
        transfer_date:    document.getElementById('tDate').value,
        from_branch_id:   document.getElementById('tFromBranch').value,
        to_branch_id:     document.getElementById('tToBranch').value,
        customer_name:    document.getElementById('tCustomerName').value,
        customer_address: document.getElementById('tCustomerAddress').value,
        customer_mobile:  document.getElementById('tCustomerMobile').value,
        order_manager:    document.getElementById('tOrderManager').value,
        driver_name:      document.getElementById('tDriverName').value,
        driver_mobile:    document.getElementById('tDriverMobile').value,
        product_id:       document.getElementById('tProduct').value,
        quantity:         document.getElementById('tQuantity').value,
        note:             document.getElementById('tNote').value,
    };
    const url = isEdit
        ? `${BASE_URL}/api/transfer_entry_update.php`
        : `${BASE_URL}/api/transfer_entry_add.php`;
    const btn = document.getElementById('tSubmitBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            cancelEditEntry();
            // Keep customer/driver fields? Spec: repeat process per customer → clear all
            document.getElementById('transferForm').reset();
            tsSyncForm('transferForm');
            document.getElementById('tDate').value = TODAY;
            loadTodaySheet();
            loadDateList();
        }
    });
}

function editEntry(entry) {
    _editingEntry = entry;
    document.getElementById('tEntryId').value          = entry.id;
    document.getElementById('tDate').value             = entry.transfer_date;
    tsSet(document.getElementById('tFromBranch'), String(entry.from_branch_id), true);
    tsSet(document.getElementById('tToBranch'),   String(entry.to_branch_id),   true);
    document.getElementById('tCustomerName').value     = entry.customer_name || '';
    document.getElementById('tCustomerAddress').value  = entry.customer_address || '';
    document.getElementById('tCustomerMobile').value   = entry.customer_mobile || '';
    document.getElementById('tOrderManager').value     = entry.order_manager || '';
    document.getElementById('tDriverName').value       = entry.driver_name || '';
    document.getElementById('tDriverMobile').value     = entry.driver_mobile || '';
    tsSet(document.getElementById('tProduct'), String(entry.product_id), true);
    document.getElementById('tQuantity').value         = entry.quantity;
    document.getElementById('tNote').value             = entry.note || '';
    document.getElementById('tSubmitBtn').innerHTML    = '<i class="bi bi-check-lg me-1"></i>আপডেট করুন';
    document.getElementById('tCancelEditBtn').classList.remove('d-none');
    // Jump to the form tab
    document.querySelector('[data-bs-target="#newTransferTab"]')?.click();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEditEntry() {
    _editingEntry = null;
    document.getElementById('tEntryId').value = '';
    document.getElementById('tSubmitBtn').innerHTML =
        '<i class="bi bi-plus-circle me-1"></i>শিটে যুক্ত করুন (এন্টার)';
    document.getElementById('tCancelEditBtn').classList.add('d-none');
}

// ── Sheet rendering (shared by today-sheet and date sheets) ─────────────────
function actionButtons(t) {
    if (!CAN_WRITE) return '';
    let html = '';
    if (t.status === 'pending' || t.status === 'returned') {
        html += `<button class="btn btn-sm btn-success me-1" title="ট্রান্সফার (পাঠান)"
                    onclick="sendTransfer(${t.id})"><i class="bi bi-send"></i></button>`;
        html += `<button class="btn btn-sm btn-outline-warning me-1" title="এডিট"
                    onclick='editEntry(${JSON.stringify(t).replace(/'/g, "&#39;")})'>
                    <i class="bi bi-pencil"></i></button>`;
    }
    if (t.status === 'pending') {
        html += `<button class="btn btn-sm btn-outline-danger" title="ডিলিট"
                    onclick="deleteEntry(${t.id})"><i class="bi bi-trash"></i></button>`;
    }
    return html;
}

function sheetRow(t, showRoute) {
    const route = showRoute
        ? `<td class="small">${esc(t.from_branch_name)} → ${esc(t.to_branch_name)}</td>`
        : `<td class="small">${esc(t.to_branch_name)}</td>`;
    return `
    <tr>
        <td class="fw-semibold">${esc(t.customer_name)}
            ${t.customer_address ? `<div class="small text-muted">${esc(t.customer_address)}</div>` : ''}</td>
        <td>${esc(t.customer_mobile || '—')}</td>
        <td>${esc(t.product_name)}</td>
        <td class="text-end">${parseFloat(t.quantity)} ${esc(t.unit)}</td>
        ${route}
        <td class="small">${esc(t.driver_name || '—')}
            ${t.driver_mobile ? `<div class="text-muted">${esc(t.driver_mobile)}</div>` : ''}</td>
        <td class="text-center">${statusBadge(t.status)}
            ${t.return_note ? `<div class="small text-danger">${esc(t.return_note)}</div>` : ''}</td>
        <td class="text-center text-nowrap">${actionButtons(t)}</td>
    </tr>`;
}

function sendTransfer(id) {
    if (!confirm('পণ্যটি গন্তব্য ব্রাঞ্চে পাঠাবেন? পাঠানোর পর প্রেরক ব্রাঞ্চের স্টক থেকে বাদ যাবে।')) return;
    ajaxPost(`${BASE_URL}/api/transfer_send.php`, { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { loadTodaySheet(); loadDateList(); }
    });
}

function deleteEntry(id) {
    if (!confirm('এন্ট্রিটি ডিলিট করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/transfer_entry_delete.php`, { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { loadTodaySheet(); loadDateList(); }
    });
}

// ── Today's sheet ────────────────────────────────────────────────────────────
async function loadTodaySheet() {
    const tbody = document.getElementById('todaySheetBody');
    if (!tbody) return;
    try {
        const res  = await fetch(`${BASE_URL}/api/get_transfer_sheet.php?date=${TODAY}`);
        const data = await res.json();
        const entries = (data.data && data.data.entries) || [];
        tbody.innerHTML = entries.length
            ? entries.map(t => sheetRow(t, false)).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">আজ কোনো এন্ট্রি নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

// ── Date-wise list ───────────────────────────────────────────────────────────
async function loadDateList() {
    const wrap = document.getElementById('dateListWrap');
    if (!wrap) return;
    try {
        const res  = await fetch(`${BASE_URL}/api/get_transfer_dates.php`);
        const data = await res.json();
        const dates = (data.data && data.data.dates) || [];
        if (!dates.length) {
            wrap.innerHTML = '<p class="text-muted text-center mb-0">কোনো ট্রান্সফার নেই</p>';
            return;
        }
        wrap.innerHTML = dates.map(d => `
            <a href="#" class="d-flex justify-content-between align-items-center border rounded p-2 mb-1 text-decoration-none text-body"
               onclick="event.preventDefault();openSheet('${d.transfer_date}')">
                <span class="fw-semibold"><i class="bi bi-calendar-date me-2 text-danger"></i>${d.transfer_date}</span>
                <span class="d-flex gap-1 flex-wrap">
                    <span class="badge bg-secondary">মোট ${d.total}</span>
                    ${+d.pending_count  ? `<span class="badge bg-warning text-dark">পেন্ডিং ${d.pending_count}</span>` : ''}
                    ${+d.sent_count     ? `<span class="badge bg-info text-dark">পথে ${d.sent_count}</span>` : ''}
                    ${+d.received_count ? `<span class="badge bg-success">সম্পন্ন ${d.received_count}</span>` : ''}
                    ${+d.returned_count ? `<span class="badge bg-danger">রিটার্ন ${d.returned_count}</span>` : ''}
                </span>
            </a>`).join('');
    } catch {
        wrap.innerHTML = '<p class="text-danger text-center mb-0">লোড করা যায়নি</p>';
    }
}

async function openSheet(date) {
    document.getElementById('sheetDateLabel').textContent = date;
    document.getElementById('sheetCard').classList.remove('d-none');
    const tbody = document.getElementById('sheetBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_transfer_sheet.php?date=${date}`);
        const data = await res.json();
        const entries = (data.data && data.data.entries) || [];
        tbody.innerHTML = entries.length
            ? entries.map(t => sheetRow(t, true)).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">এন্ট্রি নেই</td></tr>';
        document.getElementById('sheetCard').scrollIntoView({ behavior: 'smooth' });
    } catch {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

// ── Search ───────────────────────────────────────────────────────────────────
let _searchTimer = null;
document.getElementById('searchQuery')?.addEventListener('input', () => {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(runTransferSearch, 350);
});

async function runTransferSearch() {
    const q    = document.getElementById('searchQuery').value.trim();
    const type = document.getElementById('searchType').value;
    const card = document.getElementById('searchResultCard');
    const tbody = document.getElementById('searchResultBody');
    if (!q) { card.classList.add('d-none'); return; }
    card.classList.remove('d-none');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/search_transfers.php?q=${encodeURIComponent(q)}&type=${type}`);
        const data = await res.json();
        const entries = (data.data && data.data.entries) || [];
        tbody.innerHTML = entries.length
            ? entries.map(t => `
                <tr>
                    <td><a href="#" onclick="event.preventDefault();document.getElementById('listTabBtn').click();openSheet('${t.transfer_date}')">${t.transfer_date}</a></td>
                    <td>${esc(t.customer_name)}</td>
                    <td>${esc(t.customer_mobile || '—')}</td>
                    <td>${esc(t.product_name)}</td>
                    <td class="text-end">${parseFloat(t.quantity)} ${esc(t.unit)}</td>
                    <td class="small">${esc(t.from_branch_name)} → ${esc(t.to_branch_name)}</td>
                    <td class="small">${esc(t.driver_name || '—')}</td>
                    <td class="text-center">${statusBadge(t.status)}</td>
                </tr>`).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">কোনো ফলাফল নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">সার্চ করা যায়নি</td></tr>';
    }
}

// ── Receive (In / Return) ───────────────────────────────────────────────────
async function loadIncoming() {
    const branchId = document.getElementById('rcvBranch').value;
    const date     = document.getElementById('rcvDate').value;
    const tbody    = document.getElementById('incomingBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_incoming_transfers.php?branch_id=${branchId}&date=${date}`);
        const data = await res.json();
        const entries = (data.data && data.data.entries) || [];
        document.getElementById('incomingCount').textContent = entries.length;
        document.getElementById('incomingCount').classList.toggle('d-none', !entries.length);
        tbody.innerHTML = entries.length
            ? entries.map(t => `
                <tr>
                    <td>${t.transfer_date}</td>
                    <td>${esc(t.from_branch_name)}</td>
                    <td>${esc(t.customer_name)}
                        ${t.customer_mobile ? `<div class="small text-muted">${esc(t.customer_mobile)}</div>` : ''}</td>
                    <td>${esc(t.product_name)}</td>
                    <td class="text-end">${parseFloat(t.quantity)} ${esc(t.unit)}</td>
                    <td class="small">${esc(t.driver_name || '—')}
                        ${t.driver_mobile ? `<div class="text-muted">${esc(t.driver_mobile)}</div>` : ''}</td>
                    <td class="text-center text-nowrap">
                        <button class="btn btn-sm btn-success me-1" onclick="receiveTransfer(${t.id}, 'in')">
                            <i class="bi bi-box-arrow-in-down me-1"></i>ইন
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="receiveTransfer(${t.id}, 'return')">
                            <i class="bi bi-arrow-return-left me-1"></i>রিটার্ন
                        </button>
                    </td>
                </tr>`).join('')
            : '<tr><td colspan="7" class="text-center text-muted py-3">কোনো ইনকামিং ট্রান্সফার নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

function receiveTransfer(id, action) {
    let note = '';
    if (action === 'in') {
        if (!confirm('পণ্যটি এই ব্রাঞ্চের স্টকে যুক্ত হবে। নিশ্চিত?')) return;
    } else {
        note = prompt('রিটার্নের কারণ লিখুন (ঐচ্ছিক):') ?? '';
    }
    ajaxPost(`${BASE_URL}/api/transfer_receive.php`, { id, action, note }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { loadIncoming(); loadDateList(); }
    });
}

// ── Init ─────────────────────────────────────────────────────────────────────
if (CAN_WRITE) loadTodaySheet();
loadDateList();
if (IS_STAFF && STAFF_BRANCH) {
    // Staff lands on receive tab with their branch preselected
    document.getElementById('receiveTabBtn')?.click();
    loadIncoming();
}
