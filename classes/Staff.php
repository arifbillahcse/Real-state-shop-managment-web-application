<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Staff accountability (§1, §8):
 * due assignments, task assignment, monthly collection ranking.
 */
class Staff extends BaseModel
{
    protected static string $table = 'due_assignments';

    // ── Due assignments (হিসাব ট্রান্সফার) ──────────────────────────────────
    public static function assignDue(
        int $customerId, int $staffId, string $note, ?int $assignedBy
    ): int|string {
        $cust = Database::fetchOne(
            'SELECT id FROM customers WHERE id = ? AND is_active = 1', [$customerId]
        );
        if (!$cust) return 'CUSTOMER_NOT_FOUND';
        $staff = Database::fetchOne(
            'SELECT id FROM users WHERE id = ? AND is_active = 1', [$staffId]
        );
        if (!$staff) return 'STAFF_NOT_FOUND';

        // One active assignment per customer — reassigning closes the old one
        Database::execute(
            'UPDATE due_assignments SET status = "closed"
             WHERE customer_id = ? AND status = "active"',
            [$customerId]
        );
        $id = (int)Database::insert(
            'INSERT INTO due_assignments (customer_id, staff_id, assigned_date, note, assigned_by)
             VALUES (?, ?, CURDATE(), ?, ?)',
            [$customerId, $staffId, trim($note) ?: null, $assignedBy]
        );
        self::log('assign_due', 'due_assignments', $id,
                  "Due of customer #$customerId assigned to staff #$staffId");
        return $id;
    }

    public static function closeAssignment(int $id): bool|string
    {
        $row = Database::fetchOne('SELECT id FROM due_assignments WHERE id = ? LIMIT 1', [$id]);
        if (!$row) return 'NOT_FOUND';
        Database::execute('UPDATE due_assignments SET status = "closed" WHERE id = ?', [$id]);
        return true;
    }

    public static function getActiveCollector(int $customerId): ?int
    {
        $row = Database::fetchOne(
            'SELECT staff_id FROM due_assignments
             WHERE customer_id = ? AND status = "active"
             ORDER BY id DESC LIMIT 1',
            [$customerId]
        );
        return $row ? (int)$row['staff_id'] : null;
    }

    public static function getAssignments(?int $staffId = null): array
    {
        $where  = 'a.status = "active"';
        $params = [];
        if ($staffId) { $where .= ' AND a.staff_id = ?'; $params[] = $staffId; }
        return Database::fetchAll(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone,
                    u.name AS staff_name,
                    COALESCE(b.balance, 0) AS current_balance
             FROM due_assignments a
             JOIN customers c ON c.id = a.customer_id
             JOIN users u     ON u.id = a.staff_id
             LEFT JOIN vw_customer_ledger_balance b ON b.customer_id = a.customer_id
             WHERE $where
             ORDER BY a.assigned_date DESC, a.id DESC",
            $params
        );
    }

    // ── Monthly collection ranking (মাস শেষে র‌্যাংকিং) ──────────────────────
    public static function collectionRanking(string $month): array
    {
        // $month = 'YYYY-MM'
        $from = $month . '-01';
        $to   = date('Y-m-t', strtotime($from));
        return Database::fetchAll(
            'SELECT u.id, u.name, u.role,
                    COUNT(l.id)              AS deposit_count,
                    COALESCE(SUM(l.credit),0) AS collected
             FROM users u
             JOIN customer_ledger l
                   ON l.collected_by = u.id
                  AND l.entry_type = "deposit" AND l.status = "final"
                  AND l.entry_date BETWEEN ? AND ?
             GROUP BY u.id
             ORDER BY collected DESC',
            [$from, $to]
        );
    }

    // ── Tasks ────────────────────────────────────────────────────────────────
    public static function addTask(
        int $userId, string $title, string $details, string $dueDate, ?int $assignedBy
    ): int|string {
        $title = trim($title);
        if ($title === '') return 'TITLE_REQUIRED';
        $user = Database::fetchOne(
            'SELECT id FROM users WHERE id = ? AND is_active = 1', [$userId]
        );
        if (!$user) return 'STAFF_NOT_FOUND';

        $id = (int)Database::insert(
            'INSERT INTO staff_tasks (user_id, title, details, due_date, assigned_by)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $title, trim($details) ?: null,
             ($dueDate !== '' && strtotime($dueDate)) ? $dueDate : null, $assignedBy]
        );
        self::log('assign_task', 'staff_tasks', $id, "Task '$title' assigned to user #$userId");
        return $id;
    }

    public static function setTaskStatus(int $taskId, string $status, ?int $actorId, bool $isManager): bool|string
    {
        if (!in_array($status, ['pending', 'done'], true)) return 'INVALID_STATUS';
        $task = Database::fetchOne('SELECT * FROM staff_tasks WHERE id = ? LIMIT 1', [$taskId]);
        if (!$task) return 'NOT_FOUND';
        // Staff may only update their own tasks
        if (!$isManager && (int)$task['user_id'] !== (int)$actorId) return 'FORBIDDEN';

        Database::execute(
            'UPDATE staff_tasks SET status = ?, done_at = ? WHERE id = ?',
            [$status, $status === 'done' ? date('Y-m-d H:i:s') : null, $taskId]
        );
        return true;
    }

    public static function deleteTask(int $taskId): bool|string
    {
        $task = Database::fetchOne('SELECT id FROM staff_tasks WHERE id = ? LIMIT 1', [$taskId]);
        if (!$task) return 'NOT_FOUND';
        Database::execute('DELETE FROM staff_tasks WHERE id = ?', [$taskId]);
        return true;
    }

    public static function getTasks(?int $userId = null, string $status = ''): array
    {
        $where  = '1=1';
        $params = [];
        if ($userId)       { $where .= ' AND t.user_id = ?'; $params[] = $userId; }
        if ($status !== '') { $where .= ' AND t.status = ?';  $params[] = $status; }
        return Database::fetchAll(
            "SELECT t.*, u.name AS user_name, ab.name AS assigned_by_name
             FROM staff_tasks t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users ab ON ab.id = t.assigned_by
             WHERE $where
             ORDER BY t.status ASC, t.due_date IS NULL, t.due_date ASC, t.id DESC",
            $params
        );
    }

    // Performance snapshot for one user (tasks + collections)
    public static function performance(int $userId, string $month): array
    {
        $from = $month . '-01';
        $to   = date('Y-m-t', strtotime($from));
        $tasks = Database::fetchOne(
            'SELECT COUNT(*) AS total, SUM(status = "done") AS done
             FROM staff_tasks WHERE user_id = ? AND created_at BETWEEN ? AND ?',
            [$userId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $collect = Database::fetchOne(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(credit),0) AS amount
             FROM customer_ledger
             WHERE collected_by = ? AND entry_type = "deposit" AND status = "final"
               AND entry_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );
        return [
            'tasks_total'      => (int)($tasks['total'] ?? 0),
            'tasks_done'       => (int)($tasks['done'] ?? 0),
            'collection_count' => (int)($collect['cnt'] ?? 0),
            'collection_total' => (float)($collect['amount'] ?? 0),
        ];
    }

    public static function errorMessage(string $code): string
    {
        return [
            'CUSTOMER_NOT_FOUND' => 'কাস্টমার খুঁজে পাওয়া যায়নি।',
            'STAFF_NOT_FOUND'    => 'স্টাফ/সদস্য খুঁজে পাওয়া যায়নি।',
            'NOT_FOUND'          => 'রেকর্ড খুঁজে পাওয়া যায়নি।',
            'TITLE_REQUIRED'     => 'কাজের শিরোনাম দিন।',
            'INVALID_STATUS'     => 'ভুল স্ট্যাটাস।',
            'FORBIDDEN'          => 'শুধু নিজের কাজ আপডেট করা যাবে।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
