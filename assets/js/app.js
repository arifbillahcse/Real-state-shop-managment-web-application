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

// ── Count-up animation for stat numbers ───────────────────────────────────────
// Any element with data-countup="<number>" animates 0 → number on load.
// Optional data-suffix appends a unit (e.g. " ৳").
function runCountUps() {
    document.querySelectorAll('[data-countup]').forEach(el => {
        const target = parseFloat(el.getAttribute('data-countup')) || 0;
        const suffix = el.getAttribute('data-suffix') || '';
        const dur    = 950;
        const start  = performance.now();
        function tick(now) {
            const p     = Math.min((now - start) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);          // easeOutCubic
            const val   = target * eased;
            el.textContent = val.toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            }) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });
}
document.addEventListener('DOMContentLoaded', runCountUps);

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
