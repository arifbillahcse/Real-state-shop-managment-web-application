<?php
require_once __DIR__ . '/BaseModel.php';

class Installment extends BaseModel
{
    protected static string $table = 'installment_plans';

    public static function createPlan(array $data): int|string
    {
        $customerName = trim($data['customer_name'] ?? '');
        $customerId   = ($data['customer_id']  ?? 0) ?: null;
        $saleId       = ($data['sale_id']       ?? 0) ?: null;
        $total        = (float)($data['total_amount']       ?? 0);
        $down         = (float)($data['down_payment']       ?? 0);
        $count        = (int)($data['installment_count']    ?? 0);
        $startDate    = $data['start_date'] ?? date('Y-m-d');
        $note         = trim($data['note'] ?? '');

        if ($customerName === '') return 'NAME_REQUIRED';
        if ($total <= 0)          return 'INVALID_AMOUNT';
        if ($count <= 0)          return 'INVALID_COUNT';
        if ($down < 0 || $down >= $total) $down = 0;

        $remaining   = $total - $down;
        $installAmt  = round($remaining / $count, 2);
        $userId      = $_SESSION['user_id'] ?? null;
        // Branch-locked users always create plans for their own branch.
        $branchId    = resolveBranchId($data['branch_id'] ?? null);

        Database::beginTransaction();
        try {
            $planId = Database::insert(
                'INSERT INTO installment_plans
                 (customer_name, customer_id, sale_id, branch_id, total_amount, down_payment,
                  installment_count, installment_amount, start_date, note, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$customerName, $customerId, $saleId, $branchId, $total, $down,
                 $count, $installAmt, $startDate, $note, $userId]
            );

            // Auto-generate installment rows (monthly)
            $date = new DateTime($startDate);
            for ($i = 1; $i <= $count; $i++) {
                // Last installment absorbs rounding difference
                $amt = ($i === $count) ? round($remaining - $installAmt * ($count - 1), 2) : $installAmt;
                Database::insert(
                    'INSERT INTO installments (plan_id, installment_no, due_date, amount)
                     VALUES (?,?,?,?)',
                    [$planId, $i, $date->format('Y-m-d'), $amt]
                );
                $date->modify('+1 month');
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }

        return (int)$planId;
    }

    public static function getPlans(array $f = []): array
    {
        $sql    = 'SELECT p.*,
                          (SELECT COUNT(*) FROM installments WHERE plan_id = p.id) AS total_inst,
                          (SELECT COUNT(*) FROM installments WHERE plan_id = p.id AND status = \'paid\') AS paid_inst,
                          (SELECT COALESCE(SUM(paid_amount),0) FROM installments WHERE plan_id = p.id) AS total_paid
                   FROM installment_plans p WHERE 1=1';
        $params = [];
        // Branch-locked users only see their own branch's plans.
        $branchId = lockedBranchId() ?? ($f['branch_id'] ?? null);
        if ($branchId) { $sql .= ' AND p.branch_id = ?'; $params[] = (int)$branchId; }
        if (!empty($f['status'])) { $sql .= ' AND p.status = ?'; $params[] = $f['status']; }
        if (!empty($f['search'])) {
            $sql .= ' AND p.customer_name LIKE ?'; $params[] = '%'.$f['search'].'%';
        }
        $sql .= ' ORDER BY p.start_date DESC, p.id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getPlanById(int $id): array|false
    {
        $p = Database::fetchOne('SELECT * FROM installment_plans WHERE id = ? LIMIT 1', [$id]);
        if (!$p) return false;
        $p['installments'] = Database::fetchAll(
            'SELECT * FROM installments WHERE plan_id = ? ORDER BY installment_no', [$id]
        );
        // Auto-mark overdue
        $today = date('Y-m-d');
        foreach ($p['installments'] as &$inst) {
            if ($inst['status'] === 'pending' && $inst['due_date'] < $today) {
                Database::execute(
                    "UPDATE installments SET status = 'overdue' WHERE id = ? AND status = 'pending'",
                    [$inst['id']]
                );
                $inst['status'] = 'overdue';
            }
        }
        return $p;
    }

    public static function payInstallment(int $instId, float $amount, string $note = ''): bool|string
    {
        $inst = Database::fetchOne('SELECT * FROM installments WHERE id = ? LIMIT 1', [$instId]);
        if (!$inst)                     return 'NOT_FOUND';
        if ($inst['status'] === 'paid') return 'ALREADY_PAID';
        if ($amount <= 0)               return 'INVALID_AMOUNT';

        Database::execute(
            "UPDATE installments SET paid_amount = ?, paid_date = ?, status = 'paid', note = ? WHERE id = ?",
            [$amount, date('Y-m-d'), trim($note), $instId]
        );

        // Check if all installments paid — mark plan complete
        $pending = Database::fetchOne(
            "SELECT COUNT(*) AS c FROM installments WHERE plan_id = ? AND status != 'paid'",
            [$inst['plan_id']]
        );
        if ((int)($pending['c'] ?? 1) === 0) {
            Database::execute(
                "UPDATE installment_plans SET status = 'completed' WHERE id = ?",
                [$inst['plan_id']]
            );
        }
        return true;
    }

    public static function cancelPlan(int $id): bool|string
    {
        $p = Database::fetchOne('SELECT status FROM installment_plans WHERE id = ? LIMIT 1', [$id]);
        if (!$p)                        return 'NOT_FOUND';
        if ($p['status'] === 'cancelled') return 'ALREADY_CANCELLED';
        Database::execute("UPDATE installment_plans SET status = 'cancelled' WHERE id = ?", [$id]);
        return true;
    }
}
