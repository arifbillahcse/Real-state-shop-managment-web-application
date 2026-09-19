'use strict';

let expenseModal = null;
let expensesCache = {};

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    expenseModal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
    loadExpenses();

    document.getElementById('profitTabBtn')?.addEventListener('shown.bs.tab', () => {
        loadProfitLoss();
    });
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(v) {
    return parseFloat(v || 0).toLocaleString('bn-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ৳';
}

function showErr(el, msg) {
    el.textContent = msg;
    el.classList.remove('d-none');
}

const EXP_PAGE_SIZE = 50;
let _allExpenses    = [];
let _expPage        = 1;

function buildExpPageNav(page) {
    const total      = _allExpenses.length;
    const totalPages = Math.ceil(total / EXP_PAGE_SIZE);
    const from       = (page - 1) * EXP_PAGE_SIZE + 1;
    const to         = Math.min(page * EXP_PAGE_SIZE, total);
    const bar        = document.getElementById('expPaginationBar');
    document.getElementById('expPageInfo').textContent = `${total} টির মধ্যে ${from}–${to} দেখাচ্ছে`;
    if (totalPages <= 1) { bar.style.display = 'none'; return; }
    bar.style.removeProperty('display');
    let html = `<li class="page-item ${page===1?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderExpPage(${page-1})">&#8249;</a></li>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages-1 && Math.abs(i-page) > 1) {
            if (i === 3 || i === totalPages-2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        html += `<li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderExpPage(${i})">${i}</a></li>`;
    }
    html += `<li class="page-item ${page===totalPages?'disabled':''}"><a class="page-link" href="#" onclick="event.preventDefault();renderExpPage(${page+1})">&#8250;</a></li>`;
    document.getElementById('expPagination').innerHTML = html;
}

function renderExpPage(page) {
    _expPage = page;
    const cols     = HAS_BRANCHES ? 6 : 5;
    const tbody    = document.getElementById('expenseBody');
    const start    = (page - 1) * EXP_PAGE_SIZE;
    const pageData = _allExpenses.slice(start, start + EXP_PAGE_SIZE);

    tbody.innerHTML = pageData.map(r => {
        const branchCell = HAS_BRANCHES
            ? `<td>${r.branch_name ? `<span class="badge bg-secondary">${r.branch_name}</span>` : '<span class="text-muted">—</span>'}</td>`
            : '';
        const actions = CAN_WRITE ? `
            <button class="btn btn-sm btn-outline-warning me-1" onclick="openEditExpense(${r.id})" title="সম্পাদনা">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(${r.id})" title="ডিলিট">
                <i class="bi bi-trash"></i>
            </button>` : '';
        return `<tr>
            <td>${r.expense_date}</td>
            <td>${r.category_name
                ? `<span class="badge bg-light text-dark border"><i class="bi ${r.category_icon} me-1"></i>${r.category_name}</span>`
                : '<span class="text-muted">—</span>'}</td>
            ${branchCell}
            <td class="text-muted small">${r.description || '—'}</td>
            <td class="text-end fw-semibold text-danger">${fmtMoney(r.amount)}</td>
            <td class="text-center text-nowrap">${actions}</td>
        </tr>`;
    }).join('');

    buildExpPageNav(page);
}

// ── Load Expenses ─────────────────────────────────────────────────────────────
async function loadExpenses() {
    const from   = document.getElementById('eFrom')?.value   || '';
    const to     = document.getElementById('eTo')?.value     || '';
    const cat    = document.getElementById('eCat')?.value    || '';
    const branch = document.getElementById('eBranch')?.value || '';

    const tbody = document.getElementById('expenseBody');
    const cols  = HAS_BRANCHES ? 6 : 5;
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>লোড হচ্ছে...</td></tr>`;
    document.getElementById('expPaginationBar').style.display = 'none';

    let url = `${BASE_URL}/api/get_expenses.php?from=${from}&to=${to}`;
    if (cat)    url += `&category_id=${cat}`;
    if (branch) url += `&branch_id=${branch}`;

    try {
        const res = await fetch(url).then(r => r.json());
        if (!res.success) { tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4 text-danger">${res.message}</td></tr>`; return; }

        const rows = res.data || [];
        expensesCache = {};
        rows.forEach(r => expensesCache[r.id] = r);

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>কোনো খরচ নেই</td></tr>`;
            return;
        }

        _allExpenses = rows;
        _expPage     = 1;
        renderExpPage(1);
    } catch {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4 text-danger">ডাটা লোড হয়নি।</td></tr>`;
    }
}

// ── Add / Edit Expense ────────────────────────────────────────────────────────
function openAddExpense() {
    document.getElementById('expEditId').value   = '';
    document.getElementById('expAmount').value   = '';
    document.getElementById('expDate').value     = new Date().toISOString().slice(0, 10);
    document.getElementById('expDesc').value     = '';
    document.getElementById('expCategory').value = '';
    if (document.getElementById('expBranch')) document.getElementById('expBranch').value = '';
    document.getElementById('expError').classList.add('d-none');
    document.getElementById('expModalTitle').innerHTML = '<i class="bi bi-cash-stack me-2"></i>নতুন খরচ';
    expenseModal.show();
}

function openEditExpense(id) {
    const r = expensesCache[id];
    if (!r) return;
    document.getElementById('expEditId').value   = r.id;
    document.getElementById('expAmount').value   = parseFloat(r.amount);
    document.getElementById('expDate').value     = r.expense_date;
    document.getElementById('expDesc').value     = r.description || '';
    document.getElementById('expCategory').value = r.category_id || '';
    if (document.getElementById('expBranch')) document.getElementById('expBranch').value = r.branch_id || '';
    document.getElementById('expError').classList.add('d-none');
    document.getElementById('expModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>খরচ সম্পাদনা';
    expenseModal.show();
}

async function saveExpense() {
    const errEl  = document.getElementById('expError');
    errEl.classList.add('d-none');

    const id     = document.getElementById('expEditId').value;
    const amount = parseFloat(document.getElementById('expAmount').value) || 0;
    const date   = document.getElementById('expDate').value;
    const cat    = document.getElementById('expCategory').value;
    const branch = document.getElementById('expBranch')?.value || '';
    const desc   = document.getElementById('expDesc').value;

    if (amount <= 0) { showErr(errEl, 'সঠিক পরিমাণ দিন।'); return; }
    if (!date)       { showErr(errEl, 'তারিখ দিন।'); return; }

    const btn = document.getElementById('btnSaveExpense');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';

    const payload = { amount, expense_date: date, category_id: cat, branch_id: branch, description: desc };
    if (id) payload.id = id;

    const url = id
        ? `${BASE_URL}/api/update_expense.php`
        : `${BASE_URL}/api/add_expense.php`;

    try {
        const data = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload),
        }).then(r => r.json());

        if (data.success) {
            showToast(data.message, 'success');
            expenseModal.hide();
            loadExpenses();
        } else {
            showErr(errEl, data.message);
        }
    } catch {
        showErr(errEl, 'সার্ভার এরর।');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>সংরক্ষণ করুন';
    }
}

async function deleteExpense(id) {
    if (!confirm('এই খরচ রেকর্ডটি ডিলিট করবেন?')) return;
    try {
        const data = await fetch(`${BASE_URL}/api/delete_expense.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id }),
        }).then(r => r.json());
        if (data.success) { showToast(data.message, 'success'); loadExpenses(); }
        else showToast(data.message, 'danger');
    } catch { showToast('সার্ভার এরর।', 'danger'); }
}

// ── Profit / Loss ─────────────────────────────────────────────────────────────
async function loadProfitLoss() {
    const from    = document.getElementById('pFrom')?.value || '';
    const to      = document.getElementById('pTo')?.value   || '';
    const content = document.getElementById('plContent');
    content.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>';

    try {
        const data = await fetch(`${BASE_URL}/api/get_profit_loss.php?from=${from}&to=${to}`).then(r => r.json());
        if (!data.success) { content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; return; }

        const d          = data;
        const profitCls  = d.net_profit >= 0 ? 'text-success' : 'text-danger';
        const profitIcon = d.net_profit >= 0 ? 'bi-graph-up-arrow text-success' : 'bi-graph-down-arrow text-danger';

        const catRows = (d.category_breakdown || []).map(c =>
            `<tr>
                <td><i class="bi ${c.icon} me-2 text-secondary"></i>${c.category_name}</td>
                <td class="text-end text-danger">${fmtMoney(c.total)}</td>
            </tr>`
        ).join('') || `<tr><td colspan="2" class="text-center text-muted py-3">এই সময়ে কোনো খরচ নেই</td></tr>`;

        content.innerHTML = `
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-5 text-muted mb-1">মোট বিক্রয়</div>
                    <div class="fs-4 fw-bold text-primary">${fmtMoney(d.revenue)}</div>
                    <div class="small text-muted">${d.sale_count} টি বিক্রয়</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-5 text-muted mb-1">মোট খরচ</div>
                    <div class="fs-4 fw-bold text-danger">${fmtMoney(d.total_expenses)}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-5 text-muted mb-1">বাকি আদায়</div>
                    <div class="fs-4 fw-bold text-warning">${fmtMoney(d.due)}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success border shadow-sm text-center py-3">
                    <div class="fs-5 text-muted mb-1">নিট লাভ/ক্ষতি</div>
                    <div class="fs-3 fw-bold ${profitCls}">${fmtMoney(d.net_profit)}</div>
                    <i class="bi ${profitIcon} fs-5"></i>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-pie-chart me-1"></i>ক্যাটাগরি অনুযায়ী খরচ
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr><th>ক্যাটাগরি</th><th class="text-end">মোট</th></tr>
                            </thead>
                            <tbody>${catRows}</tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-calculator me-1"></i>সারাংশ
                    </div>
                    <div class="card-body">
                        <table class="table mb-0">
                            <tr>
                                <td class="text-muted">মোট বিক্রয় আয়</td>
                                <td class="text-end fw-semibold text-primary">${fmtMoney(d.revenue)}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">পরিশোধিত</td>
                                <td class="text-end text-success">${fmtMoney(d.paid)}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">বাকি</td>
                                <td class="text-end text-warning">${fmtMoney(d.due)}</td>
                            </tr>
                            <tr class="table-light">
                                <td class="text-muted">মোট পরিচালন খরচ</td>
                                <td class="text-end text-danger">− ${fmtMoney(d.total_expenses)}</td>
                            </tr>
                            <tr class="fw-bold fs-5">
                                <td>নিট লাভ/ক্ষতি</td>
                                <td class="text-end ${profitCls}">${fmtMoney(d.net_profit)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>`;
    } catch {
        content.innerHTML = '<div class="alert alert-danger">ডাটা লোড হয়নি।</div>';
    }
}

// ── Categories ────────────────────────────────────────────────────────────────
async function addCategory() {
    const name = document.getElementById('newCatName').value.trim();
    const icon = document.getElementById('newCatIcon').value;
    if (!name) { showToast('নাম দিন।', 'warning'); return; }

    try {
        const data = await fetch(`${BASE_URL}/api/add_expense_category.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ name, icon }),
        }).then(r => r.json());

        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('newCatName').value = '';
            const tbody = document.getElementById('catBody');
            const tr = document.createElement('tr');
            tr.id = `catrow${data.id}`;
            tr.innerHTML = `
                <td><i class="bi ${icon} me-2 text-secondary"></i>${name}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger"
                            onclick="deleteCategory(${data.id}, '${name}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>`;
            tbody.appendChild(tr);

            // also add to expense modal select
            const catSel = document.getElementById('expCategory');
            const eCatSel = document.getElementById('eCat');
            if (catSel) catSel.innerHTML += `<option value="${data.id}">${name}</option>`;
            if (eCatSel) eCatSel.innerHTML += `<option value="${data.id}">${name}</option>`;
        } else {
            showToast(data.message, 'danger');
        }
    } catch { showToast('সার্ভার এরর।', 'danger'); }
}

async function deleteCategory(id, name) {
    if (!confirm(`"${name}" ক্যাটাগরিটি ডিলিট করবেন? এই ক্যাটাগরির খরচগুলো অশ্রেণীভুক্ত হয়ে যাবে।`)) return;
    try {
        const data = await fetch(`${BASE_URL}/api/delete_expense_category.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id }),
        }).then(r => r.json());

        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById(`catrow${id}`)?.remove();
        } else {
            showToast(data.message, 'danger');
        }
    } catch { showToast('সার্ভার এরর।', 'danger'); }
}

// Wire the "নতুন খরচ" button on modal hide reset
document.getElementById('addExpenseModal')?.addEventListener('hidden.bs.modal', () => {
    document.getElementById('expEditId').value = '';
    document.getElementById('expError').classList.add('d-none');
});
