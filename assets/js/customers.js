// ============================================
// Customer Management — AJAX CRUD
// ============================================

const cModal       = new bootstrap.Modal(document.getElementById('customerModal'));
const refModal     = new bootstrap.Modal(document.getElementById('refModal'));
const upgradeModal = new bootstrap.Modal(document.getElementById('upgradeModal'));

// ---- Helpers ----
function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function jsEsc(str) {
    return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

// ---- Extra phones widget ----
let extraPhones = [];

function renderExtraPhones() {
    document.getElementById('extraPhones').innerHTML = extraPhones.map((p, i) => `
        <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
            ${esc(p)}
            <a href="#" class="text-danger" onclick="event.preventDefault();removeExtraPhone(${i})">
                <i class="bi bi-x"></i>
            </a>
        </span>`).join('');
}
function removeExtraPhone(i) {
    extraPhones.splice(i, 1);
    renderExtraPhones();
}
document.getElementById('btnAddPhone').addEventListener('click', () => {
    const extra = prompt('অতিরিক্ত মোবাইল নাম্বার লিখুন:');
    if (extra && extra.trim()) {
        extraPhones.push(extra.trim());
        renderExtraPhones();
    }
});

// ---- Account type toggle: short hides full-only fields ----
function applyTypeVisibility() {
    const isShort = document.getElementById('typeShort').checked;
    document.querySelectorAll('.cust-full-only').forEach(el =>
        el.classList.toggle('d-none', isShort));
}
document.getElementById('typeFull').addEventListener('change', applyTypeVisibility);
document.getElementById('typeShort').addEventListener('change', applyTypeVisibility);

// ---- Photo upload ----
const custPhotoFile   = document.getElementById('custPhotoFile');
const custPhotoHidden = document.getElementById('customerPhoto');

custPhotoFile.addEventListener('change', async () => {
    if (!custPhotoFile.files.length) return;
    const fd = new FormData();
    fd.append('photo', custPhotoFile.files[0]);
    custPhotoFile.disabled = true;
    try {
        const res  = await fetch(`${BASE_URL}/api/upload_customer_photo.php`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            custPhotoHidden.value = data.data.path;
            document.getElementById('custPhotoPreview').src = `${BASE_URL}/${data.data.path}`;
            document.getElementById('custPhotoPreviewWrap').classList.remove('d-none');
        } else {
            custPhotoFile.value = '';
            showToast(data.message, 'danger');
        }
    } catch {
        custPhotoFile.value = '';
        showToast('ছবি আপলোড করা যায়নি।', 'danger');
    } finally {
        custPhotoFile.disabled = false;
    }
});

// ---- Modal ----
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'নতুন কাস্টমার';
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    custPhotoHidden.value = '';
    document.getElementById('custPhotoPreviewWrap').classList.add('d-none');
    document.getElementById('accountTypeWrap').classList.remove('d-none');
    document.getElementById('acctNoInfo').classList.add('d-none');
    document.getElementById('typeFull').checked = true;
    extraPhones = [];
    renderExtraPhones();
    applyTypeVisibility();
    cModal.show();
}

async function openEditModal(id) {
    try {
        const res  = await fetch(`${BASE_URL}/api/get_customer_profile.php?id=${id}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message, 'danger'); return; }

        const c = data.data.customer;
        document.getElementById('modalTitle').textContent = 'কাস্টমার সম্পাদনা';
        document.getElementById('customerId').value       = c.id;
        document.getElementById('customerName').value     = c.name;
        document.getElementById('customerPhone').value    = c.phone || '';
        document.getElementById('customerWhatsapp').value = c.whatsapp || '';
        document.getElementById('customerImo').value      = c.imo || '';
        document.getElementById('customerAddress').value  = c.address || '';
        document.getElementById('customerBookNo').value   = c.book_no || '';
        document.getElementById('customerDueLimit').value = c.due_limit || 0;
        custPhotoHidden.value = c.photo || '';
        custPhotoFile.value = '';
        if (c.photo) {
            document.getElementById('custPhotoPreview').src = `${BASE_URL}/${c.photo}`;
            document.getElementById('custPhotoPreviewWrap').classList.remove('d-none');
        } else {
            document.getElementById('custPhotoPreviewWrap').classList.add('d-none');
        }

        // Account type is fixed after creation (upgrade flow handles short→full)
        document.getElementById('accountTypeWrap').classList.add('d-none');
        const info = document.getElementById('acctNoInfo');
        if (c.account_no) {
            info.innerHTML = `<i class="bi bi-journal-bookmark me-1"></i>একাউন্ট নং: <strong>${esc(c.account_no)}</strong>`;
            info.classList.remove('d-none');
        } else {
            info.classList.add('d-none');
        }

        extraPhones = (data.data.phones || []).map(p => p.phone);
        renderExtraPhones();
        document.querySelectorAll('.cust-full-only').forEach(el => el.classList.remove('d-none'));
        cModal.show();
    } catch {
        showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger');
    }
}

function submitCustomer(e) {
    e.preventDefault();
    const id  = document.getElementById('customerId').value;
    const url = id
        ? BASE_URL + '/api/update_customer.php'
        : BASE_URL + '/api/add_customer.php';

    const data = {
        id:        id,
        name:      document.getElementById('customerName').value,
        phone:     document.getElementById('customerPhone').value,
        whatsapp:  document.getElementById('customerWhatsapp').value,
        imo:       document.getElementById('customerImo').value,
        address:   document.getElementById('customerAddress').value,
        book_no:   document.getElementById('customerBookNo').value,
        due_limit: document.getElementById('customerDueLimit').value || 0,
        photo:     custPhotoHidden.value,
        phones:    JSON.stringify(extraPhones),
    };
    if (!id) {
        data.account_type = document.querySelector('input[name="account_type"]:checked').value;
    }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        if (res.success) {
            cModal.hide();
            let msg = res.message;
            if (res.data && res.data.account_no) msg += ` (একাউন্ট নং: ${res.data.account_no})`;
            showToast(msg, 'success');
            loadCustomers();
        } else {
            showToast(res.message, 'danger');
        }
    });
}

// ---- Delete ----
function deleteCustomer(id, name) {
    if (!confirm(`"${name}" ডিলিট করবেন?\nএই কাজটি পূর্বাবস্থায় ফেরানো যাবে না।`)) return;
    ajaxPost(BASE_URL + '/api/delete_customer.php', { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadCustomers();
    });
}

// ---- References ----
async function openRefModal(id, name) {
    document.getElementById('refCustomerId').value  = id;
    document.getElementById('refCustName').textContent = name;
    document.getElementById('refError').classList.add('d-none');
    document.getElementById('refName').value = '';
    document.getElementById('refPhone').value = '';
    document.getElementById('refAddress').value = '';
    tsSet(document.getElementById('refUserId'), '', true);
    document.getElementById('refPhotoFile').value = '';
    await loadReferences(id);
    refModal.show();
}

async function loadReferences(customerId) {
    const wrap = document.getElementById('refList');
    wrap.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span></div>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_customer_profile.php?id=${customerId}`);
        const data = await res.json();
        const refs = (data.data && data.data.references) || [];
        if (!refs.length) {
            wrap.innerHTML = '<p class="text-muted text-center small mb-0">কোনো রেফারেন্স নেই</p>';
            return;
        }
        wrap.innerHTML = refs.map(r => `
            <div class="d-flex align-items-center gap-2 border rounded p-2 mb-1">
                ${r.photo
                    ? `<img src="${BASE_URL}/${esc(r.photo)}" class="rounded-circle border" style="width:38px;height:38px;object-fit:cover">`
                    : `<span class="d-inline-flex align-items-center justify-content-center rounded-circle border bg-light text-muted" style="width:38px;height:38px"><i class="bi bi-person"></i></span>`}
                <div class="flex-grow-1">
                    <div class="fw-semibold">${esc(r.name)}
                        ${r.ref_user_name ? `<span class="badge bg-primary-subtle text-primary-emphasis border ms-1">স্টাফ: ${esc(r.ref_user_name)}</span>` : ''}
                    </div>
                    <small class="text-muted">${esc(r.phone || '')} ${r.address ? '· ' + esc(r.address) : ''}</small>
                </div>
                ${IS_ADMIN ? `
                <button class="btn btn-sm btn-outline-danger" onclick="deleteReference(${r.id}, ${r.customer_id})">
                    <i class="bi bi-trash"></i>
                </button>` : ''}
            </div>`).join('');
    } catch {
        wrap.innerHTML = '<p class="text-danger small mb-0">লোড করা যায়নি</p>';
    }
}

document.getElementById('btnSaveRef').addEventListener('click', async () => {
    const customerId = document.getElementById('refCustomerId').value;
    const name = document.getElementById('refName').value.trim();
    const err  = document.getElementById('refError');
    err.classList.add('d-none');
    if (!name) { err.textContent = 'রেফারেন্স ব্যক্তির নাম দিন।'; err.classList.remove('d-none'); return; }

    // Upload photo first if selected
    let photoPath = '';
    const photoFile = document.getElementById('refPhotoFile');
    if (photoFile.files.length) {
        const fd = new FormData();
        fd.append('photo', photoFile.files[0]);
        try {
            const upRes  = await fetch(`${BASE_URL}/api/upload_customer_photo.php`, { method: 'POST', body: fd });
            const upData = await upRes.json();
            if (upData.success) photoPath = upData.data.path;
        } catch { /* photo optional — continue without it */ }
    }

    ajaxPost(`${BASE_URL}/api/add_customer_reference.php`, {
        customer_id: customerId,
        name,
        phone:       document.getElementById('refPhone').value,
        address:     document.getElementById('refAddress').value,
        ref_user_id: document.getElementById('refUserId').value || '',
        photo:       photoPath,
    }, res => {
        if (res.success) {
            showToast(res.message, 'success');
            document.getElementById('refName').value = '';
            document.getElementById('refPhone').value = '';
            document.getElementById('refAddress').value = '';
            document.getElementById('refPhotoFile').value = '';
            tsSet(document.getElementById('refUserId'), '', true);
            loadReferences(customerId);
        } else {
            err.textContent = res.message;
            err.classList.remove('d-none');
        }
    });
});

function deleteReference(refId, customerId) {
    if (!confirm('রেফারেন্সটি ডিলিট করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/delete_customer_reference.php`, { id: refId }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadReferences(customerId);
    });
}

// ---- Upgrade short → full ----
function openUpgradeModal(id, name, bookNo) {
    document.getElementById('upgradeCustomerId').value = id;
    document.getElementById('upgradeCustName').textContent = name;
    document.getElementById('upgradeBookNo').value = bookNo || '';
    upgradeModal.show();
}

document.getElementById('btnConfirmUpgrade').addEventListener('click', () => {
    const id = document.getElementById('upgradeCustomerId').value;
    ajaxPost(`${BASE_URL}/api/upgrade_customer.php`, {
        id, book_no: document.getElementById('upgradeBookNo').value
    }, res => {
        if (res.success) {
            upgradeModal.hide();
            let msg = res.message;
            if (res.data && res.data.account_no) msg += ` (একাউন্ট নং: ${res.data.account_no})`;
            showToast(msg, 'success');
            loadCustomers();
        } else {
            showToast(res.message, 'danger');
        }
    });
});

// ---- Pagination state ----
const CUST_PAGE_SIZE = 50;
let _allCustomers    = [];
let _filteredCust    = [];
let _custPage        = 1;

// ---- Load & Render ----
function loadCustomers() {
    fetch(BASE_URL + '/api/get_customers.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                _allCustomers = res.data;
                _filteredCust = res.data;
                _custPage     = 1;
                renderCustomersPage(1);
            }
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderCustomers(list) {
    _allCustomers = list;
    _filteredCust = list;
    _custPage     = 1;
    renderCustomersPage(1);
}

function renderCustomersPage(page) {
    _custPage = page;
    const tbody = document.getElementById('customersBody');

    if (!_filteredCust.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">কোনো কাস্টমার নেই</td></tr>';
        document.getElementById('custPaginationBar').style.display = 'none';
        return;
    }

    const totalPages = Math.ceil(_filteredCust.length / CUST_PAGE_SIZE);
    const start      = (page - 1) * CUST_PAGE_SIZE;
    const pageData   = _filteredCust.slice(start, start + CUST_PAGE_SIZE);

    tbody.innerHTML = pageData.map((c, i) => `
        <tr>
            <td class="text-muted">${start + i + 1}</td>
            <td class="fw-semibold">
                ${c.photo ? `<img src="${BASE_URL}/${esc(c.photo)}" class="rounded-circle border me-1" style="width:26px;height:26px;object-fit:cover">` : ''}
                ${esc(c.name)}
            </td>
            <td>${c.account_no ? `<span class="badge bg-dark">${esc(c.account_no)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${(c.account_type || 'full') === 'short'
                ? '<span class="badge bg-warning text-dark">শর্ট</span>'
                : '<span class="badge bg-success">ফুল</span>'}</td>
            <td>${esc(c.phone || '—')}</td>
            <td class="text-muted small">${esc(c.address || '—')}</td>
            <td class="text-end">${fmt(c.total_purchase)}</td>
            <td class="text-end">
                ${parseFloat(c.total_due) > 0
                    ? `<span class="badge bg-danger">${fmt(c.total_due)}</span>`
                    : '<span class="text-success small">পরিশোধিত</span>'}
            </td>
            <td class="text-center text-nowrap">
                <a class="btn btn-sm btn-outline-dark" title="একাউন্ট/খাতা"
                   href="${BASE_URL}/pages/customer_account.php?id=${c.id}">
                    <i class="bi bi-journal-bookmark"></i>
                </a>
                ${(c.account_type || 'full') === 'short' && IS_ADMIN ? `
                <button class="btn btn-sm btn-outline-success" title="ফুল একাউন্টে রূপান্তর"
                    onclick="openUpgradeModal(${c.id}, '${jsEsc(c.name)}', '${jsEsc(c.book_no || '')}')">
                    <i class="bi bi-arrow-up-circle"></i>
                </button>` : ''}
                <button class="btn btn-sm btn-outline-secondary" title="রেফারেন্স"
                    onclick="openRefModal(${c.id}, '${jsEsc(c.name)}')">
                    <i class="bi bi-person-check"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" title="সম্পাদনা"
                    onclick="openEditModal(${c.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                ${IS_ADMIN && parseInt(c.id) !== 1 ? `
                <button class="btn btn-sm btn-outline-danger" title="ডিলিট"
                    onclick="deleteCustomer(${c.id}, '${jsEsc(c.name)}')">
                    <i class="bi bi-trash"></i>
                </button>` : ''}
            </td>
        </tr>
    `).join('');

    // Pagination bar
    const bar  = document.getElementById('custPaginationBar');
    const info = document.getElementById('custPageInfo');
    const nav  = document.getElementById('custPagination');
    const from = start + 1;
    const to   = Math.min(start + CUST_PAGE_SIZE, _filteredCust.length);
    info.textContent = `${_filteredCust.length} জনের মধ্যে ${from}–${to} দেখাচ্ছে`;

    if (totalPages <= 1) {
        bar.style.display = 'none';
        return;
    }

    bar.style.removeProperty('display');

    let pages = '';
    pages += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault();renderCustomersPage(${page - 1})">&#8249;</a></li>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
            if (i === 3 || i === totalPages - 2) pages += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        pages += `<li class="page-item ${i === page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault();renderCustomersPage(${i})">${i}</a></li>`;
    }
    pages += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault();renderCustomersPage(${page + 1})">&#8250;</a></li>`;
    nav.innerHTML = pages;
}

// ---- Live Search (name / phone / account no / book no) ----
document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    _filteredCust = q
        ? _allCustomers.filter(c =>
            (c.name       || '').toLowerCase().includes(q) ||
            (c.phone      || '').toLowerCase().includes(q) ||
            (c.account_no || '').toLowerCase().includes(q) ||
            (c.book_no    || '').toLowerCase().includes(q))
        : _allCustomers;
    _custPage = 1;
    renderCustomersPage(1);
});

// ---- Init ----
loadCustomers();
