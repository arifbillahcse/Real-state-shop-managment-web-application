// ============================================
// Supplier Management — AJAX CRUD
// ============================================

const sModal = new bootstrap.Modal(document.getElementById('supplierModal'));

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

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'নতুন সাপ্লাইয়ার';
    document.getElementById('supplierForm').reset();
    document.getElementById('supplierId').value = '';
    sModal.show();
}

function openEditModal(id, name, phone, address) {
    document.getElementById('modalTitle').textContent = 'সাপ্লাইয়ার সম্পাদনা';
    document.getElementById('supplierId').value      = id;
    document.getElementById('supplierName').value    = name;
    document.getElementById('supplierPhone').value   = phone;
    document.getElementById('supplierAddress').value = address;
    sModal.show();
}

function submitSupplier(e) {
    e.preventDefault();
    const id  = document.getElementById('supplierId').value;
    const url = id
        ? BASE_URL + '/api/update_supplier.php'
        : BASE_URL + '/api/add_supplier.php';

    const data = {
        id:      id,
        name:    document.getElementById('supplierName').value,
        phone:   document.getElementById('supplierPhone').value,
        address: document.getElementById('supplierAddress').value,
    };

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        if (res.success) {
            sModal.hide();
            showToast(res.message, 'success');
            loadSuppliers();
        } else {
            showToast(res.message, 'danger');
        }
    });
}

function deleteSupplier(id, name) {
    if (!confirm(`"${name}" ডিলিট করবেন?`)) return;
    ajaxPost(BASE_URL + '/api/delete_supplier.php', { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadSuppliers();
    });
}

function loadSuppliers() {
    fetch(BASE_URL + '/api/get_suppliers.php')
        .then(r => r.json())
        .then(res => { if (res.success) renderSuppliers(res.suppliers || []); })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

const SUPP_PAGE_SIZE = 50;
let _allSuppliers    = [];
let _filteredSupp    = [];
let _suppPage        = 1;

function renderSuppliers(list) {
    _allSuppliers = list;
    _filteredSupp = list;
    _suppPage     = 1;
    renderSuppPage(1);
}

function renderSuppPage(page) {
    _suppPage = page;
    const tbody = document.getElementById('suppliersBody');

    if (!_filteredSupp.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">কোনো সাপ্লাইয়ার নেই</td></tr>';
        document.getElementById('suppPaginationBar').style.display = 'none';
        return;
    }

    const totalPages = Math.ceil(_filteredSupp.length / SUPP_PAGE_SIZE);
    const start      = (page - 1) * SUPP_PAGE_SIZE;
    const pageData   = _filteredSupp.slice(start, start + SUPP_PAGE_SIZE);

    tbody.innerHTML = pageData.map((s, i) => `
        <tr>
            <td class="text-muted">${start + i + 1}</td>
            <td class="fw-semibold">${esc(s.name)}</td>
            <td>${esc(s.phone || '—')}</td>
            <td class="text-muted small">${esc(s.address || '—')}</td>
            <td class="text-end">${fmt(s.total_purchase || 0)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary me-1"
                    onclick="openEditModal(${s.id}, '${jsEsc(s.name)}', '${jsEsc(s.phone || '')}', '${jsEsc(s.address || '')}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger"
                    onclick="deleteSupplier(${s.id}, '${jsEsc(s.name)}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');

    const bar  = document.getElementById('suppPaginationBar');
    const from = start + 1;
    const to   = Math.min(start + SUPP_PAGE_SIZE, _filteredSupp.length);
    document.getElementById('suppPageInfo').textContent = `${_filteredSupp.length} জনের মধ্যে ${from}–${to} দেখাচ্ছে`;

    if (totalPages <= 1) { bar.style.display = 'none'; return; }
    bar.style.removeProperty('display');

    let html = `<li class="page-item ${page===1?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderSuppPage(${page-1})">&#8249;</a></li>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages-1 && Math.abs(i-page) > 1) {
            if (i === 3 || i === totalPages-2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        html += `<li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderSuppPage(${i})">${i}</a></li>`;
    }
    html += `<li class="page-item ${page===totalPages?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderSuppPage(${page+1})">&#8250;</a></li>`;
    document.getElementById('suppPagination').innerHTML = html;
}

document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    _filteredSupp = q
        ? _allSuppliers.filter(s =>
            (s.name    || '').toLowerCase().includes(q) ||
            (s.phone   || '').toLowerCase().includes(q))
        : _allSuppliers;
    _suppPage = 1;
    renderSuppPage(1);
});

loadSuppliers();
