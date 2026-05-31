// ============================================
// Customer Management — AJAX CRUD
// ============================================

const cModal = new bootstrap.Modal(document.getElementById('customerModal'));

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

// ---- Modal ----
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'নতুন কাস্টমার';
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    cModal.show();
}

function openEditModal(id, name, phone, address) {
    document.getElementById('modalTitle').textContent = 'কাস্টমার সম্পাদনা';
    document.getElementById('customerId').value      = id;
    document.getElementById('customerName').value    = name;
    document.getElementById('customerPhone').value   = phone;
    document.getElementById('customerAddress').value = address;
    cModal.show();
}

function submitCustomer(e) {
    e.preventDefault();
    const id  = document.getElementById('customerId').value;
    const url = id
        ? BASE_URL + '/api/update_customer.php'
        : BASE_URL + '/api/add_customer.php';

    const data = {
        id:      id,
        name:    document.getElementById('customerName').value,
        phone:   document.getElementById('customerPhone').value,
        address: document.getElementById('customerAddress').value,
    };

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    ajaxPost(url, data, res => {
        btn.disabled = false;
        if (res.success) {
            cModal.hide();
            showToast(res.message, 'success');
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

// ---- Load & Render ----
function loadCustomers() {
    fetch(BASE_URL + '/api/get_customers.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) renderCustomers(res.data);
        })
        .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));
}

function renderCustomers(list) {
    const tbody = document.getElementById('customersBody');
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">কোনো কাস্টমার নেই</td></tr>';
        return;
    }

    tbody.innerHTML = list.map((c, i) => `
        <tr>
            <td class="text-muted">${i + 1}</td>
            <td class="fw-semibold">${esc(c.name)}</td>
            <td>${esc(c.phone || '—')}</td>
            <td class="text-muted small">${esc(c.address || '—')}</td>
            <td class="text-end">${fmt(c.total_purchase)}</td>
            <td class="text-end">
                ${parseFloat(c.total_due) > 0
                    ? `<span class="badge bg-danger">${fmt(c.total_due)}</span>`
                    : '<span class="text-success small">পরিশোধিত</span>'}
            </td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary me-1"
                    onclick="openEditModal(${c.id}, '${jsEsc(c.name)}', '${jsEsc(c.phone || '')}', '${jsEsc(c.address || '')}')">
                    <i class="bi bi-pencil"></i>
                </button>
                ${IS_ADMIN && parseInt(c.id) !== 1 ? `
                <button class="btn btn-sm btn-outline-danger"
                    onclick="deleteCustomer(${c.id}, '${jsEsc(c.name)}')">
                    <i class="bi bi-trash"></i>
                </button>` : ''}
            </td>
        </tr>
    `).join('');
}

// ---- Live Search ----
document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#customersBody tr[id]').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    // if no id on rows, search all
    if (!document.querySelector('#customersBody tr[id]')) {
        document.querySelectorAll('#customersBody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
});

// ---- Init ----
loadCustomers();
