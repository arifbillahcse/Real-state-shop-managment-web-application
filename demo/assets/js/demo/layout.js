// ============================================
// Layout renderer
// Replaces includes/header.php and includes/sidebar.php —
// the navbar and sidebar are defined once here and injected
// into #appShell on every page.
// ============================================

const Layout = (() => {

    const menuItems = [
        { icon: 'bi-speedometer2',   label: 'ড্যাশবোর্ড',       href: 'dashboard.html', admin: false },
        { icon: 'bi-box-seam',       label: 'পণ্য',             href: 'products.html',  admin: true  },
        { icon: 'bi-stack',          label: 'স্টক',             href: 'stock.html',     admin: false },
        { icon: 'bi-cart-check',     label: 'বিক্রয়',           href: 'sales.html',     admin: false },
        { icon: 'bi-people',         label: 'কাস্টমার',         href: 'customers.html', admin: false },
        { icon: 'bi-wallet2',        label: 'বাকি / পেমেন্ট',   href: 'payments.html',  admin: false },
        { icon: 'bi-truck',          label: 'সাপ্লাইয়ার',       href: 'suppliers.html', admin: true  },
        { icon: 'bi-bar-chart-line', label: 'রিপোর্ট',          href: 'reports.html',   admin: true  },
        { icon: 'bi-people-fill',    label: 'ব্যবহারকারী',      href: 'users.html',     admin: true  },
        { icon: 'bi-gear',           label: 'সেটিংস',           href: 'settings.html',  admin: true  },
    ];

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function currentPage() {
        const path = window.location.pathname.split('/').pop();
        return path === '' ? 'dashboard.html' : path;
    }

    function render() {
        const shell = document.getElementById('appShell');
        if (!shell) return;

        const user     = DemoAuth.current() || { name: '', role: 'staff' };
        const isAdmin  = user.role === 'admin';
        const shopName = DemoDB.setting('shop_name', 'রড সিমেন্ট ম্যানেজমেন্ট');
        const active   = currentPage();

        document.title = (document.title || 'Demo') + ' — ' + shopName;

        const links = menuItems
            .filter(item => !item.admin || isAdmin)
            .map(item => `
                <li class="nav-item">
                    <a href="${item.href}"
                       class="nav-link text-white rounded mb-1 ${active === item.href ? 'active-menu' : ''}">
                        <i class="${item.icon} me-2"></i>${item.label}
                    </a>
                </li>`)
            .join('');

        shell.innerHTML = `
        <nav class="navbar navbar-dark bg-dark px-3 py-2 fixed-top shadow">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="navbar-brand mb-0 fw-bold fs-6">
                    <i class="bi bi-shop me-1 text-danger"></i>${esc(shopName)}
                </span>
                <span class="badge bg-warning text-dark ms-1 d-none d-lg-inline">DEMO</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-md-inline">${esc(user.name)}</span>
                        <span class="badge bg-${isAdmin ? 'danger' : 'secondary'} ms-1">
                            ${isAdmin ? 'Admin' : 'Staff'}
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><h6 class="dropdown-header">ভূমিকা পরিবর্তন করুন</h6></li>
                        <li><a class="dropdown-item" href="#" data-role="admin">
                            <i class="bi bi-shield-check me-2 text-danger"></i>Admin হিসেবে দেখুন</a></li>
                        <li><a class="dropdown-item" href="#" data-role="staff">
                            <i class="bi bi-person me-2 text-secondary"></i>Staff হিসেবে দেখুন</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-warning" href="#" id="resetDemo">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>ডেমো ডেটা রিসেট</a></li>
                    </ul>
                </div>
                <a href="#" id="logoutBtn" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="d-none d-md-inline">লগআউট</span>
                </a>
            </div>
        </nav>

        <div id="sidebar" class="sidebar bg-dark">
            <ul class="nav flex-column px-2 pt-2">${links}</ul>
            <div class="px-3 py-3 mt-2 border-top border-secondary">
                <p class="text-secondary small mb-1">
                    <i class="bi bi-info-circle me-1"></i>এটি একটি ডেমো
                </p>
                <p class="text-secondary small mb-0" style="font-size:.75rem;line-height:1.5">
                    সব ডেটা আপনার ব্রাউজারে সংরক্ষিত হয়। কোনো সার্ভার বা ডেটাবেস ব্যবহার করা হয়নি।
                </p>
            </div>
        </div>`;

        bind();
    }

    function bind() {
        const logout = document.getElementById('logoutBtn');
        if (logout) {
            logout.addEventListener('click', e => {
                e.preventDefault();
                DemoAuth.logout();
                window.location.href = 'index.html';
            });
        }

        document.querySelectorAll('[data-role]').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                DemoAuth.switchRole(el.dataset.role);
                window.location.reload();
            });
        });

        const reset = document.getElementById('resetDemo');
        if (reset) {
            reset.addEventListener('click', e => {
                e.preventDefault();
                if (!confirm('ডেমো ডেটা রিসেট করবেন?\nআপনার করা সব পরিবর্তন মুছে যাবে।')) return;
                DemoDB.reset();
                window.location.reload();
            });
        }
    }

    // Protected pages call this instead of PHP's requireLogin()/requireAdmin().
    function boot(options = {}) {
        DemoDB.init();

        if (options.admin) {
            if (!DemoAuth.requireAdmin()) return false;
        } else if (!DemoAuth.requireLogin()) {
            return false;
        }

        render();
        return true;
    }

    return { boot, render, esc };
})();
