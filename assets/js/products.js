// ============================================
// Product Management — AJAX CRUD
// ============================================

const productModal  = new bootstrap.Modal(document.getElementById('productModal'));
const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
const form          = document.getElementById('productForm');
const formError     = document.getElementById('formError');
const modalTitle    = document.getElementById('modalTitle');
const catSelect     = document.getElementById('productCategory');
const subcatSelect  = document.getElementById('productSubcategory');
const unitSelect    = document.getElementById('productUnit');

// Local mutable copy so newly added subcategories show without a reload
let subcats = Array.isArray(SUBCATEGORIES) ? [...SUBCATEGORIES] : [];

// ── Dependent sub-category dropdown ─────────────────────────────────────────
function fillSubcatOptions(categoryId, selectedId) {
    const items = subcats.filter(s => String(s.category_id) === String(categoryId));
    let html = '<option value="">— নেই —</option>';
    items.forEach(s => {
        html += `<option value="${s.id}" ${String(selectedId) === String(s.id) ? 'selected' : ''}>${escHtml(s.name)}</option>`;
    });
    if (subcatSelect.tomselect) {
        const ts = subcatSelect.tomselect;
        ts.clear(true); ts.clearOptions();
        items.forEach(s => ts.addOption({ value: String(s.id), text: s.name }));
        ts.addOption({ value: '', text: '— নেই —' });
        ts.setValue(selectedId ? String(selectedId) : '', true);
    } else {
        subcatSelect.innerHTML = html;
    }
}

catSelect.addEventListener('change', () => fillSubcatOptions(catSelect.value, ''));

// ── Image upload ─────────────────────────────────────────────────────────────
const imageFile   = document.getElementById('imageFile');
const imageHidden = document.getElementById('productImage');
const imgPrevWrap = document.getElementById('imagePreviewWrap');
const imgPrev     = document.getElementById('imagePreview');

imageFile.addEventListener('change', async () => {
    if (!imageFile.files.length) return;
    imageFile.disabled = true;
    try {
        const data = await uploadImageFile(`${BASE}/api/upload_product_image.php`, 'image', imageFile.files[0]);
        if (data.success) {
            imageHidden.value = data.data.path;
            imgPrev.src = `${BASE}/${data.data.path}`;
            imgPrevWrap.classList.remove('d-none');
            showToast(data.message, 'success');
        } else {
            imageFile.value = '';
            showToast(data.message, 'danger');
        }
    } finally {
        imageFile.disabled = false;
    }
});

// --- Open modal for ADD ---
document.getElementById('btnAddProduct').addEventListener('click', () => {
    form.reset();
    tsSyncForm(form);
    document.getElementById('productId').value = '';
    document.getElementById('minStock').value  = '0';
    document.getElementById('wholesalePrice').value = '0';
    imageHidden.value = '';
    imgPrevWrap.classList.add('d-none');
    fillSubcatOptions('', '');
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
            fillSubcatOptions(p.category_id, p.subcategory_id || '');
            document.getElementById('productName').value = p.name;
            document.getElementById('productCode').value = p.product_code || '';
            document.getElementById('sizeBrand').value   = p.size_brand || '';
            tsSet(unitSelect, p.unit, true);
            document.getElementById('buyPrice').value       = p.buy_price;
            document.getElementById('sellPrice').value      = p.sell_price;
            document.getElementById('wholesalePrice').value = p.wholesale_price || 0;
            document.getElementById('minStock').value       = p.min_stock;
            imageHidden.value = p.image || '';
            imageFile.value   = '';
            if (p.image) {
                imgPrev.src = `${BASE}/${p.image}`;
                imgPrevWrap.classList.remove('d-none');
            } else {
                imgPrevWrap.classList.add('d-none');
            }

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

// ── QR Code ───────────────────────────────────────────────────────────────────
const qrModal   = new bootstrap.Modal(document.getElementById('qrModal'));
const qrCanvas  = document.getElementById('qrCanvas');
let   qrCurrent = { name: '', code: '' };

document.querySelectorAll('.btn-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        const code = btn.dataset.code || `P-${btn.dataset.id}`;
        qrCurrent = { name: btn.dataset.name, code };
        qrCanvas.innerHTML = '';
        new QRCode(qrCanvas, { text: code, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
        document.getElementById('qrProductName').textContent = qrCurrent.name;
        document.getElementById('qrProductCode').textContent = code;
        qrModal.show();
    });
});

document.getElementById('btnPrintQr').addEventListener('click', () => {
    const img = qrCanvas.querySelector('img') || qrCanvas.querySelector('canvas');
    if (!img) return;
    const src = img.tagName === 'IMG' ? img.src : img.toDataURL();
    const w = window.open('', '_blank', 'width=400,height=500');
    w.document.write(`
        <html><head><title>QR — ${escHtml(qrCurrent.code)}</title>
        <style>
            body{font-family:sans-serif;text-align:center;padding:24px}
            img{width:220px;height:220px}
            h3{margin:.5rem 0 .1rem} code{font-size:1.05rem}
            @media print { @page { margin: 8mm; } }
        </style></head><body>
        <img src="${src}">
        <h3>${escHtml(qrCurrent.name)}</h3>
        <code>${escHtml(qrCurrent.code)}</code>
        <script>window.onload=()=>{window.print();window.close();}<\/script>
        </body></html>`);
    w.document.close();
});

// ── Branch prices ─────────────────────────────────────────────────────────────
const bpModalEl = document.getElementById('branchPriceModal');
if (bpModalEl) {
    const bpModal = new bootstrap.Modal(bpModalEl);
    const bpBody  = document.getElementById('bpBody');
    const bpError = document.getElementById('bpError');

    document.querySelectorAll('.btn-branch-price').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            document.getElementById('bpProductId').value = id;
            document.getElementById('bpProductName').textContent = btn.dataset.name;
            bpError.classList.add('d-none');
            bpBody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
            bpModal.show();
            try {
                const res  = await fetch(`${BASE}/api/get_branch_prices.php?product_id=${id}`);
                const data = await res.json();
                if (!data.success) { bpError.textContent = data.message; bpError.classList.remove('d-none'); return; }
                bpBody.innerHTML = data.data.prices.map(r => `
                    <tr data-branch-id="${r.branch_id}">
                        <td class="fw-semibold">${escHtml(r.branch_name)}</td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm bp-buy"       value="${r.buy_price       ?? ''}" placeholder="সেন্ট্রাল"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm bp-sell"      value="${r.sell_price      ?? ''}" placeholder="সেন্ট্রাল"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm bp-wholesale" value="${r.wholesale_price ?? ''}" placeholder="সেন্ট্রাল"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm bp-minstock"  value="${r.min_stock       ?? ''}" placeholder="সেন্ট্রাল"></td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input bp-active" ${Number(r.is_active) === 1 ? 'checked' : ''}>
                        </td>
                    </tr>`).join('');
            } catch {
                bpError.textContent = 'ডাটা লোড হয়নি।';
                bpError.classList.remove('d-none');
            }
        });
    });

    document.getElementById('btnSaveBranchPrices').addEventListener('click', async () => {
        const productId = document.getElementById('bpProductId').value;
        const rows = [...bpBody.querySelectorAll('tr[data-branch-id]')].map(tr => ({
            branch_id:       tr.dataset.branchId,
            buy_price:       tr.querySelector('.bp-buy').value,
            sell_price:      tr.querySelector('.bp-sell').value,
            wholesale_price: tr.querySelector('.bp-wholesale').value,
            min_stock:       tr.querySelector('.bp-minstock').value,
            is_active:       tr.querySelector('.bp-active').checked ? 1 : 0,
        }));
        const btn = document.getElementById('btnSaveBranchPrices');
        btn.disabled = true;
        try {
            const res  = await fetch(`${BASE}/api/save_branch_prices.php`, {
                method: 'POST',
                body:   new URLSearchParams({ product_id: productId, rows: JSON.stringify(rows) })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                bpModal.hide();
            } else {
                bpError.textContent = data.message;
                bpError.classList.remove('d-none');
            }
        } catch {
            bpError.textContent = 'সংরক্ষণ করা যায়নি।';
            bpError.classList.remove('d-none');
        } finally {
            btn.disabled = false;
        }
    });
}

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

    // Also append to product modal select + subcat category select
    const opt = document.createElement('option');
    opt.value       = id;
    opt.textContent = name;
    catSelect.appendChild(opt);
    if (catSelect.tomselect) catSelect.tomselect.addOption({ value: String(id), text: name });

    const scSel = document.getElementById('newSubcatCategory');
    if (scSel) {
        const o2 = document.createElement('option');
        o2.value = id; o2.textContent = name;
        scSel.appendChild(o2);
        if (scSel.tomselect) scSel.tomselect.addOption({ value: String(id), text: name });
    }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Sub-category management ──────────────────────────────────────────────────
const subcatList  = document.getElementById('subcategoryList');
const subcatError = document.getElementById('subcatError');

document.getElementById('btnAddSubcat').addEventListener('click', async () => {
    const categoryId = document.getElementById('newSubcatCategory').value;
    const name       = document.getElementById('newSubcatName').value.trim();
    subcatError.classList.add('d-none');
    if (!categoryId) { subcatError.textContent = 'ক্যাটাগরি নির্বাচন করুন।'; subcatError.classList.remove('d-none'); return; }
    if (!name)       { subcatError.textContent = 'সাব-ক্যাটাগরির নাম লিখুন।'; subcatError.classList.remove('d-none'); return; }

    try {
        const res  = await fetch(`${BASE}/api/add_subcategory.php`, {
            method: 'POST',
            body:   new URLSearchParams({ category_id: categoryId, name })
        });
        const data = await res.json();
        if (data.success) {
            const catName = (CATEGORIES.find(c => String(c.id) === String(categoryId)) || {}).name || '';
            subcats.push({ id: data.data.id, category_id: categoryId, name });
            const noMsg = document.getElementById('noSubcatMsg');
            if (noMsg) noMsg.remove();
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
            li.dataset.subcatId = data.data.id;
            li.innerHTML = `<span>${escHtml(name)} <small class="text-muted">(${escHtml(catName)})</small></span>
                <button class="btn btn-sm btn-outline-danger btn-del-subcat" data-id="${data.data.id}">
                    <i class="bi bi-trash"></i>
                </button>`;
            subcatList.appendChild(li);
            li.querySelector('.btn-del-subcat').addEventListener('click', deleteSubcatHandler);
            document.getElementById('newSubcatName').value = '';
            showToast(data.message, 'success');
        } else {
            subcatError.textContent = data.message;
            subcatError.classList.remove('d-none');
        }
    } catch {
        subcatError.textContent = 'সার্ভারে সমস্যা হয়েছে।';
        subcatError.classList.remove('d-none');
    }
});

async function deleteSubcatHandler(e) {
    const btn = e.currentTarget;
    const id  = btn.dataset.id;
    if (!confirm('সাব-ক্যাটাগরিটি ডিলিট করতে চান?')) return;
    try {
        const res  = await fetch(`${BASE}/api/delete_subcategory.php`, {
            method: 'POST',
            body:   new URLSearchParams({ id })
        });
        const data = await res.json();
        if (data.success) {
            subcats = subcats.filter(s => String(s.id) !== String(id));
            btn.closest('li').remove();
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'danger');
        }
    } catch {
        showToast('ডিলিট করা যায়নি।', 'danger');
    }
}
document.querySelectorAll('.btn-del-subcat').forEach(btn => btn.addEventListener('click', deleteSubcatHandler));

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
