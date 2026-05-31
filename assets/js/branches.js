// ============================================
// Branch Management — AJAX CRUD
// ============================================

const bModal = new bootstrap.Modal(document.getElementById('branchModal'));

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function jsEsc(str) {
    return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function openAddModal() {
    document.getElementById('branchModalTitle').textContent = 'নতুন ব্রাঞ্চ';
    document.getElementById('branchForm').reset();
    document.getElementById('branchId').value = '';
    bModal.show();
}

function openEditModal(id, name, phone, address) {
    document.getElementById('branchModalTitle').textContent = 'ব্রাঞ্চ সম্পাদনা';
    document.getElementById('branchId').value      = id;
    document.getElementById('branchName').value    = name;
    document.getElementById('branchPhone').value   = phone;
    document.getElementById('branchAddress').value = address;
    bModal.show();
}

function submitBranch(e) {
    e.preventDefault();
    const id  = document.getElementById('branchId').value;
    const url = id
        ? BASE_URL + '/api/update_branch.php'
        : BASE_URL + '/api/add_branch.php';

    const data = {
        id:      id,
        name:    document.getElementById('branchName').value,
        phone:   document.getElementById('branchPhone').value,
        address: document.getElementById('branchAddress').value,
    };

    const btn = document.getElementById('branchSaveBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        if (res.success) {
            bModal.hide();
            showToast(res.message, 'success');
            loadBranches();
        } else {
            showToast(res.message, 'danger');
        }
    });
}

function deleteBranch(id, name) {
    if (!confirm(`"${name}" ব্রাঞ্চটি মুছবেন?\n\nশুধুমাত্র তখনই মোছা যাবে যখন এই ব্রাঞ্চে কোনো স্টক বা বিক্রয় নেই।`)) return;
    ajaxPost(BASE_URL + '/api/delete_branch.php', { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadBranches();
    });
}

function loadBranches() {
    fetch(BASE_URL + '/api/get_branches.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) renderBranches(res.data || []);
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderBranches(list) {
    const tbody = document.getElementById('branchesBody');
    if (!list.length) {
        tbody.innerHTML = `
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
                কোনো ব্রাঞ্চ নেই। "নতুন ব্রাঞ্চ" বাটনে ক্লিক করুন।
              </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = list.map((b, i) => `
        <tr>
            <td class="text-muted">${i + 1}</td>
            <td>
                <span class="fw-semibold"><i class="bi bi-shop me-1 text-danger"></i>${esc(b.name)}</span>
            </td>
            <td>${esc(b.phone || '—')}</td>
            <td class="text-muted small">${esc(b.address || '—')}</td>
            <td class="text-center">
                <span class="badge bg-secondary">${b.staff_count} জন</span>
            </td>
            <td class="text-center">
                <span class="badge bg-info text-dark">${b.stock_entries}</span>
            </td>
            <td class="text-center">
                <span class="badge bg-success">${b.sales_count}</span>
            </td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary me-1"
                    onclick="openEditModal(${b.id}, '${jsEsc(b.name)}', '${jsEsc(b.phone || '')}', '${jsEsc(b.address || '')}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger"
                    onclick="deleteBranch(${b.id}, '${jsEsc(b.name)}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

loadBranches();
