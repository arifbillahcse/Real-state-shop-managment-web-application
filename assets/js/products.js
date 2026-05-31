// ============================================
// Product Management — AJAX CRUD
// ============================================

const BASE = window.location.origin + window.location.pathname.replace(/\/pages\/.*$/, '');

const productModal = new bootstrap.Modal(document.getElementById('productModal'));
const form         = document.getElementById('productForm');
const formError    = document.getElementById('formError');
const modalTitle   = document.getElementById('modalTitle');
const typeSelect   = document.getElementById('productType');
const sizeBrandLbl = document.getElementById('sizeBrandLabel');
const sizeBrandInp = document.getElementById('sizeBrand');
const unitSelect   = document.getElementById('productUnit');

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
    tsSyncForm(form);
    document.getElementById('productId').value = '';
    document.getElementById('minStock').value  = '0';
    modalTitle.innerHTML = '<i class="bi bi-box-seam me-1 text-danger"></i> নতুন পণ্য';
    formError.classList.add('d-none');
    syncTypeFields();
    productModal.show();
});

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
            tsSet(typeSelect, p.type, true);
            document.getElementById('productName').value = p.name;
            sizeBrandInp.value                           = p.size_brand || '';
            tsSet(unitSelect, p.unit, true);
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
            setTimeout(() => location.reload(), 700);
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
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (err) {
            showToast('ডিলিট করা যায়নি।', 'danger');
        }
    });
});
