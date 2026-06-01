// ============================================
// Product Management — AJAX CRUD
// ============================================

const productModal  = new bootstrap.Modal(document.getElementById('productModal'));
const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
const form          = document.getElementById('productForm');
const formError     = document.getElementById('formError');
const modalTitle    = document.getElementById('modalTitle');
const catSelect     = document.getElementById('productCategory');
const unitSelect    = document.getElementById('productUnit');

// --- Open modal for ADD ---
document.getElementById('btnAddProduct').addEventListener('click', () => {
    form.reset();
    tsSyncForm(form);
    document.getElementById('productId').value = '';
    document.getElementById('minStock').value  = '0';
    modalTitle.innerHTML = '<i class="bi bi-box-seam me-1 text-danger"></i> নতুন পণ্য';
    formError.classList.add('d-none');
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
            tsSet(catSelect,  String(p.category_id), true);
            document.getElementById('productName').value = p.name;
            document.getElementById('sizeBrand').value   = p.size_brand || '';
            tsSet(unitSelect, p.unit, true);
            document.getElementById('buyPrice').value    = p.buy_price;
            document.getElementById('sellPrice').value   = p.sell_price;
            document.getElementById('minStock').value    = p.min_stock;

            modalTitle.innerHTML = '<i class="bi bi-pencil me-1 text-danger"></i> পণ্য সম্পাদনা';
            productModal.show();
        } catch {
            showToast('ডাটা লোড হয়নি।', 'danger');
        }
    });
});

// --- Submit (Add or Update) ---
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.classList.add('d-none');

    const id  = document.getElementById('productId').value;
    const url = id ? `${BASE}/api/update_product.php` : `${BASE}/api/add_product.php`;
    const btn = document.getElementById('btnSave');
    const orig = btn.innerHTML;

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
        const res  = await fetch(url, { method: 'POST', body: new FormData(form) });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            productModal.hide();
            setTimeout(() => location.reload(), 700);
        } else {
            formError.textContent = data.message;
            formError.classList.remove('d-none');
        }
    } catch {
        formError.textContent = 'সার্ভারে সমস্যা হয়েছে।';
        formError.classList.remove('d-none');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
});

// --- Delete product ---
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id   = btn.dataset.id;
        const name = btn.dataset.name;
        if (!confirm(`"${name}" পণ্যটি কি ডিলিট করতে চান?`)) return;

        try {
            const res  = await fetch(`${BASE}/api/delete_product.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({ id })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(data.message, 'danger');
            }
        } catch {
            showToast('ডিলিট করা যায়নি।', 'danger');
        }
    });
});

// ── Category Management ────────────────────────────────────────────────────────

const catError   = document.getElementById('catError');
const catList    = document.getElementById('categoryList');
const catInput   = document.getElementById('newCategoryName');

document.getElementById('btnAddCategory').addEventListener('click', async () => {
    const name = catInput.value.trim();
    if (!name) { catError.textContent = 'ক্যাটাগরির নাম লিখুন।'; catError.classList.remove('d-none'); return; }
    catError.classList.add('d-none');

    try {
        const res  = await fetch(`${BASE}/api/add_category.php`, {
            method: 'POST',
            body:   new URLSearchParams({ name })
        });
        const data = await res.json();
        if (data.success) {
            catInput.value = '';
            showToast(data.message, 'success');
            appendCategoryRow(data.data.id, name);
        } else {
            catError.textContent = data.message;
            catError.classList.remove('d-none');
        }
    } catch {
        catError.textContent = 'সার্ভারে সমস্যা হয়েছে।';
        catError.classList.remove('d-none');
    }
});

catInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnAddCategory').click(); }
});

function appendCategoryRow(id, name) {
    const noCatMsg = document.getElementById('noCatMsg');
    if (noCatMsg) noCatMsg.remove();

    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
    li.innerHTML = `<span>${escHtml(name)}</span>
        <button class="btn btn-sm btn-outline-danger btn-del-cat"
                data-id="${id}" data-name="${escHtml(name)}">
            <i class="bi bi-trash"></i>
        </button>`;
    catList.appendChild(li);
    li.querySelector('.btn-del-cat').addEventListener('click', deleteCategoryHandler);

    // Also append to product modal select
    const opt = document.createElement('option');
    opt.value       = id;
    opt.textContent = name;
    catSelect.appendChild(opt);
    if (catSelect.tomselect) catSelect.tomselect.addOption({ value: String(id), text: name });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Typed-confirmation delete: user must type the exact category name.
const delCatModal   = new bootstrap.Modal(document.getElementById('delCatModal'));
const delCatInput   = document.getElementById('delCatConfirmInput');
const delCatError   = document.getElementById('delCatError');
const btnConfirmDel = document.getElementById('btnConfirmDelCat');
let   delCatTarget  = { id: null, name: '', li: null };

function deleteCategoryHandler(e) {
    const btn = e.currentTarget;
    delCatTarget = { id: btn.dataset.id, name: btn.dataset.name, li: btn.closest('li') };

    document.getElementById('delCatName').textContent = delCatTarget.name;
    document.getElementById('delCatId').value          = delCatTarget.id;
    delCatInput.value = '';
    delCatError.classList.add('d-none');
    btnConfirmDel.disabled = true;

    // Bootstrap 5 doesn't support stacked modals — close the parent first.
    const catModalEl   = document.getElementById('categoryModal');
    const catModalInst = bootstrap.Modal.getInstance(catModalEl);
    if (catModalInst) {
        catModalEl.addEventListener('hidden.bs.modal', () => {
            delCatModal.show();
            setTimeout(() => delCatInput.focus(), 300);
        }, { once: true });
        catModalInst.hide();
    } else {
        delCatModal.show();
        setTimeout(() => delCatInput.focus(), 300);
    }
}

// Enable the confirm button only when the typed name matches exactly.
delCatInput.addEventListener('input', () => {
    btnConfirmDel.disabled = delCatInput.value.trim() !== delCatTarget.name;
});
delCatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !btnConfirmDel.disabled) { e.preventDefault(); btnConfirmDel.click(); }
});

btnConfirmDel.addEventListener('click', async () => {
    if (delCatInput.value.trim() !== delCatTarget.name) {
        delCatError.textContent = 'নাম মিলছে না। হুবহু একই নাম লিখুন।';
        delCatError.classList.remove('d-none');
        return;
    }
    const { id, li } = delCatTarget;
    btnConfirmDel.disabled = true;

    try {
        const res  = await fetch(`${BASE}/api/delete_category.php`, {
            method: 'POST',
            body:   new URLSearchParams({ id })
        });
        const data = await res.json();
        if (data.success) {
            if (li) li.remove();
            showToast(data.message, 'success');
            const opt = catSelect.querySelector(`option[value="${id}"]`);
            if (opt) opt.remove();
            if (catSelect.tomselect) catSelect.tomselect.removeOption(String(id));
            delCatModal.hide();
        } else {
            delCatError.textContent = data.message;
            delCatError.classList.remove('d-none');
            btnConfirmDel.disabled = false;
        }
    } catch {
        delCatError.textContent = 'ডিলিট করা যায়নি।';
        delCatError.classList.remove('d-none');
        btnConfirmDel.disabled = false;
    }
});

document.querySelectorAll('.btn-del-cat').forEach(btn => {
    btn.addEventListener('click', deleteCategoryHandler);
});

