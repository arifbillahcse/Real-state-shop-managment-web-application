// ============================================
// Demo API router — core.
// Endpoint handlers live in assets/js/demo/api/*.js, one file per domain
// mirroring classes/. Each returns the same { success, message, ...data }
// envelope as api/*.php, and the guards below mirror api/_guard.php.
// ============================================

const ApiRouter = (() => {

    const routes = {};

    const ok   = (message = '', data = {}) => Object.assign({ success: true, message }, data);
    const fail = (message) => ({ success: false, message });

    // A guard returns null when it passes, or a failure payload when it doesn't.
    const GUARDS = {
        // requireAdminApi() — admin or manager
        admin: () => DemoAuth.isAdminOrManager()
            ? null : fail('এই কাজের অনুমতি নেই।'),

        // requireStrictAdminApi() — users, settings
        strictAdmin: () => DemoAuth.isAdmin()
            ? null : fail('এই কাজের অনুমতি শুধু অ্যাডমিনের আছে।'),

        // requireBranchWriteApi() — admin, manager or assistant manager
        branchWrite: () => DemoAuth.canWriteBranchData()
            ? null : fail('এই কাজের অনুমতি নেই।'),
    };

    function guarded(kind, handler) {
        return (params) => {
            const blocked = GUARDS[kind]();
            return blocked || handler(params);
        };
    }

    const adminOnly       = (h) => guarded('admin', h);
    const strictAdminOnly = (h) => guarded('strictAdmin', h);
    const branchWriteOnly = (h) => guarded('branchWrite', h);

    /**
     * requireOwnBranchRecord() — for edit/delete of an existing row, make
     * sure a branch-locked user owns it. Returns a failure payload to
     * return, or null when the caller may proceed.
     */
    function ownBranchRecord(tableName, id, branchColumn = 'branch_id') {
        const locked = DemoAuth.lockedBranchId();
        if (locked === null) return null;   // admin / manager — unrestricted

        const allowed = [
            'sales', 'stock_inbound', 'stock_adjustments', 'stock_transfers',
            'expenses', 'quotations', 'installment_plans',
        ];
        if (!allowed.includes(tableName)) return fail('এই কাজের অনুমতি নেই।');

        const row = DemoDB.find(tableName, id);
        if (!row) return fail('রেকর্ডটি খুঁজে পাওয়া যায়নি।');
        if (Number(row[branchColumn]) !== locked) return fail('এটি আপনার ব্রাঞ্চের রেকর্ড নয়।');
        return null;
    }

    /**
     * requireOwnTransferSide() — transfers have two branch sides, so which
     * one matters depends on the action being performed.
     */
    function ownTransferSide(id, side) {
        const locked = DemoAuth.lockedBranchId();
        if (locked === null) return null;

        const row = DemoDB.find('stock_transfers', id);
        if (!row) return fail('ট্রান্সফার এন্ট্রি খুঁজে পাওয়া যায়নি।');

        const column = side === 'to' ? 'to_branch_id' : 'from_branch_id';
        if (Number(row[column]) !== locked) {
            return fail(side === 'to'
                ? 'এই ট্রান্সফারটি আপনার ব্রাঞ্চে আসেনি।'
                : 'এটি আপনার ব্রাঞ্চের ট্রান্সফার নয়।');
        }
        return null;
    }

    function handle(endpoint, params) {
        // The login check at the top of api/_guard.php
        if (!DemoAuth.isLoggedIn()) {
            return fail('অনুমতি নেই। আবার লগইন করুন।');
        }

        const route = routes[endpoint];
        if (!route) {
            return fail('ডেমোতে এই অংশটি এখনো যুক্ত হয়নি (' + endpoint + ')।');
        }

        try {
            return route(params || {});
        } catch (err) {
            console.error('Demo API error in ' + endpoint, err);
            return fail('সার্ভারে একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।');
        }
    }

    function register(map) { Object.assign(routes, map); }

    function has(endpoint) { return !!routes[endpoint]; }

    return {
        handle, register, has, ok, fail,
        adminOnly, strictAdminOnly, branchWriteOnly,
        ownBranchRecord, ownTransferSide,
    };
})();
