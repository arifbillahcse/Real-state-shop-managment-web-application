<?php

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    protected static string $table = 'users';

    public static function login(string $username, string $password): array|false
    {
        $user = Database::fetchOne(
            'SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1',
            [trim($username)]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Start session
        session_regenerate_id(true);
        $_SESSION['user_id']            = $user['id'];
        $_SESSION['user_name']          = $user['name'];
        $_SESSION['user_role']          = $user['role'];
        $_SESSION['user_username']      = $user['username'];
        $_SESSION['user_branch_id']     = $user['branch_id'] ?? null;
        $_SESSION['last_regeneration']  = time();
        $_SESSION['login_time']         = time();

        self::log('login', 'auth', (int)$user['id'], 'User logged in');

        return $user;
    }

    public static function logout(): void
    {
        if (isLoggedIn()) {
            self::log('logout', 'auth', (int)($_SESSION['user_id'] ?? 0), 'User logged out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function checkRole(string $role): bool
    {
        return ($_SESSION['user_role'] ?? '') === $role;
    }

    public static function isAdmin(): bool
    {
        return self::checkRole('admin');
    }

    public static function isManager(): bool
    {
        return self::checkRole('manager');
    }

    public static function isAdminOrManager(): bool
    {
        return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true);
    }

    public static function getCurrentUser(): array|false
    {
        if (!isLoggedIn()) return false;
        return Database::fetchOne(
            'SELECT id, name, username, role FROM users WHERE id = ? LIMIT 1',
            [$_SESSION['user_id']]
        );
    }

    public static function getAll(): array
    {
        return Database::fetchAll(
            'SELECT u.id, u.name, u.username, u.role, u.is_active, u.created_at,
                    u.branch_id, b.name AS branch_name
             FROM users u
             LEFT JOIN branches b ON b.id = u.branch_id
             ORDER BY u.id'
        );
    }

    /** Every role the system accepts. */
    public const ROLES = ['admin', 'manager', 'assistant_manager', 'staff'];

    /**
     * Roles that are pinned to a single branch. For these the branch is not
     * optional — it IS the account's scope, and without it lockedBranchId()
     * would return null and hand the user the whole business.
     */
    public const BRANCH_ROLES = ['assistant_manager', 'staff'];

    /**
     * Normalise the branch for a role: required for branch-scoped roles,
     * always cleared for admin and manager (who work across branches).
     * Returns 'BRANCH_REQUIRED' when a branch-scoped role has none.
     */
    private static function branchForRole(string $role, ?int $branchId): int|null|string
    {
        if (!in_array($role, self::BRANCH_ROLES, true)) return null;
        if ($branchId === null || $branchId <= 0)       return 'BRANCH_REQUIRED';

        $branch = Database::fetchOne(
            'SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1', [$branchId]
        );
        return $branch ? $branchId : 'BRANCH_REQUIRED';
    }

    public static function create(
        string $name,
        string $username,
        string $password,
        string $role,
        ?int   $branchId = null
    ): int|string {
        if (!in_array($role, self::ROLES, true)) return 'INVALID_ROLE';

        $exists = Database::fetchOne(
            'SELECT id FROM users WHERE username = ? LIMIT 1', [trim($username)]
        );
        if ($exists) return 'USERNAME_TAKEN';

        $branchId = self::branchForRole($role, $branchId);
        if (is_string($branchId)) return $branchId;

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $id = Database::insert(
            'INSERT INTO users (name, username, password, role, branch_id) VALUES (?, ?, ?, ?, ?)',
            [trim($name), trim($username), $hashed, $role, $branchId]
        );
        self::log('create_user', 'users', (int)$id, "Created user: $username");
        return (int)$id;
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::execute(
            'UPDATE users SET password = ? WHERE id = ?',
            [$hashed, $id]
        );
        self::log('update_password', 'users', $id, 'Password changed');
    }

    public static function getById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT id, name, username, role, is_active, branch_id FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** Update a user's name, role, and branch (username is immutable). */
    public static function updateUser(int $id, string $name, string $role, ?int $branchId = null): bool|string
    {
        $user = self::getById($id);
        if (!$user) return 'NOT_FOUND';

        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';
        if (!in_array($role, self::ROLES, true)) return 'INVALID_ROLE';

        // Don't allow demoting the last active admin
        if ($user['role'] === 'admin' && $role !== 'admin' && self::countActiveAdmins() <= 1) {
            return 'LAST_ADMIN';
        }

        $branchId = self::branchForRole($role, $branchId);
        if (is_string($branchId)) return $branchId;

        Database::execute(
            'UPDATE users SET name = ?, role = ?, branch_id = ? WHERE id = ?',
            [$name, $role, $branchId, $id]
        );
        self::log('update_user', 'users', $id, "Updated user: {$user['username']}");
        return true;
    }

    /** Enable / disable a user account. */
    public static function setStatus(int $id, bool $active): bool|string
    {
        $user = self::getById($id);
        if (!$user) return 'NOT_FOUND';

        // Prevent self-deactivation
        if ($id === (int)($_SESSION['user_id'] ?? 0) && !$active) {
            return 'SELF_DEACTIVATE';
        }
        // Prevent disabling the last active admin
        if ($user['role'] === 'admin' && !$active && self::countActiveAdmins() <= 1) {
            return 'LAST_ADMIN';
        }

        Database::execute('UPDATE users SET is_active = ? WHERE id = ?', [(int)$active, $id]);
        self::log('toggle_user', 'users', $id,
            ($active ? 'Activated' : 'Deactivated') . " user: {$user['username']}");
        return true;
    }

    /** Admin reset of another user's password. */
    public static function resetPassword(int $id, string $newPassword): bool|string
    {
        $user = self::getById($id);
        if (!$user) return 'NOT_FOUND';
        if (strlen($newPassword) < 4) return 'WEAK_PASSWORD';

        self::updatePassword($id, $newPassword);
        return true;
    }

    public static function countActiveAdmins(): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin' AND is_active = 1"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public static function errorMessage(string $code): string
    {
        return [
            'USERNAME_TAKEN'  => 'এই ইউজারনেম ইতিমধ্যে ব্যবহৃত হচ্ছে।',
            'NOT_FOUND'       => 'ব্যবহারকারী খুঁজে পাওয়া যায়নি।',
            'NAME_REQUIRED'   => 'নাম দিন।',
            'INVALID_ROLE'    => 'সঠিক রোল নির্বাচন করুন।',
            'BRANCH_REQUIRED' => 'এই রোলের জন্য একটি সক্রিয় ব্রাঞ্চ নির্বাচন করা বাধ্যতামূলক।',
            'LAST_ADMIN'      => 'শেষ অ্যাডমিনকে নিষ্ক্রিয় বা ডিমোট করা যাবে না।',
            'SELF_DEACTIVATE' => 'আপনি নিজের অ্যাকাউন্ট নিষ্ক্রিয় করতে পারবেন না।',
            'WEAK_PASSWORD'   => 'পাসওয়ার্ড কমপক্ষে ৪ অক্ষরের হতে হবে।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
