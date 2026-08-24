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
let _editingEntry  = null;
let tItemRowCounter = 0;

const transferProductOptsHtml = (TRANSFER_PRODUCTS || []).map(p =>
    `<option value="${p.id}" data-unit="${esc(p.unit)}">${esc(p.name)} (${esc(p.unit)})</option>`
).join('');

// One product+quantity row. Editing an existing pending entry is always a
// single product (transfer_entry_update.php updates one stock_transfers row),
// so the "add row"/"remove" controls only matter while creating a fresh batch.
function addTransferItemRow(productId, quantity) {
    tItemRowCounter++;
    const id = tItemRowCounter;
    const tr = document.createElement('tr');
    tr.id = 't_item_row_' + id;
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm t-item-product" required>
                <option value="">— পণ্য নির্বাচন —</option>
                ${transferProductOptsHtml}
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm t-item-qty"
                   min="0.01" step="0.01" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger t-item-remove"
                    onclick="removeTransferItemRow(${id})"><i class="bi bi-x-lg"></i></button>
        </td>`;
    document.getElementById('tItemsBody').appendChild(tr);
    if (productId) tsSet(tr.querySelector('.t-item-product'), String(productId), true);
    if (quantity)  tr.querySelector('.t-item-qty').value = quantity;
    if (typeof initTomSelect === 'function') initTomSelect(tr.querySelector('.t-item-product'));
    checkTransferItemsEmpty();
}

function removeTransferItemRow(id) {
    document.getElementById('t_item_row_' + id)?.remove();
    // Never leave zero rows while adding a fresh batch — always one to fill in.
    if (!document.getElementById('tEntryId').value && !document.querySelectorAll('#tItemsBody tr').length) {
        addTransferItemRow();
    }
    checkTransferItemsEmpty();
}

function checkTransferItemsEmpty() {
    const has = document.querySelectorAll('#tItemsBody tr').length > 0;
    document.getElementById('tNoItemsAlert').classList.toggle('d-none', has);
}

function collectTransferItems() {
    const items = [];
    document.querySelectorAll('#tItemsBody tr').forEach(tr => {
        const sel = tr.querySelector('.t-item-product');
        const qty = tr.querySelector('.t-item-qty');
        if (sel?.value && parseFloat(qty?.value) > 0) {
            items.push({ product_id: parseInt(sel.value), quantity: parseFloat(qty.value) });
        }
    });
    return items;
}

function resetTransferItemRows() {
    document.getElementById('tItemsBody').innerHTML = '';
    tItemRowCounter = 0;
    addTransferItemRow();
}

// Pre-select the logged-in manager/assistant manager as order-placer; they
// remain free to pick someone else for this particular delivery.
function applyDefaultOrderManager() {
    const sel = document.getElementById('tOrderManager');
    if (!sel || !CURRENT_USER_NAME) return;
    const match = Array.from(sel.options).some(o => o.value === CURRENT_USER_NAME);
    if (match) tsSet(sel, CURRENT_USER_NAME, true);
}

function submitTransferEntry(e) {
    e.preventDefault();
    const isEdit = !!document.getElementById('tEntryId').value;

    if (isEdit) {
        const row = document.querySelector('#tItemsBody tr');
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
            product_id:       row?.querySelector('.t-item-product')?.value || '',
            quantity:         row?.querySelector('.t-item-qty')?.value || '',
            note:             document.getElementById('tNote').value,
        };
        const btn = document.getElementById('tSubmitBtn');
        btn.disabled = true;
        ajaxPost(`${BASE_URL}/api/transfer_entry_update.php`, data, res => {
            btn.disabled = false;
            showToast(res.message, res.success ? 'success' : 'danger');
            if (res.success) {
                cancelEditEntry();
                loadTodaySheet();
                loadDateList();
            }
        });
        return;
    }

    const items = collectTransferItems();
    if (!items.length) { showToast('কমপক্ষে একটি পণ্য যোগ করুন।', 'warning'); return; }

    const data = {
        transfer_date:    document.getElementById('tDate').value,
        from_branch_id:   document.getElementById('tFromBranch').value,
        to_branch_id:     document.getElementById('tToBranch').value,
        customer_name:    document.getElementById('tCustomerName').value,
        customer_address: document.getElementById('tCustomerAddress').value,
        customer_mobile:  document.getElementById('tCustomerMobile').value,
        order_manager:    document.getElementById('tOrderManager').value,
        driver_name:      document.getElementById('tDriverName').value,
        driver_mobile:    document.getElementById('tDriverMobile').value,
        note:             document.getElementById('tNote').value,
        items:            JSON.stringify(items),
    };
    const btn = document.getElementById('tSubmitBtn');
    btn.disabled = true;
    ajaxPost(`${BASE_URL}/api/transfer_entry_add.php`, data, res => {
        btn.disabled = false;
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            document.getElementById('transferForm').reset();
            tsSyncForm('transferForm');
            document.getElementById('tDate').value = TODAY;
            resetTransferItemRows();
            applyDefaultOrderManager();
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
    tsSet(document.getElementById('tOrderManager'), entry.order_manager || '', true);
    document.getElementById('tDriverName').value       = entry.driver_name || '';
    document.getElementById('tDriverMobile').value     = entry.driver_mobile || '';
    document.getElementById('tItemsBody').innerHTML    = '';
    addTransferItemRow(entry.product_id, entry.quantity);
    // An existing entry is one DB row — no adding more products to it here.
    document.getElementById('tAddRowBtn').classList.add('d-none');
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
    document.getElementById('tAddRowBtn').classList.remove('d-none');
    resetTransferItemRows();
    applyDefaultOrderManager();
    document.getElementById('tSubmitBtn').innerHTML =
        '<i class="bi bi-plus-circle me-1"></i>শিটে যুক্ত করুন (এন্টার)';
    document.getElementById('tCancelEditBtn').classList.add('d-none');
}

// ── Sheet rendering (shared by today-sheet and date sheets) ─────────────────
function actionButtons(t) {
    if (!CAN_WRITE) return '';
    // A past-date sheet is a record, not a working document: the only things
    // allowed there are finishing a pending transfer and correcting a
    // returned one. Transfer.php enforces the same rule server-side.
    const isPast = !!t.transfer_date && t.transfer_date < TODAY;
    let html = '';
    if (t.status === 'pending' || t.status === 'returned') {
        html += `<button class="btn btn-sm btn-success me-1" title="ট্রান্সফার (পাঠান)"
                    onclick="sendTransfer(${t.id})"><i class="bi bi-send"></i></button>`;
        if (!isPast || t.status === 'returned') {
            html += `<button class="btn btn-sm btn-outline-warning me-1" title="এডিট"
                        onclick='editEntry(${JSON.stringify(t).replace(/'/g, "&#39;")})'>
                        <i class="bi bi-pencil"></i></button>`;
        }
    }
    if (t.status === 'pending' && !isPast) {
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
        const entries = (data.entries) || [];
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
        const dates = (data.dates) || [];
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
        const entries = (data.entries) || [];
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
        const entries = (data.entries) || [];
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
let _incoming = [];

// Filters the already-loaded list rather than re-querying: the receive page
// shows one branch/date at a time, so everything needed is on the client.
function incomingMatches(t, q) {
    if (!q) return true;
    return [t.customer_name, t.customer_mobile, t.driver_name, t.driver_mobile]
        .some(v => String(v || '').toLowerCase().includes(q));
}

function renderIncoming() {
    const tbody = document.getElementById('incomingBody');
    const q = (document.getElementById('rcvSearch')?.value || '').trim().toLowerCase();
    const rows = _incoming.filter(t => incomingMatches(t, q));
    tbody.innerHTML = rows.length
        ? rows.map(t => `
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
        : `<tr><td colspan="7" class="text-center text-muted py-3">${
             q ? 'এই সার্চে কিছু পাওয়া যায়নি' : 'কোনো ইনকামিং ট্রান্সফার নেই'}</td></tr>`;
}

document.getElementById('rcvSearch')?.addEventListener('input', renderIncoming);

async function loadIncoming() {
    const branchId = document.getElementById('rcvBranch').value;
    const date     = document.getElementById('rcvDate').value;
    const tbody    = document.getElementById('incomingBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_incoming_transfers.php?branch_id=${branchId}&date=${date}`);
        const data = await res.json();
        _incoming = (data.entries) || [];
        document.getElementById('incomingCount').textContent = _incoming.length;
        document.getElementById('incomingCount').classList.toggle('d-none', !_incoming.length);
        renderIncoming();
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
if (CAN_WRITE) {
    loadTodaySheet();
    resetTransferItemRows();
    applyDefaultOrderManager();
}
loadDateList();
if (IS_STAFF && STAFF_BRANCH) {
    // Staff lands on receive tab with their branch preselected
    document.getElementById('receiveTabBtn')?.click();
    loadIncoming();
}
