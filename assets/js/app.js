// Sidebar toggle
const sidebar       = document.getElementById('sidebar');
const mainContent   = document.getElementById('mainContent');
const sidebarToggle = document.getElementById('sidebarToggle');

// Mobile backdrop overlay
const backdrop = document.createElement('div');
backdrop.id = 'sidebarBackdrop';
backdrop.style.cssText = 'display:none;position:fixed;inset:0;z-index:1015;background:rgba(0,0,0,.45)';
document.body.appendChild(backdrop);

function closeMobileSidebar() {
    if (sidebar) sidebar.classList.remove('mobile-open');
    backdrop.style.display = 'none';
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            const opening = !sidebar.classList.contains('mobile-open');
            sidebar.classList.toggle('mobile-open');
            backdrop.style.display = opening ? 'block' : 'none';
        } else {
            sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('expanded');
        }
    });
}

backdrop.addEventListener('click', closeMobileSidebar);

// Close sidebar on nav link tap (mobile)
if (sidebar) {
    sidebar.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth <= 768) closeMobileSidebar();
        });
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

// ── Dark / Light theme toggle ─────────────────────────────────────────────────
(function () {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    function syncIcon() {
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        toggle.innerHTML = dark
            ? '<i class="bi bi-sun-fill"></i>'
            : '<i class="bi bi-moon-stars"></i>';
        toggle.title = dark ? 'লাইট মোড' : 'ডার্ক মোড';
    }

    syncIcon();
    toggle.addEventListener('click', () => {
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (dark) {
            document.documentElement.removeAttribute('data-bs-theme');
            try { localStorage.setItem('theme', 'light'); } catch (e) {}
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            try { localStorage.setItem('theme', 'dark'); } catch (e) {}
        }
        syncIcon();
    });
})();

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

// ── Shared image upload helper ────────────────────────────────────────────────
// Validates type/size on the client, then reads the response defensively so a
// non-JSON reply (e.g. a server 413 "too large" or 500 page that never reached
// PHP) produces a clear, specific message instead of a generic failure.
// Returns { success: bool, message: string, path?: string }.
async function uploadImageFile(url, fieldName, file) {
    const okTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (file && file.type && !okTypes.includes(file.type)) {
        return { success: false, message: 'শুধু JPG / PNG / WebP ছবি দেওয়া যাবে।' };
    }
    if (file && file.size > 3 * 1024 * 1024) {
        return { success: false, message: 'ছবির সাইজ সর্বোচ্চ ৩ MB। ছোট ছবি দিন।' };
    }

    const fd = new FormData();
    fd.append(fieldName, file);

    let res;
    try {
        res = await fetch(url, { method: 'POST', body: fd });
    } catch (e) {
        return { success: false, message: 'নেটওয়ার্ক সমস্যা — সার্ভারে পৌঁছানো যায়নি।' };
    }

    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        // Response was not JSON → the request likely failed at the web-server
        // level before/around PHP. Surface the real reason by HTTP status.
        if (res.status === 413) {
            return { success: false, message: 'ছবির সাইজ সার্ভারের সীমার চেয়ে বড় (413)। ছোট ছবি দিন, অথবা হোস্টিং-এ upload_max_filesize বাড়ান।' };
        }
        if (res.status === 500) {
            return { success: false, message: 'সার্ভার ত্রুটি (500)। uploads/products ও uploads/customers ফোল্ডারের পারমিশন 755 করুন।' };
        }
        if (res.status === 404) {
            return { success: false, message: 'আপলোড স্ক্রিপ্ট পাওয়া যায়নি (404)। কোড ঠিকমতো আপলোড হয়েছে কিনা দেখুন।' };
        }
        if (res.status === 403) {
            return { success: false, message: 'অনুমতি নেই (403)। আবার লগইন করুন অথবা হোস্টিং সিকিউরিটি (mod_security) চেক করুন।' };
        }
        return { success: false, message: `সার্ভার ত্রুটি (HTTP ${res.status})।` };
    }
}
