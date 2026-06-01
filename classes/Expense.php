<?php

require_once __DIR__ . '/BaseModel.php';

class Expense extends BaseModel
{
    // ── Categories ──────────────────────────────────────────────────────────

    public static function getCategories(): array
    {
        return Database::fetchAll(
            'SELECT * FROM expense_categories ORDER BY id'
        );
    }

    public static function addCategory(string $name, string $icon = 'bi-receipt'): int|string
    {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';
        return (int)Database::insert(
            'INSERT INTO expense_categories (name, icon) VALUES (?, ?)',
            [$name, trim($icon)]
        );
    }

    public static function deleteCategory(int $id): bool
    {
        Database::execute(
            'UPDATE expenses SET category_id = NULL WHERE category_id = ?', [$id]
        );
        Database::execute('DELETE FROM expense_categories WHERE id = ?', [$id]);
        return true;
    }

    // ── Expenses ─────────────────────────────────────────────────────────────

    public static function getExpenses(
        ?string $from = null,
        ?string $to   = null,
        ?int    $categoryId = null,
        ?int    $branchId   = null
    ): array {
        $where  = [];
        $params = [];

        if ($from) { $where[] = 'e.expense_date >= ?'; $params[] = $from; }
        if ($to)   { $where[] = 'e.expense_date <= ?'; $params[] = $to;   }
        if ($categoryId) { $where[] = 'e.category_id = ?'; $params[] = $categoryId; }
        if ($branchId)   { $where[] = 'e.branch_id = ?';   $params[] = $branchId;   }

        $sql = "SELECT e.*, ec.name AS category_name, ec.icon AS category_icon,
                       b.name AS branch_name, u.name AS created_by_name
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.category_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN users u ON u.id = e.created_by"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY e.expense_date DESC, e.id DESC';

        return Database::fetchAll($sql, $params);
    }

    public static function addExpense(array $data): int|string
    {
        $amount = (float)($data['amount'] ?? 0);
        $date   = trim($data['expense_date'] ?? '');
        if ($amount <= 0) return 'INVALID_AMOUNT';
        if (!$date)       return 'DATE_REQUIRED';

        return (int)Database::insert(
            'INSERT INTO expenses (category_id, branch_id, amount, expense_date, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'] ?: null,
                $data['branch_id']   ?: null,
                $amount,
                $date,
                trim($data['description'] ?? ''),
                $data['created_by'] ?? null,
            ]
        );
    }

    public static function updateExpense(int $id, array $data): bool|string
    {
        $amount = (float)($data['amount'] ?? 0);
        $date   = trim($data['expense_date'] ?? '');
        if ($amount <= 0) return 'INVALID_AMOUNT';
        if (!$date)       return 'DATE_REQUIRED';

        Database::execute(
            'UPDATE expenses SET category_id=?, branch_id=?, amount=?, expense_date=?, description=?
             WHERE id=?',
            [
                $data['category_id'] ?: null,
                $data['branch_id']   ?: null,
                $amount,
                $date,
                trim($data['description'] ?? ''),
                $id,
            ]
        );
        return true;
    }

    public static function deleteExpense(int $id): bool
    {
        Database::execute('DELETE FROM expenses WHERE id = ?', [$id]);
        return true;
    }

    // ── Summary for Profit/Loss ──────────────────────────────────────────────

    public static function getTotalExpenses(string $from, string $to, ?int $branchId = null): float
    {
        $where  = ['expense_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($branchId) { $where[] = 'branch_id = ?'; $params[] = $branchId; }

        $row = Database::fetchOne(
            'SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE '
            . implode(' AND ', $where),
            $params
        );
        return (float)($row['total'] ?? 0);
    }

    public static function getCategoryTotals(string $from, string $to): array
    {
        return Database::fetchAll(
            "SELECT COALESCE(ec.name,'অশ্রেণীভুক্ত') AS category_name,
                    COALESCE(ec.icon,'bi-receipt') AS icon,
                    SUM(e.amount) AS total
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE e.expense_date BETWEEN ? AND ?
             GROUP BY e.category_id
             ORDER BY total DESC",
            [$from, $to]
        );
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NAME_REQUIRED'  => 'নাম দিন।',
            'INVALID_AMOUNT' => 'সঠিক পরিমাণ দিন।',
            'DATE_REQUIRED'  => 'তারিখ দিন।',
        ][$code] ?? 'অজানা ত্রুটি।';
    }
}
