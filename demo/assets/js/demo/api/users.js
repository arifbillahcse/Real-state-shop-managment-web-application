// Users — ports classes/User.php
// The real app bcrypt-hashes passwords. This prototype stores them as
// plain text because there is no server to verify a hash against.

(() => {

    const ROLES = ['admin', 'staff'];

    function countActiveAdmins() {
        return DemoDB.table('users')
            .filter(u => u.role === 'admin' && Number(u.is_active) === 1).length;
    }

    function currentUid() {
        const u = DemoAuth.current();
        return u ? Number(u.id) : 0;
    }

    // Never hand the password field back to the page.
    function publicUser(u) {
        return {
            id:         u.id,
            name:       u.name,
            username:   u.username,
            role:       u.role,
            is_active:  u.is_active,
            created_at: u.created_at,
        };
    }

    ApiRouter.register({

        'get_users.php': ApiRouter.adminOnly(() => {
            const data = DemoDB.table('users')
                .slice()
                .sort((a, b) => Number(a.id) - Number(b.id))
                .map(publicUser);
            return ApiRouter.ok('', { data });
        }),

        'add_user.php': ApiRouter.adminOnly((p) => {
            const name     = String(p.name     || '').trim();
            const username = String(p.username || '').trim();
            const password = String(p.password || '');
            const role     = String(p.role     || 'staff').trim();

            if (name === '')             return ApiRouter.fail('নাম দিন।');
            if (username === '')         return ApiRouter.fail('ইউজারনেম দিন।');
            if (password.length < 4)     return ApiRouter.fail('পাসওয়ার্ড কমপক্ষে ৪ অক্ষরের হতে হবে।');
            if (!ROLES.includes(role))   return ApiRouter.fail('সঠিক রোল নির্বাচন করুন।');

            const taken = DemoDB.table('users').some(u => u.username === username);
            if (taken) return ApiRouter.fail('এই ইউজারনেম ইতিমধ্যে ব্যবহৃত হচ্ছে।');

            const id = DemoDB.insert('users', {
                name, username, password, role, is_active: 1,
            });
            DemoDB.log('create_user', 'users', id, 'Created user: ' + username);
            return ApiRouter.ok('ব্যবহারকারী যোগ করা হয়েছে।', { id });
        }),

        'update_user.php': ApiRouter.adminOnly((p) => {
            const id   = Number(p.id || 0);
            const name = String(p.name || '').trim();
            const role = String(p.role || 'staff').trim();

            if (id <= 0) return ApiRouter.fail('সঠিক ID দিন।');

            const user = DemoDB.find('users', id);
            if (!user)                 return ApiRouter.fail('ব্যবহারকারী খুঁজে পাওয়া যায়নি।');
            if (name === '')           return ApiRouter.fail('নাম দিন।');
            if (!ROLES.includes(role)) return ApiRouter.fail('সঠিক রোল নির্বাচন করুন।');

            if (user.role === 'admin' && role !== 'admin' && countActiveAdmins() <= 1) {
                return ApiRouter.fail('শেষ অ্যাডমিনকে নিষ্ক্রিয় বা ডিমোট করা যাবে না।');
            }

            DemoDB.update('users', id, { name, role });
            DemoDB.log('update_user', 'users', id, 'Updated user: ' + user.username);

            // Keep the navbar truthful if an admin edited their own account.
            if (id === currentUid()) DemoAuth.refresh();

            return ApiRouter.ok('ব্যবহারকারী আপডেট করা হয়েছে।');
        }),

        'toggle_user.php': ApiRouter.adminOnly((p) => {
            const id     = Number(p.id || 0);
            const active = Number(p.active || 0) === 1;

            if (id <= 0) return ApiRouter.fail('সঠিক ID দিন।');

            const user = DemoDB.find('users', id);
            if (!user) return ApiRouter.fail('ব্যবহারকারী খুঁজে পাওয়া যায়নি।');

            if (id === currentUid() && !active) {
                return ApiRouter.fail('আপনি নিজের অ্যাকাউন্ট নিষ্ক্রিয় করতে পারবেন না।');
            }
            if (user.role === 'admin' && !active && countActiveAdmins() <= 1) {
                return ApiRouter.fail('শেষ অ্যাডমিনকে নিষ্ক্রিয় বা ডিমোট করা যাবে না।');
            }

            DemoDB.update('users', id, { is_active: active ? 1 : 0 });
            DemoDB.log('toggle_user', 'users', id,
                (active ? 'Activated' : 'Deactivated') + ' user: ' + user.username);

            return ApiRouter.ok(active
                ? 'অ্যাকাউন্ট সক্রিয় করা হয়েছে।'
                : 'অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে।');
        }),

        'reset_password.php': ApiRouter.adminOnly((p) => {
            const id       = Number(p.id || 0);
            const password = String(p.password || '');

            if (id <= 0) return ApiRouter.fail('সঠিক ID দিন।');

            const user = DemoDB.find('users', id);
            if (!user)               return ApiRouter.fail('ব্যবহারকারী খুঁজে পাওয়া যায়নি।');
            if (password.length < 4) return ApiRouter.fail('পাসওয়ার্ড কমপক্ষে ৪ অক্ষরের হতে হবে।');

            DemoDB.update('users', id, { password });
            DemoDB.log('update_password', 'users', id, 'Password changed');
            return ApiRouter.ok('পাসওয়ার্ড পরিবর্তন করা হয়েছে।');
        }),
    });
})();
