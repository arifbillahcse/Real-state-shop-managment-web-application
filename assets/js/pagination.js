'use strict';

/**
 * Lightweight client-side table pagination.
 *
 * Splits a <tbody>'s data rows into pages and renders a navigation bar
 * just below the table. Re-callable: invoking it again rebuilds the nav
 * (useful after AJAX-rendered tables refresh their rows).
 *
 * Placeholder / empty-state rows (a single row) are left untouched because
 * the row count stays at or below the page size, so no nav is shown.
 *
 * @param {HTMLElement|string} tbody     tbody element or its id
 * @param {number}             pageSize  rows per page (default 50)
 */
function paginateTable(tbody, pageSize = 50) {
    if (typeof tbody === 'string') tbody = document.getElementById(tbody);
    if (!tbody) return;

    const table = tbody.closest('table');
    if (!table) return;

    // Anchor for the nav: prefer the scroll wrapper so the bar sits outside it.
    const anchor = table.closest('.table-responsive') || table;

    // Remove any nav we created earlier for this table.
    const host = anchor.parentElement || anchor;
    const oldNav = host.querySelector(':scope > .table-pagination');
    if (oldNav) oldNav.remove();

    const rows = Array.from(tbody.children)
        .filter(tr => tr.tagName === 'TR');

    // Nothing to paginate.
    if (rows.length <= pageSize) {
        rows.forEach(r => { r.style.display = ''; });
        return;
    }

    const totalPages = Math.ceil(rows.length / pageSize);
    let current = 1;

    const nav = document.createElement('div');
    nav.className = 'table-pagination d-flex justify-content-between align-items-center '
                 + 'px-3 py-2 border-top flex-wrap gap-2';
    anchor.insertAdjacentElement('afterend', nav);

    function bn(n) {
        return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]);
    }

    function show(page) {
        current = Math.min(Math.max(1, page), totalPages);
        const start = (current - 1) * pageSize;
        const end   = start + pageSize;
        rows.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });
        render();
    }

    function pageBtn(label, page, opts = {}) {
        const li = document.createElement('li');
        li.className = 'page-item' + (opts.active ? ' active' : '') + (opts.disabled ? ' disabled' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.innerHTML = label;
        a.addEventListener('click', e => {
            e.preventDefault();
            if (!opts.disabled && !opts.active) show(page);
        });
        li.appendChild(a);
        return li;
    }

    function render() {
        const from = (current - 1) * pageSize + 1;
        const to   = Math.min(current * pageSize, rows.length);

        const info = `<span class="text-muted small">${bn(from)}–${bn(to)} / ${bn(rows.length)} টি</span>`;

        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';

        ul.appendChild(pageBtn('<i class="bi bi-chevron-left"></i>', current - 1, { disabled: current === 1 }));

        // Windowed page numbers: first, last, and a few around current.
        const pages = new Set([1, totalPages, current, current - 1, current + 1]);
        const sorted = [...pages].filter(p => p >= 1 && p <= totalPages).sort((a, b) => a - b);
        let prev = 0;
        sorted.forEach(p => {
            if (p - prev > 1) {
                const gap = document.createElement('li');
                gap.className = 'page-item disabled';
                gap.innerHTML = '<span class="page-link">…</span>';
                ul.appendChild(gap);
            }
            ul.appendChild(pageBtn(bn(p), p, { active: p === current }));
            prev = p;
        });

        ul.appendChild(pageBtn('<i class="bi bi-chevron-right"></i>', current + 1, { disabled: current === totalPages }));

        nav.innerHTML = info;
        nav.appendChild(ul);
    }

    show(1);
}
