// ============================================
// Demo storage layer — stands in for classes/Database.php.
// Every table lives in localStorage under one key, with auto-increment ids.
// ============================================

const DemoDB = (() => {

    const KEY = 'niharika_demo_v2';
    let state = null;

    // localStorage throws in private mode / with site data blocked, so fall
    // back to an in-memory store for the page session.
    let persistent = true;

    function read() {
        try { return localStorage.getItem(KEY); }
        catch (e) { persistent = false; return null; }
    }

    function write() {
        if (!persistent) return;
        try { localStorage.setItem(KEY, JSON.stringify(state)); }
        catch (e) { persistent = false; }
    }

    function init() {
        if (state) return state;

        const raw = read();
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (parsed && parsed._version === 2) { state = parsed; return state; }
            } catch (e) { /* corrupt payload — reseed */ }
        }

        state = Seed.build();
        write();
        return state;
    }

    function reset() {
        state = Seed.build();
        write();
        return state;
    }

    function table(name) {
        init();
        if (!Array.isArray(state[name])) state[name] = [];
        return state[name];
    }

    function nextId(name) {
        return table(name).reduce((max, r) => Math.max(max, Number(r.id) || 0), 0) + 1;
    }

    function insert(name, row) {
        const rows = table(name);
        const rec  = Object.assign({}, row, { id: nextId(name) });
        if (!rec.created_at) rec.created_at = nowStamp();
        rows.push(rec);
        write();
        return rec.id;
    }

    function find(name, id) {
        if (id === null || id === undefined || id === '') return null;
        return table(name).find(r => Number(r.id) === Number(id)) || null;
    }

    function update(name, id, patch) {
        const row = find(name, id);
        if (!row) return false;
        Object.assign(row, patch, { updated_at: nowStamp() });
        write();
        return true;
    }

    function remove(name, id) {
        const rows = table(name);
        const i = rows.findIndex(r => Number(r.id) === Number(id));
        if (i === -1) return false;
        rows.splice(i, 1);
        write();
        return true;
    }

    function setting(key, fallback = '') {
        init();
        const v = state.settings[key];
        return (v === undefined || v === null || v === '') ? fallback : v;
    }

    function setSetting(key, value) {
        init();
        state.settings[key] = value;
        write();
    }

    function log(action, module, referenceId, description) {
        const user = (typeof DemoAuth !== 'undefined') ? DemoAuth.current() : null;
        insert('activity_logs', {
            user_id: user ? user.id : null,
            action, module, reference_id: referenceId, description,
            ip_address: '127.0.0.1',
        });
    }

    function nowStamp() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
               ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    function today() { return Seed.fmtDate(new Date()); }

    // Whole-database export/import, used by the backup page.
    function exportAll() { init(); return JSON.parse(JSON.stringify(state)); }

    function importAll(data) {
        if (!data || data._version !== 2) return false;
        state = data;
        write();
        return true;
    }

    return {
        init, reset, table, find, insert, update, remove,
        setting, setSetting, log, today, nowStamp,
        exportAll, importAll,
        isPersistent: () => persistent,
    };
})();
