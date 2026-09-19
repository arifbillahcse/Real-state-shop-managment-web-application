<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Customer.php';

/**
 * Customer ledger — single source of truth for customer accounts.
 *
 * Convention:
 *   debit  = customer owes more  (goods taken, expense paid on their behalf,
 *            money returned to them, due transferred in)
 *   credit = customer owes less  (money deposited, product returned)
 *   balance = SUM(debit) - SUM(credit)   [status = 'final' only]
 *
 * Final entries are immutable — corrections are new offsetting entries.
 * Draft (pending) goods memos may be edited/finalized/deleted.
 */
class Ledger extends BaseModel
{
    protected static string $table = 'customer_ledger';

    // ── Balance ──────────────────────────────────────────────────────────────
    public static function balance(int $customerId): float
    {
        $row = Database::fetchOne(
            'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal
             FROM customer_ledger
             WHERE customer_id = ? AND status = "final"',
            [$customerId]
        );
        return (float)($row['bal'] ?? 0);
    }

    // Balance before a given date (পূর্বের বাকি)
    public static function balanceBefore(int $customerId, string $date): float
    {
        $row = Database::fetchOne(
            'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal
             FROM customer_ledger
             WHERE customer_id = ? AND status = "final" AND entry_date < ?',
            [$customerId, $date]
        );
        return (float)($row['bal'] ?? 0);
    }

    // ── Goods entry (মালামাল এন্ট্রি) ─────────────────────────────────────────
    // $items: [{product_id, product_name, quantity, unit, unit_price,
    //           unload_bill?, labor_bill?, transport_bill?}, ...]
    // Combined charges go on the entry; per-item charges on each line.
    public static function addGoods(
        int $customerId, string $date, array $items,
        float $unloadBill, float $laborBill, float $transportBill,
        string $note, bool $finalize, ?int $userId
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'NOT_FOUND';
        if (empty($items)) return 'ITEMS_REQUIRED';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        $itemsTotal   = 0.0;
        $itemCharges  = 0.0;
        $cleanItems   = [];
        foreach ($items as $it) {
            $qty   = (float)($it['quantity'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $name  = trim($it['product_name'] ?? '');
            if ($qty <= 0 || $price < 0 || $name === '') return 'INVALID_ITEM';
            $lineTotal = round($qty * $price, 2);
            $u = max(0, (float)($it['unload_bill'] ?? 0));
            $l = max(0, (float)($it['labor_bill'] ?? 0));
            $t = max(0, (float)($it['transport_bill'] ?? 0));
            $itemsTotal  += $lineTotal;
            $itemCharges += $u + $l + $t;
            $cleanItems[] = [
                'product_id' => (int)($it['product_id'] ?? 0) ?: null,
                'product_name' => $name,
                'quantity' => $qty,
                'unit' => trim($it['unit'] ?? ''),
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'unload_bill' => $u, 'labor_bill' => $l, 'transport_bill' => $t,
            ];
        }

        $unloadBill    = max(0, $unloadBill);
        $laborBill     = max(0, $laborBill);
        $transportBill = max(0, $transportBill);
        $debit = round($itemsTotal + $itemCharges + $unloadBill + $laborBill + $transportBill, 2);

        Database::beginTransaction();
        try {
            $ledgerId = (int)Database::insert(
                'INSERT INTO customer_ledger
                    (customer_id, entry_type, entry_date, debit, credit,
                     unload_bill, labor_bill, transport_bill, note, status, created_by)
                 VALUES (?, "goods", ?, ?, 0, ?, ?, ?, ?, ?, ?)',
                [$customerId, $date, $debit,
                 $unloadBill, $laborBill, $transportBill,
                 trim($note) ?: null, $finalize ? 'final' : 'pending', $userId]
            );
            foreach ($cleanItems as $ci) {
                Database::insert(
                    'INSERT INTO customer_ledger_items
                        (ledger_id, product_id, product_name, quantity, unit, unit_price,
                         line_total, unload_bill, labor_bill, transport_bill)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$ledgerId, $ci['product_id'], $ci['product_name'], $ci['quantity'],
                     $ci['unit'], $ci['unit_price'], $ci['line_total'],
                     $ci['unload_bill'], $ci['labor_bill'], $ci['transport_bill']]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('Ledger addGoods failed: ' . $e->getMessage());
            return 'DB_ERROR';
        }

        self::log('ledger_goods', 'customer_ledger', $ledgerId,
                  ($finalize ? 'Goods entry' : 'Draft memo') . " for customer #$customerId: $debit");
        return $ledgerId;
    }

    // ── Money deposit (টাকা জমা) — triggers SMS hook ─────────────────────────
    public static function addDeposit(
        int $customerId, string $date, float $amount,
        string $method, string $note, ?int $userId
    ): int|string {
        $customer = Customer::getCustomerById($customerId);
        if (!$customer) return 'NOT_FOUND';
        if ($amount <= 0) return 'INVALID_AMOUNT';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        $noteFull = trim($method) !== '' ? trim("[$method] " . $note) : trim($note);

        // Credit the assigned collector's khata if this customer's due
        // was transferred to a staff member (§8 হিসাব ট্রান্সফার)
        require_once __DIR__ . '/Staff.php';
        $collectedBy = Staff::getActiveCollector($customerId);

        $ledgerId = (int)Database::insert(
            'INSERT INTO customer_ledger
                (customer_id, entry_type, entry_date, debit, credit, note, status,
                 created_by, collected_by)
             VALUES (?, "deposit", ?, 0, ?, ?, "final", ?, ?)',
            [$customerId, $date, $amount, $noteFull ?: null, $userId, $collectedBy]
        );

        // Auto SMS: deposit amount, date, current balance
        if (!empty($customer['phone'])) {
            require_once __DIR__ . '/Notifier.php';
            $balance = self::balance($customerId);
            Notifier::send(
                $customer['phone'],
                "টাকা জমা: " . number_format($amount, 2) . " ৳, তারিখ: $date, " .
                "বর্তমান ব্যালেন্স: " . number_format($balance, 2) . " ৳",
                'sms', 'customer_ledger', $ledgerId
            );
        }

        self::log('ledger_deposit', 'customer_ledger', $ledgerId,
                  "Deposit for customer #$customerId: $amount");
        return $ledgerId;
    }

    // ── Money return (টাকা ফেরত) ─────────────────────────────────────────────
    public static function addMoneyReturn(
        int $customerId, string $date, float $amount,
        string $reason, string $receivedBy, ?int $userId
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'NOT_FOUND';
        if ($amount <= 0) return 'INVALID_AMOUNT';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        $ledgerId = (int)Database::insert(
            'INSERT INTO customer_ledger
                (customer_id, entry_type, entry_date, debit, credit,
                 note, received_by, status, created_by)
             VALUES (?, "money_return", ?, ?, 0, ?, ?, "final", ?)',
            [$customerId, $date, $amount, trim($reason) ?: null,
             trim($receivedBy) ?: null, $userId]
        );
        self::log('ledger_money_return', 'customer_ledger', $ledgerId,
                  "Money return for customer #$customerId: $amount");
        return $ledgerId;
    }

    // ── Product return (রিটার্ন পণ্য) — restocks + credits at chosen rate ────
    /**
     * $branchId: which branch physically takes the goods back.
     *
     * Required, because branch stock is counted by matching branch_id — an
     * adjustment with no branch belongs to no branch's shelf, so the returned
     * goods would show up in the global total while every branch still read
     * as if nothing had come back.
     */
    public static function addProductReturn(
        int $customerId, string $date, array $items, string $note, ?int $userId,
        ?int $branchId = null
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'NOT_FOUND';
        if (empty($items)) return 'ITEMS_REQUIRED';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        if ($branchId !== null) {
            $br = Database::fetchOne(
                'SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]
            );
            if (!$br) return 'INVALID_BRANCH';
        } elseif (Database::fetchOne('SELECT id FROM branches WHERE is_active = 1 LIMIT 1')) {
            // Single-branch installs have no branches row at all and are fine
            // without one; once branches exist, the goods have to land in one.
            return 'BRANCH_REQUIRED';
        }

        $total = 0.0;
        $cleanItems = [];
        foreach ($items as $it) {
            $qty   = (float)($it['quantity'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $name  = trim($it['product_name'] ?? '');
            if ($qty <= 0 || $price < 0 || $name === '') return 'INVALID_ITEM';
            $lineTotal = round($qty * $price, 2);
            $total += $lineTotal;
            $cleanItems[] = [
                'product_id' => (int)($it['product_id'] ?? 0) ?: null,
                'product_name' => $name, 'quantity' => $qty,
                'unit' => trim($it['unit'] ?? ''), 'unit_price' => $price,
                'line_total' => $lineTotal,
            ];
        }

        Database::beginTransaction();
        try {
            $ledgerId = (int)Database::insert(
                'INSERT INTO customer_ledger
                    (customer_id, entry_type, entry_date, debit, credit, note, status, created_by)
                 VALUES (?, "product_return", ?, 0, ?, ?, "final", ?)',
                [$customerId, $date, round($total, 2), trim($note) ?: null, $userId]
            );
            foreach ($cleanItems as $ci) {
                Database::insert(
                    'INSERT INTO customer_ledger_items
                        (ledger_id, product_id, product_name, quantity, unit, unit_price, line_total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$ledgerId, $ci['product_id'], $ci['product_name'], $ci['quantity'],
                     $ci['unit'], $ci['unit_price'], $ci['line_total']]
                );
                // Returned goods go back on the shelf of the branch that took them
                if ($ci['product_id']) {
                    Database::insert(
                        'INSERT INTO stock_adjustments
                            (product_id, branch_id, quantity, reason, note, created_by)
                         VALUES (?, ?, ?, "return", ?, ?)',
                        [$ci['product_id'], $branchId, $ci['quantity'],
                         "কাস্টমার #$customerId রিটার্ন (লেজার #$ledgerId)", $userId]
                    );
                }
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('Ledger addProductReturn failed: ' . $e->getMessage());
            return 'DB_ERROR';
        }

        self::log('ledger_product_return', 'customer_ledger', $ledgerId,
                  "Product return for customer #$customerId: $total");
        return $ledgerId;
    }

    // ── Other expense (অন্যান্য খরচ) ─────────────────────────────────────────
    public static function addExpense(
        int $customerId, string $date, float $amount, string $description, ?int $userId
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'NOT_FOUND';
        if ($amount <= 0) return 'INVALID_AMOUNT';
        if (trim($description) === '') return 'NOTE_REQUIRED';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        $ledgerId = (int)Database::insert(
            'INSERT INTO customer_ledger
                (customer_id, entry_type, entry_date, debit, credit, note, status, created_by)
             VALUES (?, "expense", ?, ?, 0, ?, "final", ?)',
            [$customerId, $date, $amount, trim($description), $userId]
        );
        self::log('ledger_expense', 'customer_ledger', $ledgerId,
                  "Expense for customer #$customerId: $amount");
        return $ledgerId;
    }

    // ── Draft memo handling ──────────────────────────────────────────────────
    public static function finalizeDraft(int $ledgerId, string $date): bool|string
    {
        $entry = Database::fetchOne(
            'SELECT * FROM customer_ledger WHERE id = ? LIMIT 1', [$ledgerId]
        );
        if (!$entry) return 'NOT_FOUND';
        if ($entry['status'] !== 'pending') return 'NOT_DRAFT';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        Database::execute(
            'UPDATE customer_ledger SET status = "final", entry_date = ? WHERE id = ?',
            [$date, $ledgerId]
        );
        self::log('ledger_finalize', 'customer_ledger', $ledgerId, "Draft memo finalized");
        return true;
    }

    public static function deleteDraft(int $ledgerId): bool|string
    {
        $entry = Database::fetchOne(
            'SELECT * FROM customer_ledger WHERE id = ? LIMIT 1', [$ledgerId]
        );
        if (!$entry) return 'NOT_FOUND';
        // Only drafts are deletable — final entries are immutable
        if ($entry['status'] !== 'pending') return 'NOT_DRAFT';

        Database::execute('DELETE FROM customer_ledger WHERE id = ?', [$ledgerId]);
        self::log('ledger_delete_draft', 'customer_ledger', $ledgerId, "Draft memo deleted");
        return true;
    }

    // ── Reads ────────────────────────────────────────────────────────────────
    public static function getEntries(int $customerId, bool $includeDrafts = true): array
    {
        $where = 'l.customer_id = ?';
        if (!$includeDrafts) $where .= ' AND l.status = "final"';
        $entries = Database::fetchAll(
            "SELECT l.*, u.name AS created_by_name
             FROM customer_ledger l
             LEFT JOIN users u ON u.id = l.created_by
             WHERE $where
             ORDER BY l.entry_date ASC, l.id ASC",
            [$customerId]
        );
        // Attach items + running balance (finals only feed the balance)
        $running = 0.0;
        foreach ($entries as &$e) {
            if ($e['status'] === 'final') {
                $running += (float)$e['debit'] - (float)$e['credit'];
            }
            $e['running_balance'] = round($running, 2);
            $e['items'] = in_array($e['entry_type'], ['goods', 'product_return'], true)
                ? Database::fetchAll(
                    'SELECT * FROM customer_ledger_items WHERE ledger_id = ? ORDER BY id',
                    [$e['id']]
                  )
                : [];
        }
        return $entries;
    }

    // Historical purchase rates of one product for one customer (return flow)
    public static function getProductRates(int $customerId, int $productId): array
    {
        return Database::fetchAll(
            '(SELECT DISTINCT cli.unit_price AS rate, l.entry_date AS used_on, "খাতা" AS source
              FROM customer_ledger_items cli
              JOIN customer_ledger l ON l.id = cli.ledger_id
              WHERE l.customer_id = ? AND cli.product_id = ?
                AND l.entry_type = "goods" AND l.status = "final")
             UNION
             (SELECT DISTINCT si.unit_price AS rate, s.sale_date AS used_on, "ইনভয়েস" AS source
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
              WHERE s.customer_id = ? AND si.product_id = ? AND s.status = "completed")
             ORDER BY used_on DESC
             LIMIT 20',
            [$customerId, $productId, $customerId, $productId]
        );
    }

    // Per-product totals for a customer (কত ব্যাগ সিমেন্ট নিয়েছে…)
    public static function getProductSummary(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT cli.product_name, cli.unit,
                    SUM(CASE WHEN l.entry_type = "goods" THEN cli.quantity ELSE 0 END) AS taken_qty,
                    SUM(CASE WHEN l.entry_type = "product_return" THEN cli.quantity ELSE 0 END) AS returned_qty,
                    SUM(CASE WHEN l.entry_type = "goods" THEN cli.line_total ELSE 0 END) AS taken_value
             FROM customer_ledger_items cli
             JOIN customer_ledger l ON l.id = cli.ledger_id
             WHERE l.customer_id = ? AND l.status = "final"
             GROUP BY cli.product_name, cli.unit
             ORDER BY taken_value DESC',
            [$customerId]
        );
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NOT_FOUND'       => 'কাস্টমার/এন্ট্রি খুঁজে পাওয়া যায়নি।',
            'ITEMS_REQUIRED'  => 'কমপক্ষে একটি পণ্য যোগ করুন।',
            'INVALID_ITEM'    => 'পণ্যের নাম, পরিমাণ ও দাম সঠিকভাবে দিন।',
            'INVALID_AMOUNT'  => 'টাকার পরিমাণ ০ এর বেশি হতে হবে।',
            'INVALID_DATE'    => 'সঠিক তারিখ দিন।',
            'NOTE_REQUIRED'   => 'বিবরণ লিখুন।',
            'BRANCH_REQUIRED' => 'পণ্য কোন ব্রাঞ্চের স্টকে ফেরত যাবে তা নির্বাচন করুন।',
            'INVALID_BRANCH'  => 'সঠিক ব্রাঞ্চ নির্বাচন করুন।',
            'NOT_DRAFT'       => 'শুধুমাত্র খসড়া মেমো পরিবর্তন/ডিলিট করা যায়।',
            'DB_ERROR'        => 'ডাটাবেজে সমস্যা হয়েছে। আবার চেষ্টা করুন।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
