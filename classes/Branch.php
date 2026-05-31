<?php

require_once __DIR__ . '/BaseModel.php';

class Branch extends BaseModel
{
    protected static string $table = 'branches';

    public static function addBranch(
        string $name,
        string $address = '',
        string $phone   = ''
    ): int|string {
        $name = trim($name);
        if ($name === '') return 'EMPTY_NAME';

        $id = Database::insert(
            'INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)',
            [$name, trim($address), trim($phone)]
        );
        self::log('add_branch', 'branches', (int)$id, "Branch '$name' added");
        return (int)$id;
    }

    public static function getBranches(): array
    {
        return Database::fetchAll(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM users        WHERE branch_id = b.id) AS staff_count,
                    (SELECT COUNT(*) FROM stock_inbound WHERE branch_id = b.id) AS stock_entries,
                    (SELECT COUNT(*) FROM sales         WHERE branch_id = b.id
                      AND status = \'completed\') AS sales_count
             FROM   branches b
             WHERE  b.is_active = 1
             ORDER BY b.name'
        );
    }

    public static function getBranchById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT * FROM branches WHERE id = ? LIMIT 1', [$id]
        ) ?: false;
    }

    public static function updateBranch(
        int    $id,
        string $name,
        string $address = '',
        string $phone   = ''
    ): bool|string {
        $name = trim($name);
        if ($name === '')            return 'EMPTY_NAME';
        if (!self::getBranchById($id)) return 'NOT_FOUND';

        Database::execute(
            'UPDATE branches SET name = ?, address = ?, phone = ? WHERE id = ?',
            [$name, trim($address), trim($phone), $id]
        );
        self::log('update_branch', 'branches', $id, "Branch '$name' updated");
        return true;
    }

    public static function deleteBranch(int $id): bool|string
    {
        if (!self::getBranchById($id)) return 'NOT_FOUND';

        $hasStock = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM stock_inbound WHERE branch_id = ?', [$id]
        );
        if ((int)($hasStock['cnt'] ?? 0) > 0) return 'HAS_STOCK';

        $hasSales = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM sales WHERE branch_id = ?', [$id]
        );
        if ((int)($hasSales['cnt'] ?? 0) > 0) return 'HAS_SALES';

        // Unassign any staff from this branch before deleting
        Database::execute('UPDATE users SET branch_id = NULL WHERE branch_id = ?', [$id]);
        Database::execute('DELETE FROM branches WHERE id = ?', [$id]);
        self::log('delete_branch', 'branches', $id, "Branch #$id deleted");
        return true;
    }

    public static function errorMessage(string $code): string
    {
        return [
            'EMPTY_NAME' => 'ব্রাঞ্চের নাম দিন।',
            'NOT_FOUND'  => 'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
            'HAS_STOCK'  => 'এই ব্রাঞ্চে স্টক আছে, আগে স্টক সরান।',
            'HAS_SALES'  => 'এই ব্রাঞ্চে বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
