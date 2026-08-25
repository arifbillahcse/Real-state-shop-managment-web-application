<?php

require_once __DIR__ . '/BaseModel.php';

class Customer extends BaseModel
{
    protected static string $table = 'customers';

    public static function addCustomer(
        string $name, string $phone = '', string $address = '',
        array  $extra = []
    ): int|string {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';

        $accountType = ($extra['account_type'] ?? 'full') === 'short' ? 'short' : 'full';
        $bookNo      = trim($extra['book_no'] ?? '');
        $whatsapp    = trim($extra['whatsapp'] ?? '');
        $imo         = trim($extra['imo'] ?? '');
        $photo       = trim($extra['photo'] ?? '');
        $dueLimit    = max(0, (float)($extra['due_limit'] ?? 0));

        // Account no: serial within the selected book (e.g. book 12 → 12/1, 12/2 …).
        // Retry on unique-key collision so two concurrent creates can't share a number.
        $attempts = 0;
        do {
            $accountNo = $bookNo !== '' ? self::nextAccountNo($bookNo) : null;
            try {
                $id = Database::insert(
                    'INSERT INTO customers
                        (name, phone, whatsapp, imo, address, photo,
                         book_no, account_no, account_type, due_limit)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, trim($phone), $whatsapp ?: null, $imo ?: null,
                     trim($address), $photo ?: null,
                     $bookNo ?: null, $accountNo, $accountType, $dueLimit]
                );
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $accountNo !== null && ++$attempts < 5) {
                    continue; // account_no collided — recompute and retry
                }
                throw $e;
            }
        } while (true);

        $id = (int)$id;

        // Extra phone numbers
        foreach (($extra['phones'] ?? []) as $p) {
            $p = trim($p);
            if ($p !== '') {
                Database::execute(
                    'INSERT INTO customer_phones (customer_id, phone) VALUES (?, ?)',
                    [$id, $p]
                );
            }
        }

        self::log('add_customer', 'customers', $id, "Added customer: $name");
        return $id;
    }

    // Next serial account number within a book: "<book>/<n>"
    public static function nextAccountNo(string $bookNo): string
    {
        $row = Database::fetchOne(
            'SELECT MAX(CAST(SUBSTRING_INDEX(account_no, "/", -1) AS UNSIGNED)) AS mx
             FROM customers WHERE book_no = ?',
            [$bookNo]
        );
        $next = (int)($row['mx'] ?? 0) + 1;
        return $bookNo . '/' . $next;
    }

    /**
     * Customer list.
     *
     * Customers themselves are shared across the whole business (there is no
     * customers.branch_id, and the same person may buy from several
     * branches). So for a branch-locked user we don't filter the customer
     * record — we filter by RELATIONSHIP: only customers who have actually
     * transacted with their branch, with the totals limited to that branch's
     * sales. The result reads as "my branch's customers" while the
     * underlying customer data stays shared and intact.
     */
    /**
     * SQL for the khata half of what a customer owes.
     *
     * A receivable lives in one of two places: an invoice that was never moved
     * into the customer's khata, or the khata itself once a memo was pushed
     * there. Neither alone is "the due", so both are summed wherever an
     * outstanding figure is shown. Clamped at zero because a customer sitting
     * on an advance should not have that advance quietly cancel out unrelated
     * invoices.
     */
    private const LEDGER_DUE_SQL = '
        GREATEST(COALESCE((SELECT SUM(l.debit) - SUM(l.credit)
                           FROM customer_ledger l
                           WHERE l.customer_id = c.id AND l.status = "final"), 0), 0)';

    /** What this customer owes in total — unmoved invoices plus khata balance. */
    public static function outstanding(int $customerId): float
    {
        $row = Database::fetchOne(
            'SELECT COALESCE((SELECT SUM(s.due_amount) FROM sales s
                              WHERE s.customer_id = c.id AND s.status = "completed"
                                AND s.ledger_id IS NULL), 0)
                    + ' . self::LEDGER_DUE_SQL . ' AS due
             FROM customers c WHERE c.id = ? LIMIT 1',
            [$customerId]
        );
        return (float)($row['due'] ?? 0);
    }

    public static function getCustomers(): array
    {
        $branchId = lockedBranchId();

        if ($branchId !== null) {
            return Database::fetchAll(
                'SELECT c.*,
                        COALESCE(SUM(s.due_amount), 0) + ' . self::LEDGER_DUE_SQL . ' AS total_due,
                        COALESCE(SUM(s.total_amount), 0) AS total_purchase
                 FROM customers c
                 JOIN sales s ON s.customer_id = c.id
                              AND s.status = "completed" AND s.branch_id = ?
                              AND s.ledger_id IS NULL
                 WHERE c.is_active = 1
                 GROUP BY c.id
                 ORDER BY c.name',
                [$branchId]
            );
        }

        return Database::fetchAll(
            'SELECT c.*,
                    COALESCE(d.total_due, 0) + ' . self::LEDGER_DUE_SQL . ' AS total_due,
                    COALESCE(d.total_purchase, 0) AS total_purchase
             FROM customers c
             LEFT JOIN vw_customer_dues d ON d.customer_id = c.id
             WHERE c.is_active = 1
             ORDER BY c.name'
        );
    }

    /**
     * May the current user open this customer's account page?
     *
     * Unrestricted users always may. A branch-locked user may open a customer
     * that has traded with their branch — and also one that has no sales
     * anywhere yet, because that is exactly the customer they just created and
     * are about to bill. Only customers who trade solely with OTHER branches
     * are hidden from them.
     */
    public static function isVisibleToCurrentUser(int $id): bool
    {
        $branchId = lockedBranchId();
        if ($branchId === null) return true;

        $row = Database::fetchOne(
            'SELECT
                COUNT(*) AS total,
                SUM(branch_id = ?) AS mine
             FROM sales WHERE customer_id = ? AND status = "completed"',
            [$branchId, $id]
        );
        return (int)($row['total'] ?? 0) === 0 || (int)($row['mine'] ?? 0) > 0;
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

        $name     = trim($data['name']     ?? $customer['name']);
        $phone    = trim($data['phone']    ?? $customer['phone'] ?? '');
        $address  = trim($data['address']  ?? $customer['address'] ?? '');
        $whatsapp = trim($data['whatsapp'] ?? $customer['whatsapp'] ?? '');
        $imo      = trim($data['imo']      ?? $customer['imo'] ?? '');
        $photo    = array_key_exists('photo', $data)
                  ? trim((string)$data['photo'])
                  : (string)($customer['photo'] ?? '');
        $dueLimit = isset($data['due_limit'])
                  ? max(0, (float)$data['due_limit'])
                  : (float)($customer['due_limit'] ?? 0);

        if ($name === '') return 'NAME_REQUIRED';

        Database::execute(
            'UPDATE customers SET name = ?, phone = ?, whatsapp = ?, imo = ?,
                    address = ?, photo = ?, due_limit = ? WHERE id = ?',
            [$name, $phone, $whatsapp ?: null, $imo ?: null,
             $address, $photo ?: null, $dueLimit, $id]
        );

        // Replace extra phones if provided
        if (array_key_exists('phones', $data) && is_array($data['phones'])) {
            Database::execute('DELETE FROM customer_phones WHERE customer_id = ?', [$id]);
            foreach ($data['phones'] as $p) {
                $p = trim($p);
                if ($p !== '') {
                    Database::execute(
                        'INSERT INTO customer_phones (customer_id, phone) VALUES (?, ?)',
                        [$id, $p]
                    );
                }
            }
        }

        self::log('update_customer', 'customers', $id, "Updated customer: $name");
        return true;
    }

    // Short account → Full account. Book/account no is assigned here if missing.
    public static function upgradeToFull(int $id, string $bookNo = ''): bool|string
    {
        $customer = self::getCustomerById($id);
        if (!$customer) return 'NOT_FOUND';
        if (($customer['account_type'] ?? 'full') === 'full') return 'ALREADY_FULL';

        $bookNo = trim($bookNo) !== '' ? trim($bookNo) : (string)($customer['book_no'] ?? '');
        $accountNo = $customer['account_no'];
        if ($accountNo === null && $bookNo !== '') {
            $accountNo = self::nextAccountNo($bookNo);
        }

        Database::execute(
            'UPDATE customers SET account_type = "full", book_no = ?, account_no = ? WHERE id = ?',
            [$bookNo ?: null, $accountNo, $id]
        );
        self::log('upgrade_customer', 'customers', $id, "Upgraded to full account: {$customer['name']}");
        return true;
    }

    // ── Extra phones ────────────────────────────────────────────────────────
    public static function getPhones(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT id, phone FROM customer_phones WHERE customer_id = ? ORDER BY id',
            [$customerId]
        );
    }

    // ── References ──────────────────────────────────────────────────────────
    public static function getReferences(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.name AS ref_user_name
             FROM customer_references r
             LEFT JOIN users u ON u.id = r.ref_user_id
             WHERE r.customer_id = ?
             ORDER BY r.id',
            [$customerId]
        );
    }

    public static function addReference(
        int $customerId, string $name, string $address = '',
        string $phone = '', string $photo = '', ?int $refUserId = null
    ): int|string {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';
        if (!self::getCustomerById($customerId)) return 'NOT_FOUND';

        $id = Database::insert(
            'INSERT INTO customer_references
                (customer_id, ref_user_id, name, address, phone, photo)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$customerId, $refUserId, $name, trim($address) ?: null,
             trim($phone) ?: null, trim($photo) ?: null]
        );
        self::log('add_customer_reference', 'customers', $customerId,
                  "Added reference '$name' for customer #$customerId");
        return (int)$id;
    }

    public static function deleteReference(int $refId): bool
    {
        Database::execute('DELETE FROM customer_references WHERE id = ?', [$refId]);
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
            'NOTE_REQUIRED' => 'নোট লিখুন।',
            'ALREADY_FULL'  => 'এই একাউন্টটি ইতিমধ্যে ফুল একাউন্ট।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }

    // ── Customer account notes ──────────────────────────────────────────────

    public static function getNotes(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT cn.id, cn.note, cn.created_at, u.name AS author
             FROM customer_notes cn
             LEFT JOIN users u ON u.id = cn.created_by
             WHERE cn.customer_id = ?
             ORDER BY cn.created_at DESC',
            [$customerId]
        );
    }

    public static function addNote(int $customerId, string $note, ?int $userId): int|string
    {
        $note = trim($note);
        if ($note === '') return 'NOTE_REQUIRED';

        $customer = self::getCustomerById($customerId);
        if (!$customer) return 'NOT_FOUND';

        $id = Database::insert(
            'INSERT INTO customer_notes (customer_id, note, created_by) VALUES (?, ?, ?)',
            [$customerId, $note, $userId]
        );
        self::log('add_customer_note', 'customers', $customerId, "Added note for customer #$customerId");
        return (int)$id;
    }

    public static function deleteNote(int $noteId): bool
    {
        Database::execute('DELETE FROM customer_notes WHERE id = ?', [$noteId]);
        return true;
    }
}
