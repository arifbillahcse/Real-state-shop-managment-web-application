// ============================================
// Product Management — AJAX CRUD
//
// Adapted from the original assets/js/products.js. pages/products.php
// rendered both tables server-side, so this version fetches the rows
// from get_products.php and renders them before binding the handlers.
// The modal, validation and submit logic are unchanged.
// ============================================

const BASE = BASE_URL;

const productModal = new bootstrap.Modal(document.getElementById('productModal'));
const form         = document.getElementById('productForm');
const formError    = document.getElementById('formError');
const modalTitle   = document.getElementById('modalTitle');
const typeSelect   = document.getElementById('productType');
const sizeBrandLbl = document.getElementById('sizeBrandLabel');
const sizeBrandInp = document.getElementById('sizeBrand');
const unitSelect   = document.getElementById('productUnit');

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function money(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

// Matches rtrim(rtrim($p['min_stock'],'0'),'.') in the PHP view
function trimZeros(n) {
    return String(parseFloat(n || 0));
}

// --- Update size/brand label + default unit based on type ---
function syncTypeFields() {
    if (typeSelect.value === 'rod') {
        sizeBrandLbl.textContent   = 'সাইজ';
        sizeBrandInp.placeholder   = 'যেমন: 12mm';
    } else {
        sizeBrandLbl.textContent   = 'ব্র্যান্ড';
        sizeBrandInp.placeholder   = 'যেমন: LAFARGE';
    }
}
typeSelect.addEventListener('change', syncTypeFields);

// --- Open modal for ADD ---
document.getElementById('btnAddProduct').addEventListener('click', () => {
    form.reset();
    document.getElementById('productId').value = '';
    document.getElementById('minStock').value  = '0';
    modalTitle.innerHTML = '<i class="bi bi-box-seam me-1 text-danger"></i> নতুন পণ্য';
    formError.classList.add('d-none');
    syncTypeFields();
    productModal.show();
});

// --- Load & render both tables ---
function loadProducts() {
    fetch(`${BASE}/api/get_products.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.message, 'danger'); return; }
            const all = res.products || [];
            renderTable('rod',    all.filter(p => p.type === 'rod'));
            renderTable('cement', all.filter(p => p.type === 'cement'));
            bindRowButtons();
        })
        .catch(() => showToast('ডাটা লোড হয়নি।', 'danger'));
}

function renderTable(type, items) {
    const tbody = document.getElementById(type + 'Body');
    const count = document.getElementById(type + 'Count');
    if (count) count.textContent = items.length;

    if (!items.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
            কোন পণ্য নেই। “নতুন পণ্য” বাটনে ক্লিক করুন।
        </td></tr>`;
        return;
    }

    tbody.innerHTML = items.map(p => `
        <tr>
            <td class="fw-semibold">${esc(p.name)}</td>
            <td><span class="badge bg-light text-dark border">${esc(p.size_brand)}</span></td>
            <td>${esc(p.unit)}</td>
            <td class="text-end">${money(p.buy_price)}</td>
            <td class="text-end">${money(p.sell_price)}</td>
            <td class="text-end">${trimZeros(p.min_stock)} ${esc(p.unit)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary btn-edit"
                        data-id="${p.id}" title="সম্পাদনা">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="${p.id}" data-name="${esc(p.name)}" title="ডিলিট">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`).join('');
}

function bindRowButtons() {
    // --- Open modal for EDIT ---
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            formError.classList.add('d-none');
            try {
                const res  = await fetch(`${BASE}/api/get_products.php?id=${id}`);
                const data = await res.json();
                if (!data.success) { showToast(data.message, 'danger'); return; }

                const p = data.product;
                document.getElementById('productId').value   = p.id;
                typeSelect.value                             = p.type;
                document.getElementById('productName').value = p.name;
                sizeBrandInp.value                           = p.size_brand || '';
                unitSelect.value                             = p.unit;
                document.getElementById('buyPrice').value    = p.buy_price;
                document.getElementById('sellPrice').value   = p.sell_price;
                document.getElementById('minStock').value    = p.min_stock;

                modalTitle.innerHTML = '<i class="bi bi-pencil me-1 text-danger"></i> পণ্য সম্পাদনা';
                syncTypeFields();
                productModal.show();
            } catch (err) {
                showToast('ডাটা লোড হয়নি।', 'danger');
            }
        });
    });

    // --- Delete (with confirmation) ---
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id   = btn.dataset.id;
            const name = btn.dataset.name;
            if (!confirm(`“${name}” পণ্যটি কি ডিলিট করতে চান?`)) return;

            try {
                const res  = await fetch(`${BASE}/api/delete_product.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:   new URLSearchParams({ id })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    loadProducts();
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (err) {
                showToast('ডিলিট করা যায়নি।', 'danger');
            }
        });
    });
}

// --- Submit (Add or Update) ---
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.classList.add('d-none');

    const id     = document.getElementById('productId').value;
    const url    = id ? `${BASE}/api/update_product.php` : `${BASE}/api/add_product.php`;
    const btn    = document.getElementById('btnSave');
    const origin = btn.innerHTML;

    // Client-side validation
    const buy  = parseFloat(document.getElementById('buyPrice').value);
    const sell = parseFloat(document.getElementById('sellPrice').value);
    if (buy <= 0 || sell <= 0) {
        formError.textContent = 'ক্রয় ও বিক্রয় দাম ০ এর বেশি হতে হবে।';
        formError.classList.remove('d-none');
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> সেভ হচ্ছে...';

    try {
        const res  = await fetch(url, {
            method: 'POST',
            body:   new FormData(form)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            productModal.hide();
            loadProducts();
        } else {
            formError.textContent = data.message;
            formError.classList.remove('d-none');
        }
    } catch (err) {
        formError.textContent = 'সার্ভারে সমস্যা হয়েছে।';
        formError.classList.remove('d-none');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = origin;
    }
});

loadProducts();
