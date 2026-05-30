<?php

/**
 * BaseModel provides shared helpers for all model classes.
 */
abstract class BaseModel
{
    protected static string $table = '';

    // Generic find by ID
    public static function findById(int $id): array|false
    {
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE id = ? LIMIT 1';
        return Database::fetchOne($sql, [$id]);
    }

    // Generic soft-delete check (is_active column)
    public static function setActive(int $id, bool $active): int
    {
        $sql = 'UPDATE ' . static::$table . ' SET is_active = ? WHERE id = ?';
        return Database::execute($sql, [(int)$active, $id]);
    }

    // Log any action to activity_logs
    public static function log(
        string $action,
        string $module,
        int    $referenceId = 0,
        string $description = ''
    ): void {
        $userId = $_SESSION['user_id'] ?? null;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? null;

        Database::insert(
            'INSERT INTO activity_logs
             (user_id, action, module, reference_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $module, $referenceId ?: null, $description, $ip]
        );
    }
}
