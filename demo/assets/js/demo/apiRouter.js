// ============================================
// Demo API router — core
// Endpoint handlers live in assets/js/demo/api/*.js, one file per
// domain, mirroring the classes/ folder of the PHP app. Each returns
// the same { success, message, ...data } envelope as api/*.php.
// ============================================

const ApiRouter = (() => {

    const routes = {};

    const ok   = (message = '', data = {}) => Object.assign({ success: true,  message }, data);
    const fail = (message)                  => ({ success: false, message });

    // Mirrors requireAdminApi() in api/_guard.php
    function adminOnly(handler) {
        return (params) => {
            if (!DemoAuth.isAdmin()) {
                return fail('এই কাজের অনুমতি শুধু অ্যাডমিনের আছে।');
            }
            return handler(params);
        };
    }

    function handle(endpoint, params) {
        // Mirrors the login check in api/_guard.php
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
            return fail('একটি সমস্যা হয়েছে।');
        }
    }

    function register(map) {
        Object.assign(routes, map);
    }

    return { handle, register, ok, fail, adminOnly };
})();
