<?php

require_once __DIR__ . '/BaseModel.php';

class Supplier extends BaseModel
{
    protected static string $table = 'suppliers';

    public static function addSupplier(string $name, string $phone = '', string $address = ''): int|string
    {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';

        $exists = Database::fetchOne(
            'SELECT id FROM suppliers WHERE name = ? AND is_active = 1 LIMIT 1',
            [$name]
        );
        if ($exists) return 'DUPLICATE';

        $id = Database::insert(
            'INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)',
            [$name, trim($phone), trim($address)]
        );
        self::log('create_supplier', 'suppliers', (int)$id, "Added supplier: $name");
        return (int)$id;
    }

    public static function getSuppliers(): array
    {
        return Database::fetchAll(
            'SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name'
        );
    }

    public static function getSupplierById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT * FROM suppliers WHERE id = ? AND is_active = 1 LIMIT 1',
            [$id]
        );
    }

    public static function updateSupplier(int $id, array $data): bool|string
    {
        $supplier = self::getSupplierById($id);
        if (!$supplier) return 'NOT_FOUND';

        $name    = trim($data['name']    ?? $supplier['name']);
        $phone   = trim($data['phone']   ?? $supplier['phone']);
        $address = trim($data['address'] ?? $supplier['address']);

        if ($name === '') return 'NAME_REQUIRED';

        $dup = Database::fetchOne(
            'SELECT id FROM suppliers WHERE name = ? AND is_active = 1 AND id <> ? LIMIT 1',
            [$name, $id]
        );
        if ($dup) return 'DUPLICATE';

        Database::execute(
            'UPDATE suppliers SET name = ?, phone = ?, address = ? WHERE id = ?',
            [$name, $phone, $address, $id]
        );
        self::log('update_supplier', 'suppliers', $id, "Updated supplier: $name");
        return true;
    }

    public static function deleteSupplier(int $id): bool|string
    {
        $supplier = self::getSupplierById($id);
        if (!$supplier) return 'NOT_FOUND';

        $used = Database::fetchOne(
            'SELECT id FROM stock_inbound WHERE supplier_id = ? LIMIT 1', [$id]
        );
        if ($used) return 'HAS_HISTORY';

        Database::execute('UPDATE suppliers SET is_active = 0 WHERE id = ?', [$id]);
        self::log('delete_supplier', 'suppliers', $id, "Deleted supplier: {$supplier['name']}");
        return true;
    }

    /** Total purchase amount from a supplier. */
    public static function getTotalPurchase(int $id): float
    {
        $row = Database::fetchOne(
            'SELECT COALESCE(SUM(total_cost),0) AS total FROM stock_inbound WHERE supplier_id = ?',
            [$id]
        );
        return (float)($row['total'] ?? 0);
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NAME_REQUIRED' => 'সাপ্লাইয়ারের নাম দিন।',
            'DUPLICATE'     => 'এই নামে সাপ্লাইয়ার ইতিমধ্যে আছে।',
            'NOT_FOUND'     => 'সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।',
            'HAS_HISTORY'   => 'এই সাপ্লাইয়ারের ক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
