// ============================================
// Demo auth
// Stands in for the PHP session in includes/init.php.
// This is a public prototype: credentials are shown on the login
// screen on purpose and nothing here protects real data.
// ============================================

const DemoAuth = (() => {

    const KEY = 'rcshop_demo_session';
    let memory = null;   // fallback when sessionStorage is unavailable

    function read() {
        try {
            const raw = sessionStorage.getItem(KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return memory;
        }
    }

    function write(user) {
        memory = user;
        try {
            if (user) sessionStorage.setItem(KEY, JSON.stringify(user));
            else      sessionStorage.removeItem(KEY);
        } catch (e) {
            // session kept in memory only
        }
    }

    function login(username, password) {
        DemoDB.init();

        const user = DemoDB.table('users').find(u =>
            u.username === String(username).trim() && u.password === password
        );

        if (!user)                      return { success: false, message: 'ইউজারনেম বা পাসওয়ার্ড ভুল!' };
        if (Number(user.is_active) !== 1) return { success: false, message: 'এই অ্যাকাউন্টটি নিষ্ক্রিয়।' };

        write({ id: user.id, name: user.name, username: user.username, role: user.role });
        return { success: true, message: 'লগইন সফল' };
    }

    function logout() {
        write(null);
    }

    function current() {
        return read();
    }

    function isLoggedIn() {
        return !!read();
    }

    function isAdmin() {
        const u = read();
        return !!u && u.role === 'admin';
    }

    // Swap role without logging out — lets a visitor see the
    // Admin vs Staff sidebar difference in one click.
    function switchRole(role) {
        const u = read();
        if (!u) return;
        const match = DemoDB.table('users').find(x =>
            x.role === role && Number(x.is_active) === 1
        );
        if (!match) return;
        write({ id: match.id, name: match.name, username: match.username, role: match.role });
    }

    // Call at the top of every protected page.
    function requireLogin() {
        if (!isLoggedIn()) {
            window.location.replace('index.html');
            return false;
        }
        return true;
    }

    function requireAdmin() {
        if (!requireLogin()) return false;
        if (!isAdmin()) {
            window.location.replace('dashboard.html');
            return false;
        }
        return true;
    }

    return {
        login, logout, current, isLoggedIn, isAdmin,
        switchRole, requireLogin, requireAdmin,
    };
})();
