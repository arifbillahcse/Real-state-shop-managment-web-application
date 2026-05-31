<?php

require_once __DIR__ . '/BaseModel.php';

class Setting extends BaseModel
{
    protected static string $table = 'settings';

    public static function get(string $key, string $default = ''): string
    {
        $row = Database::fetchOne(
            'SELECT setting_val FROM settings WHERE setting_key = ? LIMIT 1',
            [$key]
        );
        return $row ? (string)$row['setting_val'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            'INSERT INTO settings (setting_key, setting_val)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)',
            [$key, $value]
        );
    }

    public static function getAll(): array
    {
        $rows = Database::fetchAll('SELECT setting_key, setting_val FROM settings');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_val'];
        }
        return $result;
    }
}
