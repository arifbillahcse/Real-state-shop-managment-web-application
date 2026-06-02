// Sidebar toggle
const sidebar       = document.getElementById('sidebar');
const mainContent   = document.getElementById('mainContent');
const sidebarToggle = document.getElementById('sidebarToggle');

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('expanded');
        }
    });
}

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert-auto').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
});

// Global AJAX helper
function ajaxPost(url, data, callback) {
    fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(callback)
    .catch(err => console.error('AJAX error:', err));
}

// ============================================
// Searchable dropdowns (Tom Select)
// ============================================

// Initialize a single <select> as a searchable Tom Select.
function initTomSelect(el) {
    if (!el || el.tomselect) return;                 // already done
    if (el.dataset.noSearch === '1') return;         // opt-out hook
    if (typeof TomSelect === 'undefined') return;    // library not loaded

    // Use the empty/placeholder option text as the placeholder
    let placeholder = el.getAttribute('placeholder') || '';
    const emptyOpt  = el.querySelector('option[value=""]');
    if (!placeholder && emptyOpt) placeholder = emptyOpt.textContent.trim();

    new TomSelect(el, {
        create: false,
        allowEmptyOption: true,
        maxOptions: 1000,
        dropdownParent: 'body',
        placeholder: placeholder || 'খুঁজুন...',
        sortField: [{ field: '$order' }, { field: '$score' }],
        onDropdownOpen: function() {
            requestAnimationFrame(() => this.refreshOptions(false));
        },
    });
}

// Initialize every <select> under a root element.
function initAllTomSelects(root = document) {
    root.querySelectorAll('select').forEach(initTomSelect);
}

// Set a select's value and update the Tom Select widget display.
// silent = true skips firing the 'change' event.
function tsSet(el, val, silent = false) {
    if (typeof el === 'string') el = document.getElementById(el);
    if (!el) return;
    val = (val ?? '') + '';
    if (el.tomselect) el.tomselect.setValue(val, silent);
    else el.value = val;
}

// After form.reset(), re-sync all Tom Select widgets in the form to the
// underlying select values (reset changes the <select> but not the widget).
function tsSyncForm(form) {
    if (typeof form === 'string') form = document.getElementById(form);
    if (!form) return;
    form.querySelectorAll('select').forEach(s => {
        if (s.tomselect) s.tomselect.setValue(s.value || '', true);
    });
}

// Replace a select's <option> list (new HTML) and keep it searchable.
function tsRebuild(el, html, val) {
    if (!el) return;
    const hadTs = !!el.tomselect;
    if (hadTs) el.tomselect.destroy();
    el.innerHTML = html;
    if (val !== undefined && val !== null) el.value = val;
    if (hadTs) initTomSelect(el);
}

document.addEventListener('DOMContentLoaded', () => {
    initAllTomSelects();
    // Auto-upgrade any <select> added later (e.g. dynamic sales item rows)
    const obs = new MutationObserver(muts => {
        muts.forEach(m => m.addedNodes.forEach(node => {
            if (node.nodeType !== 1) return;
            if (node.tagName === 'SELECT') initTomSelect(node);
            else if (node.querySelectorAll) node.querySelectorAll('select').forEach(initTomSelect);
        }));
    });
    obs.observe(document.body, { childList: true, subtree: true });
});

// ── Shared table paginator ────────────────────────────────────────────────────
// Usage: paginateTable('tbodyId', 50)  or  paginateTable(tbodyEl, 50)
// Dynamically inserts a pagination bar after the closest .table-responsive.
function paginateTable(tbodyIdOrEl, pageSize) {
    const tbody = typeof tbodyIdOrEl === 'string'
        ? document.getElementById(tbodyIdOrEl)
        : tbodyIdOrEl;
    if (!tbody) return;

    const allRows = Array.from(tbody.querySelectorAll('tr'));
    if (!allRows.length || allRows.length <= pageSize) return;

    // Find or create the pagination bar
    const tableResponsive = tbody.closest('.table-responsive') || tbody.closest('table').parentElement;
    let bar = tableResponsive.nextElementSibling;
    if (!bar || !bar.classList.contains('pg-bar')) {
        bar = document.createElement('div');
        bar.className = 'pg-bar d-flex justify-content-between align-items-center px-3 py-2 border-top';
        tableResponsive.insertAdjacentElement('afterend', bar);
    }

    let currentPage = 1;

    function go(page) {
        currentPage = page;
        const total      = allRows.length;
        const totalPages = Math.ceil(total / pageSize);
        const start      = (page - 1) * pageSize;

        allRows.forEach((tr, i) => {
            tr.style.display = (i >= start && i < start + pageSize) ? '' : 'none';
        });

        const from = start + 1;
        const to   = Math.min(start + pageSize, total);

        let html = `<small class="text-muted">${total} টির মধ্যে ${from}–${to}</small><nav><ul class="pagination pagination-sm mb-0">`;
        html += `<li class="page-item ${page===1?'disabled':''}"><a class="page-link pg-prev" href="#">&#8249;</a></li>`;
        for (let i = 1; i <= totalPages; i++) {
            if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
                if (i === 3 || i === totalPages - 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
                continue;
            }
            html += `<li class="page-item ${i===page?'active':''}"><a class="page-link pg-num" href="#" data-p="${i}">${i}</a></li>`;
        }
        html += `<li class="page-item ${page===totalPages?'disabled':''}"><a class="page-link pg-next" href="#">&#8250;</a></li>`;
        html += `</ul></nav>`;
        bar.innerHTML = html;

        bar.querySelector('.pg-prev')?.addEventListener('click', e => { e.preventDefault(); if (currentPage > 1) go(currentPage - 1); });
        bar.querySelector('.pg-next')?.addEventListener('click', e => { e.preventDefault(); if (currentPage < totalPages) go(currentPage + 1); });
        bar.querySelectorAll('.pg-num').forEach(a => a.addEventListener('click', e => { e.preventDefault(); go(parseInt(a.dataset.p)); }));
    }

    go(1);
}

// Toast notification
function showToast(message, type = 'success') {
    const bg   = { success: '#198754', danger: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
    const fg   = { success: '#fff',    danger: '#fff',    warning: '#212529', info: '#212529' };
    const toast = document.createElement('div');
    toast.style.cssText = `
        position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
        background:${bg[type] || bg.success}; color:${fg[type] || '#fff'};
        padding:.75rem 1.25rem; border-radius:10px;
        box-shadow:0 4px 16px rgba(0,0,0,.25); font-size:.9rem;
        animation: slideIn .3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .5s'; }, 3000);
    setTimeout(() => toast.remove(), 3500);
}
