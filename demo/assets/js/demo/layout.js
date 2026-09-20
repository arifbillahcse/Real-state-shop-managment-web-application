// ============================================
// Layout renderer — replaces includes/header.php and includes/sidebar.php.
// The navbar and sidebar are defined once here and injected into #appShell.
// ============================================

const Layout = (() => {

    // Mirrors the $menuItems array in includes/sidebar.php, including the
    // three entries hidden there. The demo shows them, since a visitor has
    // no other way to reach those pages.
    const menuItems = [
        { icon: 'bi-speedometer2',      label: 'ড্যাশবোর্ড',       href: 'dashboard.html',       admin: false, manager: true,  asst: true,  staff: true },
        { icon: 'bi-box-seam',          label: 'পণ্য',             href: 'products.html',        admin: false, manager: true,  asst: false, staff: false },
        { icon: 'bi-stack',             label: 'স্টক',             href: 'stock.html',           admin: false, manager: true,  asst: true,  staff: true },
        { icon: 'bi-arrow-left-right',  label: 'ট্রান্সফার',        href: 'transfers.html',       admin: false, manager: true,  asst: true,  staff: true },
        { icon: 'bi-bell',              label: 'স্টক সতর্কতা',      href: 'alert_center.html',    admin: false, manager: true,  asst: true,  staff: true },
        { icon: 'bi-cart-check',        label: 'বিক্রয়',            href: 'sales.html',           admin: false, manager: true,  asst: true,  staff: true },
        { icon: 'bi-file-earmark-text', label: 'কোটেশন',           href: 'quotations.html',      admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-people',            label: 'কাস্টমার',          href: 'customers.html',       admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-wallet2',           label: 'বাকি / পেমেন্ট',   href: 'payments.html',        admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-calendar-check',    label: 'কিস্তি',            href: 'installments.html',    admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-shop',              label: 'ব্রাঞ্চ',           href: 'branches.html',        admin: false, manager: true,  asst: false, staff: false },
        { icon: 'bi-truck',             label: 'সাপ্লাইয়ার',        href: 'suppliers.html',       admin: false, manager: true,  asst: false, staff: false },
        { icon: 'bi-journal-text',      label: 'খাতা',             href: 'khata.html',           admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-journal-check',     label: 'ডেইলি স্টেটমেন্ট',  href: 'daily_statement.html', admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-person-workspace',  label: 'স্টাফ প্যানেল',     href: 'staff_panel.html',     admin: false, manager: true,  asst: false, staff: true },
        { icon: 'bi-bar-chart-line',    label: 'রিপোর্ট',           href: 'reports.html',         admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-cash-stack',        label: 'খরচ',              href: 'expenses.html',        admin: false, manager: true,  asst: true,  staff: false },
        { icon: 'bi-sticky',            label: 'নোট',              href: 'notes.html',           admin: false, manager: true,  asst: false, staff: false },
        { icon: 'bi-people-fill',       label: 'ব্যবহারকারী',       href: 'users.html',           admin: true,  manager: false, asst: false, staff: false },
        { icon: 'bi-database-fill-down',label: 'ব্যাকআপ',           href: 'backup.html',          admin: true,  manager: false, asst: false, staff: false },
        { icon: 'bi-database-gear',     label: 'ডাটাবেস আপডেট',    href: 'migrate.html',         admin: true,  manager: false, asst: false, staff: false },
        { icon: 'bi-gear',              label: 'সেটিংস',            href: 'settings.html',        admin: true,  manager: false, asst: false, staff: false },
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

    // Mirrors the visibility rules at the top of includes/sidebar.php
    function visibleTo(item) {
        if (DemoAuth.isAdmin())            return true;
        if (item.admin)                    return false;
        if (DemoAuth.isManager())          return !!item.manager;
        if (DemoAuth.isAssistantManager()) return !!item.asst;
        if (DemoAuth.isStaff())            return !!item.staff;
        return false;
    }

    // Pages that are built. Anything else is greyed out with a hint, so a
    // visitor never lands on a 404 while the port is in progress.
    const built = new Set(['dashboard.html', 'products.html', 'stock.html']);

    function render() {
        const shell = document.getElementById('appShell');
        if (!shell) return;

        const user     = DemoAuth.current() || { name: '', role: 'staff' };
        const shopName = DemoDB.setting('shop_name', 'রড সিমেন্ট ম্যানেজমেন্ট');
        const active   = currentPage();
        const initial  = (user.name || 'U').trim().charAt(0) || 'U';
        const branch   = user.branch_id ? Compute.branchName(user.branch_id) : null;

        document.title = (document.title || 'Demo') + ' — ' + shopName;

        const links = menuItems.filter(visibleTo).map(item => {
            const ready = built.has(item.href);
            return `
            <li class="nav-item">
                <a href="${ready ? item.href : '#'}"
                   class="nav-link sidebar-link ${active === item.href ? 'active-menu' : ''} ${ready ? '' : 'demo-soon'}"
                   ${ready ? '' : 'title="এই পেজটি ডেমোতে এখনো যুক্ত হচ্ছে"'}>
                    <i class="${item.icon}"></i><span>${item.label}</span>
                    ${ready ? '' : '<i class="bi bi-hourglass-split ms-auto small opacity-50"></i>'}
                </a>
            </li>`;
        }).join('');

        shell.innerHTML = `
        <nav class="navbar app-navbar px-3 py-2 fixed-top">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm nav-toggle-btn" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="navbar-brand app-brand mb-0 fw-bold fs-6">
                    <span class="brand-logo"><i class="bi bi-shop"></i></span>
                    <span class="d-none d-sm-inline">${esc(shopName)}</span>
                </span>
                <span class="badge bg-warning text-dark ms-1 d-none d-lg-inline">DEMO</span>
            </div>
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <button class="btn btn-sm theme-toggle" id="themeToggle" title="থিম পরিবর্তন" type="button">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm p-0 border-0 bg-transparent" data-bs-toggle="dropdown">
                        <span class="user-chip d-flex">
                            <span class="user-avatar">${esc(initial)}</span>
                            <span class="user-meta d-none d-md-flex">
                                <span class="user-name">${esc(user.name)}</span>
                                <span class="badge role-badge bg-${DemoAuth.ROLE_COLOR[user.role] || 'secondary'}">
                                    ${DemoAuth.ROLE_LABEL[user.role] || user.role}
                                </span>
                            </span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        ${branch ? `<li><h6 class="dropdown-header"><i class="bi bi-shop me-1"></i>${esc(branch)}</h6></li>` : ''}
                        <li><h6 class="dropdown-header">ভূমিকা পরিবর্তন করুন</h6></li>
                        <li><a class="dropdown-item" href="#" data-role="admin"><i class="bi bi-shield-check me-2 text-danger"></i>Admin</a></li>
                        <li><a class="dropdown-item" href="#" data-role="manager"><i class="bi bi-person-badge me-2 text-primary"></i>Manager</a></li>
                        <li><a class="dropdown-item" href="#" data-role="assistant_manager"><i class="bi bi-person-gear me-2 text-info"></i>Asst. Manager</a></li>
                        <li><a class="dropdown-item" href="#" data-role="staff"><i class="bi bi-person me-2 text-secondary"></i>Staff</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-warning" href="#" id="resetDemo">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>ডেমো ডেটা রিসেট</a></li>
                    </ul>
                </div>
                <a href="#" id="logoutBtn" class="btn btn-sm btn-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="d-none d-md-inline">লগআউট</span>
                </a>
            </div>
        </nav>

        <div id="sidebar" class="sidebar">
            <ul class="nav flex-column px-2 pt-3 pb-2">${links}</ul>
            <div class="px-3 py-3 mt-1 border-top border-secondary-subtle">
                <p class="small mb-1 opacity-75"><i class="bi bi-info-circle me-1"></i>এটি একটি ডেমো</p>
                <p class="small mb-0 opacity-50" style="font-size:.75rem;line-height:1.5">
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

        document.querySelectorAll('.demo-soon').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                if (typeof showToast === 'function') {
                    showToast('এই পেজটি ডেমোতে এখনো যুক্ত হচ্ছে।', 'info');
                }
            });
        });
    }

    // Protected pages call this instead of PHP's requireLogin() family.
    function boot(options = {}) {
        DemoDB.init();

        const guard = options.guard || 'login';
        const pass = guard === 'strictAdmin'       ? DemoAuth.requireStrictAdmin()
                   : guard === 'managerOrAdmin'    ? DemoAuth.requireManagerOrAdmin()
                   : guard === 'branchStaffOrAbove'? DemoAuth.requireBranchStaffOrAbove()
                   : DemoAuth.requireLogin();

        if (!pass) return false;
        render();
        return true;
    }

    return { boot, render, esc, menuItems, built };
})();
