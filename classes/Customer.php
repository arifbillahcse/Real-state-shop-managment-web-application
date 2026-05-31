<?php

require_once __DIR__ . '/BaseModel.php';

class Customer extends BaseModel
{
    protected static string $table = 'customers';

    public static function addCustomer(string $name, string $phone = '', string $address = ''): int|string
    {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';

        $id = Database::insert(
            'INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)',
            [$name, trim($phone), trim($address)]
        );
        self::log('add_customer', 'customers', (int)$id, "Added customer: $name");
        return (int)$id;
    }

    public static function getCustomers(): array
    {
        return Database::fetchAll(
            'SELECT c.*, COALESCE(d.total_due, 0) AS total_due,
                    COALESCE(d.total_purchase, 0) AS total_purchase
             FROM customers c
             LEFT JOIN vw_customer_dues d ON d.customer_id = c.id
             WHERE c.is_active = 1
             ORDER BY c.name'
        );
    }

    public static function getCustomerById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT * FROM customers WHERE id = ? AND is_active = 1 LIMIT 1',
            [$id]
        );
    }

    public static function updateCustomer(int $id, array $data): bool|string
    {
        $customer = self::getCustomerById($id);
        if (!$customer) return 'NOT_FOUND';

        $name    = trim($data['name']    ?? $customer['name']);
        $phone   = trim($data['phone']   ?? $customer['phone'] ?? '');
        $address = trim($data['address'] ?? $customer['address'] ?? '');

        if ($name === '') return 'NAME_REQUIRED';

        Database::execute(
            'UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?',
            [$name, $phone, $address, $id]
        );
        self::log('update_customer', 'customers', $id, "Updated customer: $name");
        return true;
    }

    public static function deleteCustomer(int $id): bool|string
    {
        $customer = self::getCustomerById($id);
        if (!$customer) return 'NOT_FOUND';

        if ($id === 1) return 'PROTECTED';

        $used = Database::fetchOne(
            'SELECT id FROM sales WHERE customer_id = ? LIMIT 1', [$id]
        );
        if ($used) return 'HAS_SALES';

        Database::execute('UPDATE customers SET is_active = 0 WHERE id = ?', [$id]);
        self::log('delete_customer', 'customers', $id, "Deleted customer: {$customer['name']}");
        return true;
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NAME_REQUIRED' => 'কাস্টমারের নাম দিন।',
            'NOT_FOUND'     => 'কাস্টমার খুঁজে পাওয়া যায়নি।',
            'HAS_SALES'     => 'এই কাস্টমারের বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
            'PROTECTED'     => 'ডিফল্ট কাস্টমার ডিলিট করা যাবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
