<?php

require_once __DIR__ . '/BaseModel.php';

class Category extends BaseModel
{
    protected static string $table = 'product_categories';

    public static function getAll(): array
    {
        return Database::fetchAll('SELECT * FROM product_categories ORDER BY name');
    }

    public static function exists(int $id): bool
    {
        return (bool) Database::fetchOne(
            'SELECT id FROM product_categories WHERE id = ? LIMIT 1', [$id]
        );
    }

    public static function add(string $name): int|string
    {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';

        $dup = Database::fetchOne(
            'SELECT id FROM product_categories WHERE name = ? LIMIT 1', [$name]
        );
        if ($dup) return 'DUPLICATE';

        return (int) Database::insert(
            'INSERT INTO product_categories (name) VALUES (?)', [$name]
        );
    }

    public static function delete(int $id): bool|string
    {
        $used = Database::fetchOne(
            'SELECT id FROM products WHERE category_id = ? AND is_active = 1 LIMIT 1', [$id]
        );
        if ($used) return 'HAS_PRODUCTS';

        Database::execute('DELETE FROM product_categories WHERE id = ?', [$id]);
        return true;
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NAME_REQUIRED' => 'ক্যাটাগরির নাম দিন।',
            'DUPLICATE'     => 'এই ক্যাটাগরি ইতিমধ্যে আছে।',
            'HAS_PRODUCTS'  => 'এই ক্যাটাগরিতে পণ্য আছে, ডিলিট করা যাবে না।',
            'NOT_FOUND'     => 'ক্যাটাগরিটি খুঁজে পাওয়া যায়নি।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
