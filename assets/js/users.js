// ============================================
// User Management — AJAX
// ============================================

const uModal  = new bootstrap.Modal(document.getElementById('userModal'));
const pwModal = new bootstrap.Modal(document.getElementById('passwordModal'));

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function jsEsc(str) {
    return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function toggleBranchField() {
    const role  = document.getElementById('userRole')?.value;
    const group = document.getElementById('branchFieldGroup');
    if (group) group.style.display = role === 'staff' ? '' : 'none';
}

// ---- Add / Edit Modal ----
function openAddModal() {
    document.getElementById('userModalTitle').textContent = 'নতুন ব্যবহারকারী';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('usernameGroup').classList.remove('d-none');
    document.getElementById('passwordGroup').classList.remove('d-none');
    document.getElementById('userUsername').required = true;
    document.getElementById('userPassword').required = true;
    toggleBranchField();
    uModal.show();
}

function openEditModal(id, name, username, role, branchId) {
    document.getElementById('userModalTitle').textContent = 'ব্যবহারকারী সম্পাদনা';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value       = id;
    document.getElementById('userName').value      = name;
    document.getElementById('userUsername').value  = username;
    document.getElementById('userRole').value      = role;
    const branchSel = document.getElementById('userBranch');
    if (branchSel) branchSel.value = branchId || '';
    // Username & password not editable here
    document.getElementById('usernameGroup').classList.add('d-none');
    document.getElementById('passwordGroup').classList.add('d-none');
    document.getElementById('userUsername').required = false;
    document.getElementById('userPassword').required = false;
    toggleBranchField();
    uModal.show();
}

function submitUser(e) {
    e.preventDefault();
    const id  = document.getElementById('userId').value;
    const url = id
        ? BASE_URL + '/api/update_user.php'
        : BASE_URL + '/api/add_user.php';

    const role = document.getElementById('userRole').value;
    const data = {
        id:        id,
        name:      document.getElementById('userName').value,
        role:      role,
        branch_id: role === 'staff' ? (document.getElementById('userBranch')?.value || '') : '',
    };
    if (!id) {
        data.username = document.getElementById('userUsername').value;
        data.password = document.getElementById('userPassword').value;
    }

    const btn = document.getElementById('userSaveBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        if (res.success) {
            uModal.hide();
            showToast(res.message, 'success');
            loadUsers();
        } else {
            showToast(res.message, 'danger');
        }
    });
}

// ---- Toggle status ----
function toggleUser(id, currentlyActive, name) {
    const action = currentlyActive ? 'নিষ্ক্রিয়' : 'সক্রিয়';
    if (!confirm(`"${name}" কে ${action} করবেন?`)) return;
    ajaxPost(BASE_URL + '/api/toggle_user.php',
        { id, active: currentlyActive ? 0 : 1 }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadUsers();
    });
}

// ---- Reset password ----
function openPasswordModal(id, name) {
    document.getElementById('passwordForm').reset();
    document.getElementById('pwUserId').value     = id;
    document.getElementById('pwUserName').textContent = name;
    pwModal.show();
}

function submitPassword(e) {
    e.preventDefault();
    const btn = document.getElementById('pwSaveBtn');
    btn.disabled = true;
    ajaxPost(BASE_URL + '/api/reset_password.php', {
        id:       document.getElementById('pwUserId').value,
        password: document.getElementById('pwNew').value,
    }, res => {
        btn.disabled = false;
        if (res.success) {
            pwModal.hide();
            showToast(res.message, 'success');
        } else {
            showToast(res.message, 'danger');
        }
    });
}

// ---- Load & Render ----
function loadUsers() {
    fetch(BASE_URL + '/api/get_users.php')
        .then(r => r.json())
        .then(res => { if (res.success) renderUsers(res.data); })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderUsers(list) {
    const tbody   = document.getElementById('usersBody');
    const colSpan = HAS_BRANCHES ? 7 : 6;
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-5 text-muted">কোনো ব্যবহারকারী নেই</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map((u, i) => {
        const active    = parseInt(u.is_active) === 1;
        const isAdmin   = u.role === 'admin';
        const isManager = u.role === 'manager';
        const isSelf    = parseInt(u.id) === CURRENT_UID;
        const roleLabel = isAdmin ? 'অ্যাডমিন' : isManager ? 'ম্যানেজার' : 'স্টাফ';
        const roleBg    = isAdmin ? 'danger' : isManager ? 'warning text-dark' : 'secondary';
        const hasNoBranch = isAdmin || isManager;
        const branchCell = HAS_BRANCHES
            ? `<td>${u.branch_name && !hasNoBranch ? `<span class="badge bg-secondary"><i class="bi bi-shop me-1"></i>${esc(u.branch_name)}</span>` : '<span class="text-muted">—</span>'}</td>`
            : '';
        return `
        <tr class="${active ? '' : 'text-muted'}">
            <td class="text-muted">${i + 1}</td>
            <td class="fw-semibold">${esc(u.name)}
                ${isSelf ? '<span class="badge bg-info text-dark ms-1">আপনি</span>' : ''}
            </td>
            <td>${esc(u.username)}</td>
            <td class="text-center">
                <span class="badge bg-${roleBg}">
                    ${roleLabel}
                </span>
            </td>
            ${branchCell}
            <td class="text-center">
                <span class="badge bg-${active ? 'success' : 'secondary'}">
                    ${active ? 'সক্রিয়' : 'নিষ্ক্রিয়'}
                </span>
            </td>
            <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-primary me-1"
                    onclick="openEditModal(${u.id}, '${jsEsc(u.name)}', '${jsEsc(u.username)}', '${u.role}', '${u.branch_id || ''}')"
                    title="সম্পাদনা">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning me-1"
                    onclick="openPasswordModal(${u.id}, '${jsEsc(u.name)}')"
                    title="পাসওয়ার্ড রিসেট">
                    <i class="bi bi-key"></i>
                </button>
                ${isSelf ? '' : `
                <button class="btn btn-sm btn-outline-${active ? 'danger' : 'success'}"
                    onclick="toggleUser(${u.id}, ${active ? 1 : 0}, '${jsEsc(u.name)}')"
                    title="${active ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন'}">
                    <i class="bi bi-${active ? 'person-x' : 'person-check'}"></i>
                </button>`}
            </td>
        </tr>`;
    }).join('');
}

loadUsers();
