// ============================================
// Demo auth — stands in for the PHP session and the role helpers in
// includes/init.php. Four roles, two of them pinned to one branch.
//
// This is a public prototype: the credentials are printed on the login
// screen on purpose and nothing here protects real data.
// ============================================

const DemoAuth = (() => {

    const KEY = 'niharika_demo_session';
    let memory = null;   // fallback when sessionStorage is unavailable

    function read() {
        try {
            const raw = sessionStorage.getItem(KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return memory; }
    }

    function write(user) {
        memory = user;
        try {
            if (user) sessionStorage.setItem(KEY, JSON.stringify(user));
            else      sessionStorage.removeItem(KEY);
        } catch (e) { /* kept in memory only */ }
    }

    function sessionFor(row) {
        return {
            id: row.id, name: row.name, username: row.username,
            role: row.role, branch_id: row.branch_id ?? null,
        };
    }

    function login(username, password) {
        DemoDB.init();

        const user = DemoDB.table('users').find(u =>
            u.username === String(username).trim() && u.password === password);

        if (!user)                        return { success: false, message: 'ইউজারনেম বা পাসওয়ার্ড ভুল!' };
        if (Number(user.is_active) !== 1) return { success: false, message: 'এই অ্যাকাউন্টটি নিষ্ক্রিয়।' };

        write(sessionFor(user));
        return { success: true, message: 'লগইন সফল' };
    }

    function logout()     { write(null); }
    function current()    { return read(); }
    function isLoggedIn() { return !!read(); }

    const roleIs = (...roles) => {
        const u = read();
        return !!u && roles.includes(u.role);
    };

    const isAdmin            = () => roleIs('admin');
    const isManager          = () => roleIs('manager');
    const isAssistantManager = () => roleIs('assistant_manager');
    const isStaff            = () => roleIs('staff');

    // Deliberately excludes assistant_manager — they are branch-scoped and
    // must not inherit blanket write access (see includes/init.php).
    const isAdminOrManager  = () => roleIs('admin', 'manager');
    const canWriteBranchData = () => roleIs('admin', 'manager', 'assistant_manager');

    function sessionBranchId() {
        const u = read();
        return u && u.branch_id !== null && u.branch_id !== undefined
            ? Number(u.branch_id) : null;
    }

    // The branch a user is LOCKED to, or null if they work across all
    // branches. The single source of truth for branch scoping.
    function lockedBranchId() {
        return (isStaff() || isAssistantManager()) ? sessionBranchId() : null;
    }

    // A locked user always gets their own branch, whatever the request asked
    // for; everyone else gets the requested value.
    function resolveBranchId(requested) {
        const locked = lockedBranchId();
        if (locked !== null) return locked;
        return (requested !== null && requested !== undefined && requested !== '')
            ? Number(requested) : null;
    }

    function refresh() {
        const u = read();
        if (!u) return;
        const row = DemoDB.find('users', u.id);
        if (row) write(sessionFor(row));
    }

    // Switch role without logging out — lets a visitor compare what each
    // role sees without hunting for passwords.
    function switchRole(role) {
        const match = DemoDB.table('users').find(u =>
            u.role === role && Number(u.is_active) === 1);
        if (match) write(sessionFor(match));
    }

    // Page guards, mirroring includes/init.php
    function requireLogin() {
        if (!isLoggedIn()) { window.location.replace('index.html'); return false; }
        return true;
    }

    function requireStrictAdmin() {
        if (!requireLogin()) return false;
        if (!isAdmin()) { window.location.replace('dashboard.html'); return false; }
        return true;
    }

    function requireManagerOrAdmin() {
        if (!requireLogin()) return false;
        if (!isAdminOrManager()) { window.location.replace('dashboard.html'); return false; }
        return true;
    }

    function requireBranchStaffOrAbove() {
        if (!requireLogin()) return false;
        if (!roleIs('admin', 'manager', 'assistant_manager')) {
            window.location.replace('dashboard.html');
            return false;
        }
        return true;
    }

    const ROLE_LABEL = {
        admin: 'Admin', manager: 'Manager',
        assistant_manager: 'Asst. Manager', staff: 'Staff',
    };

    const ROLE_COLOR = {
        admin: 'danger', manager: 'primary',
        assistant_manager: 'info', staff: 'secondary',
    };

    return {
        login, logout, current, isLoggedIn, refresh, switchRole,
        isAdmin, isManager, isAssistantManager, isStaff,
        isAdminOrManager, canWriteBranchData,
        sessionBranchId, lockedBranchId, resolveBranchId,
        requireLogin, requireStrictAdmin, requireManagerOrAdmin, requireBranchStaffOrAbove,
        ROLE_LABEL, ROLE_COLOR,
    };
})();
