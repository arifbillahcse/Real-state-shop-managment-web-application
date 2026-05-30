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
            'SELECT id, name, username, role, is_active, created_at FROM users ORDER BY id'
        );
    }

    public static function create(string $name, string $username, string $password, string $role): int|string
    {
        $exists = Database::fetchOne(
            'SELECT id FROM users WHERE username = ? LIMIT 1', [trim($username)]
        );
        if ($exists) return 'USERNAME_TAKEN';

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $id = Database::insert(
            'INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)',
            [trim($name), trim($username), $hashed, $role]
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
}
