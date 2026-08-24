<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Stock.php';

/**
 * Product transfer workflow (§3–4).
 *
 * States: pending → sent → received | returned
 * - pending:  on the transfer sheet, no stock movement
 * - sent:     stock left the source branch (in transit)
 * - received: destination confirmed "In" — stock added there
 * - returned: destination bounced it — stock is back at source;
 *             the sender can edit and re-send
 */
class Transfer extends BaseModel
{
    protected static string $table = 'stock_transfers';

    /**
     * Add several products to the transfer sheet in one delivery: one shared
     * customer/driver/date, several stock_transfers rows (one per product) —
     * because each product still ships and is received independently, exactly
     * like a single entry does today.
     *
     * @param array $items list of ['product_id' => int, 'quantity' => float]
     * @return array|string list of inserted ids, or an error code
     */
    public static function addEntryBatch(array $shared, array $items, ?int $userId): array|string
    {
        $fromBranchId = (int)($shared['from_branch_id'] ?? 0);
        $toBranchId   = (int)($shared['to_branch_id'] ?? 0);
        $customerName = trim($shared['customer_name'] ?? '');
        $mobile       = trim($shared['customer_mobile'] ?? '');
        $driverName   = trim($shared['driver_name'] ?? '');
        $driverMobile = trim($shared['driver_mobile'] ?? '');
        $date         = trim($shared['transfer_date'] ?? '') ?: date('Y-m-d');

        if ($fromBranchId <= 0 || $toBranchId <= 0) return 'INVALID_BRANCH';
        if ($fromBranchId === $toBranchId)          return 'SAME_BRANCH';
        if ($customerName === '')                   return 'CUSTOMER_REQUIRED';
        if ($mobile === '')                          return 'MOBILE_REQUIRED';
        if ($driverName === '')                       return 'DRIVER_REQUIRED';
        if ($driverMobile === '')                     return 'DRIVER_MOBILE_REQUIRED';
        if (!strtotime($date))                        return 'INVALID_DATE';
        if (empty($items))                            return 'NO_ITEMS';

        $valid = [];
        foreach ($items as $it) {
            $productId = (int)($it['product_id'] ?? 0);
            $quantity  = (float)($it['quantity'] ?? 0);
            if ($productId <= 0) return 'INVALID_PRODUCT';
            if ($quantity <= 0)  return 'INVALID_QUANTITY';
            $valid[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        $address     = trim($shared['customer_address'] ?? '') ?: null;
        $orderManager = trim($shared['order_manager'] ?? '') ?: null;
        $note        = trim($shared['note'] ?? '') ?: null;

        $ids = [];
        Database::beginTransaction();
        try {
            foreach ($valid as $it) {
                $id = (int)Database::insert(
                    'INSERT INTO stock_transfers
                        (product_id, from_branch_id, to_branch_id, quantity, status, transfer_date,
                         customer_name, customer_address, customer_mobile, order_manager,
                         driver_name, driver_mobile, note, created_by)
                     VALUES (?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$it['product_id'], $fromBranchId, $toBranchId, $it['quantity'], $date,
                     $customerName, $address, $mobile, $orderManager,
                     $driverName, $driverMobile, $note, $userId]
                );
                $ids[] = $id;
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }

        self::log('transfer_entry_batch', 'stock_transfers', $ids[0] ?? 0,
                  count($ids) . ' transfer entries created (pending) for ' . $customerName);
        return $ids;
    }

    public static function addEntry(array $d, ?int $userId): int|string
    {
        $productId    = (int)($d['product_id'] ?? 0);
        $fromBranchId = (int)($d['from_branch_id'] ?? 0);
        $toBranchId   = (int)($d['to_branch_id'] ?? 0);
        $quantity     = (float)($d['quantity'] ?? 0);
        $customerName = trim($d['customer_name'] ?? '');
        $mobile       = trim($d['customer_mobile'] ?? '');
        $driverName   = trim($d['driver_name'] ?? '');
        $driverMobile = trim($d['driver_mobile'] ?? '');
        $date         = trim($d['transfer_date'] ?? '') ?: date('Y-m-d');

        if ($productId <= 0)                       return 'INVALID_PRODUCT';
        if ($fromBranchId <= 0 || $toBranchId <= 0) return 'INVALID_BRANCH';
        if ($fromBranchId === $toBranchId)          return 'SAME_BRANCH';
        if ($quantity <= 0)                         return 'INVALID_QUANTITY';
        if ($customerName === '')                   return 'CUSTOMER_REQUIRED';
        if ($mobile === '')                         return 'MOBILE_REQUIRED';
        if ($driverName === '')                     return 'DRIVER_REQUIRED';
        if ($driverMobile === '')                   return 'DRIVER_MOBILE_REQUIRED';
        if (!strtotime($date))                      return 'INVALID_DATE';

        $id = (int)Database::insert(
            'INSERT INTO stock_transfers
                (product_id, from_branch_id, to_branch_id, quantity, status, transfer_date,
                 customer_name, customer_address, customer_mobile, order_manager,
                 driver_name, driver_mobile, note, created_by)
             VALUES (?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$productId, $fromBranchId, $toBranchId, $quantity, $date,
             $customerName, trim($d['customer_address'] ?? '') ?: null, $mobile,
             trim($d['order_manager'] ?? '') ?: null,
             $driverName, $driverMobile, trim($d['note'] ?? '') ?: null, $userId]
        );
        self::log('transfer_entry', 'stock_transfers', $id,
                  "Transfer entry #$id created (pending)");
        return $id;
    }

    /**
     * A sheet for an earlier date is a record of what was sent that day, not
     * a working document. The spec allows exactly two things on it: finish a
     * transfer that was left pending, and correct one that came back as a
     * return. Deleting or rewriting a past pending entry is not among them.
     */
    private static function isPastSheet(array $entry): bool
    {
        return !empty($entry['transfer_date']) && $entry['transfer_date'] < date('Y-m-d');
    }

    public static function updateEntry(int $id, array $d): bool|string
    {
        $entry = Database::fetchOne('SELECT * FROM stock_transfers WHERE id = ? LIMIT 1', [$id]);
        if (!$entry) return 'NOT_FOUND';
        if (!in_array($entry['status'], ['pending', 'returned'], true)) return 'NOT_EDITABLE';
        // On a past sheet only a returned entry may be corrected.
        if (self::isPastSheet($entry) && $entry['status'] !== 'returned') return 'PAST_SHEET_LOCKED';

        $productId = (int)($d['product_id'] ?? $entry['product_id']);
        $quantity  = (float)($d['quantity'] ?? $entry['quantity']);
        if ($productId <= 0) return 'INVALID_PRODUCT';
        if ($quantity <= 0)  return 'INVALID_QUANTITY';

        Database::execute(
            'UPDATE stock_transfers SET
                product_id = ?, quantity = ?, customer_name = ?, customer_address = ?,
                customer_mobile = ?, order_manager = ?, driver_name = ?, driver_mobile = ?,
                note = ?
             WHERE id = ?',
            [$productId, $quantity,
             trim($d['customer_name']    ?? $entry['customer_name']),
             trim($d['customer_address'] ?? $entry['customer_address'] ?? '') ?: null,
             trim($d['customer_mobile']  ?? $entry['customer_mobile']),
             trim($d['order_manager']    ?? $entry['order_manager'] ?? '') ?: null,
             trim($d['driver_name']      ?? $entry['driver_name']),
             trim($d['driver_mobile']    ?? $entry['driver_mobile']),
             trim($d['note']             ?? $entry['note'] ?? '') ?: null,
             $id]
        );
        self::log('transfer_update', 'stock_transfers', $id, "Transfer entry #$id updated");
        return true;
    }

    public static function deleteEntry(int $id): bool|string
    {
        $entry = Database::fetchOne('SELECT * FROM stock_transfers WHERE id = ? LIMIT 1', [$id]);
        if (!$entry) return 'NOT_FOUND';
        if ($entry['status'] !== 'pending') return 'NOT_EDITABLE';
        if (self::isPastSheet($entry)) return 'PAST_SHEET_LOCKED';
        Database::execute('DELETE FROM stock_transfers WHERE id = ?', [$id]);
        self::log('transfer_delete', 'stock_transfers', $id, "Transfer entry #$id deleted");
        return true;
    }

    // pending/returned → sent (stock leaves the source branch)
    public static function send(int $id): bool|string
    {
        $entry = Database::fetchOne('SELECT * FROM stock_transfers WHERE id = ? LIMIT 1', [$id]);
        if (!$entry) return 'NOT_FOUND';
        if (!in_array($entry['status'], ['pending', 'returned'], true)) return 'ALREADY_SENT';

        $available = Stock::getCurrentBranchStock(
            (int)$entry['product_id'], (int)$entry['from_branch_id']
        );
        if ($available < (float)$entry['quantity']) return 'INSUFFICIENT_STOCK';

        Database::execute(
            'UPDATE stock_transfers SET status = "sent", return_note = NULL WHERE id = ?', [$id]
        );
        self::log('transfer_send', 'stock_transfers', $id, "Transfer #$id sent");
        return true;
    }

    // Receiving branch: In (received) or Return (back to sender)
    public static function receive(int $id, string $action, string $note, ?int $userId): bool|string
    {
        $entry = Database::fetchOne('SELECT * FROM stock_transfers WHERE id = ? LIMIT 1', [$id]);
        if (!$entry) return 'NOT_FOUND';
        if ($entry['status'] !== 'sent') return 'NOT_IN_TRANSIT';
        if (!in_array($action, ['in', 'return'], true)) return 'INVALID_ACTION';

        $newStatus = $action === 'in' ? 'received' : 'returned';
        Database::execute(
            'UPDATE stock_transfers
             SET status = ?, return_note = ?, received_by = ?, received_at = NOW()
             WHERE id = ?',
            [$newStatus, $action === 'return' ? (trim($note) ?: null) : null, $userId, $id]
        );
        self::log('transfer_' . $newStatus, 'stock_transfers', $id,
                  "Transfer #$id " . ($action === 'in' ? 'received' : 'returned'));
        return true;
    }

    // Date-wise list with status counts (৩.২)
    public static function getDates(?int $fromBranchId = null): array
    {
        $where  = '1=1';
        $params = [];
        if ($fromBranchId) { $where .= ' AND from_branch_id = ?'; $params[] = $fromBranchId; }
        return Database::fetchAll(
            "SELECT transfer_date,
                    COUNT(*) AS total,
                    SUM(status = 'pending')  AS pending_count,
                    SUM(status = 'sent')     AS sent_count,
                    SUM(status = 'received') AS received_count,
                    SUM(status = 'returned') AS returned_count
             FROM stock_transfers
             WHERE $where AND transfer_date IS NOT NULL
             GROUP BY transfer_date
             ORDER BY transfer_date DESC",
            $params
        );
    }

    public static function getSheet(string $date, ?int $fromBranchId = null): array
    {
        $where  = 't.transfer_date = ?';
        $params = [$date];
        if ($fromBranchId) { $where .= ' AND t.from_branch_id = ?'; $params[] = $fromBranchId; }
        return Database::fetchAll(
            "SELECT t.*, p.name AS product_name, p.unit,
                    fb.name AS from_branch_name, tb.name AS to_branch_name,
                    u.name AS created_by_name, ru.name AS received_by_name
             FROM stock_transfers t
             JOIN products p  ON p.id  = t.product_id
             JOIN branches fb ON fb.id = t.from_branch_id
             JOIN branches tb ON tb.id = t.to_branch_id
             LEFT JOIN users u  ON u.id  = t.created_by
             LEFT JOIN users ru ON ru.id = t.received_by
             WHERE $where
             ORDER BY t.id DESC",
            $params
        );
    }

    // Incoming transfers for the receiving branch (§4) — in transit only
    public static function getIncoming(int $branchId, string $date = ''): array
    {
        $where  = 't.to_branch_id = ? AND t.status = "sent"';
        $params = [$branchId];
        if ($date !== '') { $where .= ' AND t.transfer_date = ?'; $params[] = $date; }
        return Database::fetchAll(
            "SELECT t.*, p.name AS product_name, p.unit,
                    fb.name AS from_branch_name, tb.name AS to_branch_name
             FROM stock_transfers t
             JOIN products p  ON p.id  = t.product_id
             JOIN branches fb ON fb.id = t.from_branch_id
             JOIN branches tb ON tb.id = t.to_branch_id
             WHERE $where
             ORDER BY t.id DESC",
            $params
        );
    }

    // Customer / driver search (৩.৩) — matches name or mobile, groups by date
    public static function search(string $q, string $type = 'customer'): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $like = "%$q%";
        $cond = $type === 'driver'
            ? '(t.driver_name LIKE ? OR t.driver_mobile LIKE ?)'
            : '(t.customer_name LIKE ? OR t.customer_mobile LIKE ?)';
        return Database::fetchAll(
            "SELECT t.*, p.name AS product_name, p.unit,
                    fb.name AS from_branch_name, tb.name AS to_branch_name
             FROM stock_transfers t
             JOIN products p  ON p.id  = t.product_id
             JOIN branches fb ON fb.id = t.from_branch_id
             JOIN branches tb ON tb.id = t.to_branch_id
             WHERE $cond
             ORDER BY t.transfer_date DESC, t.id DESC
             LIMIT 200",
            [$like, $like]
        );
    }

    // Full lifecycle for one transfer entry (§4) — every status change ever
    // logged for it, so "receive" and "return" aren't a dead end: the record
    // stays visible after it drops off the in-transit list.
    public static function getHistory(int $id): array
    {
        return Database::fetchAll(
            "SELECT l.action, l.description, l.created_at, u.name AS user_name
             FROM activity_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.module = 'stock_transfers' AND l.reference_id = ?
             ORDER BY l.created_at ASC, l.id ASC",
            [$id]
        );
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NOT_FOUND'             => 'ট্রান্সফার এন্ট্রি খুঁজে পাওয়া যায়নি।',
            'INVALID_PRODUCT'       => 'সঠিক পণ্য নির্বাচন করুন।',
            'INVALID_BRANCH'        => 'সঠিক ব্রাঞ্চ নির্বাচন করুন।',
            'SAME_BRANCH'           => 'একই ব্রাঞ্চে ট্রান্সফার করা যায় না।',
            'INVALID_QUANTITY'      => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'CUSTOMER_REQUIRED'     => 'কাস্টমারের নাম দিন।',
            'MOBILE_REQUIRED'       => 'কাস্টমারের মোবাইল নাম্বার দিন।',
            'DRIVER_REQUIRED'       => 'ড্রাইভারের নাম দিন।',
            'DRIVER_MOBILE_REQUIRED'=> 'ড্রাইভারের মোবাইল নাম্বার দিন।',
            'INVALID_DATE'          => 'সঠিক তারিখ দিন।',
            'NOT_EDITABLE'          => 'শুধু পেন্ডিং/রিটার্ন এন্ট্রি পরিবর্তন করা যায়।',
            'ALREADY_SENT'          => 'এই এন্ট্রিটি ইতিমধ্যে পাঠানো হয়েছে।',
            'NOT_IN_TRANSIT'        => 'এই ট্রান্সফারটি বর্তমানে পথে নেই।',
            'INSUFFICIENT_STOCK'    => 'প্রেরক ব্রাঞ্চে পর্যাপ্ত স্টক নেই।',
            'INVALID_ACTION'        => 'ভুল অ্যাকশন।',
            'NO_ITEMS'              => 'কমপক্ষে একটি পণ্য যোগ করুন।',
            'DB_ERROR'              => 'সংরক্ষণ করা যায়নি, আবার চেষ্টা করুন।',
            'PAST_SHEET_LOCKED'     => 'পুরনো তারিখের শিটে শুধু অসম্পন্ন ট্রান্সফার সম্পন্ন করা এবং রিটার্ন সংশোধন করা যাবে।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
