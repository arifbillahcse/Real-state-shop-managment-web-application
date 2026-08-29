<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Customer.php';

class Payment extends BaseModel
{
    protected static string $table = 'payments';

    /**
     * Record a payment from a customer.
     * If $saleId is provided the payment is applied to that sale's due
     * and sales.paid_amount / sales.due_amount are updated accordingly.
     */
    public static function addPayment(
        int    $customerId,
        float  $amount,
        string $paymentMethod = 'cash',
        string $referenceNo   = '',
        string $paymentDate   = '',
        string $note          = '',
        ?int   $saleId        = null
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'CUSTOMER_NOT_FOUND';
        if ($amount <= 0) return 'INVALID_AMOUNT';

        $paymentDate = $paymentDate !== '' ? $paymentDate : today();
        $userId      = $_SESSION['user_id'] ?? null;

        if ($saleId !== null && $saleId > 0) {
            $sale = Database::fetchOne(
                'SELECT * FROM sales WHERE id = ? AND customer_id = ? AND status = ? LIMIT 1',
                [$saleId, $customerId, 'completed']
            );
            if (!$sale)                          return 'SALE_NOT_FOUND';
            if ((float)$sale['due_amount'] <= 0) return 'NO_DUE';
            if ($amount > (float)$sale['due_amount']) return 'EXCEEDS_DUE';

            $newPaid = (float)$sale['paid_amount'] + $amount;
            $newDue  = max(0, (float)$sale['total_amount'] - $newPaid);
            Database::execute(
                'UPDATE sales SET paid_amount = ?, due_amount = ? WHERE id = ?',
                [$newPaid, $newDue, $saleId]
            );
        } else {
            // No specific sale — apply to oldest outstanding sales (FIFO).
            // A branch-locked user may only settle their own branch's dues,
            // otherwise their payment would silently clear another branch's
            // invoice.
            $saleId    = null;
            $remaining = $amount;
            $branchId  = lockedBranchId();
            $pending   = Database::fetchAll(
                'SELECT id, paid_amount, due_amount, total_amount FROM sales
                 WHERE customer_id = ? AND status = ? AND due_amount > 0
                   AND ledger_id IS NULL' .
                 ($branchId !== null ? ' AND branch_id = ?' : '') . '
                 ORDER BY sale_date ASC, id ASC',
                $branchId !== null ? [$customerId, 'completed', $branchId]
                                   : [$customerId, 'completed']
            );
            foreach ($pending as $sale) {
                if ($remaining <= 0) break;
                $apply   = min($remaining, (float)$sale['due_amount']);
                $newPaid = (float)$sale['paid_amount'] + $apply;
                $newDue  = max(0, (float)$sale['total_amount'] - $newPaid);
                Database::execute(
                    'UPDATE sales SET paid_amount = ?, due_amount = ? WHERE id = ?',
                    [$newPaid, $newDue, $sale['id']]
                );
                $remaining -= $apply;
            }
        }

        $id = Database::insert(
            'INSERT INTO payments
             (customer_id, sale_id, amount, payment_method, reference_no,
              payment_date, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$customerId, $saleId, $amount, $paymentMethod,
             trim($referenceNo), $paymentDate, trim($note), $userId]
        );

        self::log('add_payment', 'payments', (int)$id,
            "Payment $amount from customer #$customerId");
        return (int)$id;
    }

    public static function getPayments(array $filters = []): array
    {
        $sql    = 'SELECT p.*, c.name AS customer_name, s.invoice_number,
                          u.name AS created_by_name
                   FROM payments p
                   JOIN customers c  ON c.id = p.customer_id
                   LEFT JOIN sales s ON s.id = p.sale_id
                   LEFT JOIN users u ON u.id = p.created_by
                   WHERE 1=1';
        $params = [];

        // A branch-locked user sees only payments that belong to their branch:
        // either the payment is tied to one of their sales, or it is a loose
        // (FIFO) payment from a customer who trades with their branch.
        $branchId = lockedBranchId();
        if ($branchId !== null) {
            $sql .= ' AND (s.branch_id = ?
                           OR (p.sale_id IS NULL AND EXISTS (
                                 SELECT 1 FROM sales s2
                                 WHERE s2.customer_id = p.customer_id
                                   AND s2.branch_id = ? AND s2.status = "completed")))';
            $params[] = $branchId;
            $params[] = $branchId;
        }

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND p.customer_id = ?'; $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND p.payment_date >= ?'; $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND p.payment_date <= ?'; $params[] = $filters['date_to'];
        }

        $sql .= ' ORDER BY p.payment_date DESC, p.id DESC';
        return Database::fetchAll($sql, $params);
    }

    /**
     * Outstanding (due > 0) sales for a customer — used by the payment form.
     */
    public static function getOutstandingSales(int $customerId): array
    {
        $branchId = lockedBranchId();
        return Database::fetchAll(
            'SELECT id, invoice_number, sale_date, total_amount, paid_amount, due_amount
             FROM sales
             WHERE customer_id = ? AND status = ? AND due_amount > 0
               AND ledger_id IS NULL' .
             ($branchId !== null ? ' AND branch_id = ?' : '') . '
             ORDER BY sale_date ASC, id ASC',
            $branchId !== null ? [$customerId, 'completed', $branchId]
                               : [$customerId, 'completed']
        );
    }

    /**
     * Full ledger for a customer: sales + payments, sorted by date.
     */
    public static function getCustomerLedger(int $customerId): array
    {
        $customer = Customer::getCustomerById($customerId);
        if (!$customer) return [];

        // Branch-locked users see only the part of the ledger that happened at
        // their branch, and a summary computed from that same slice — so the
        // totals always agree with the rows shown above them.
        $branchId = lockedBranchId();

        // A sale pushed into the customer's khata (ledger_id set) is excluded
        // here — its goods debit and any paid-at-the-counter deposit are shown
        // below as customer_ledger rows instead. Showing both the sales row
        // and its ledger copy would count the same money twice.
        $sales = Database::fetchAll(
            'SELECT id, invoice_number, sale_date AS txn_date,
                    total_amount, paid_amount, due_amount, payment_method, status
             FROM sales
             WHERE customer_id = ? AND status = ? AND ledger_id IS NULL' .
             ($branchId !== null ? ' AND branch_id = ?' : '') . '
             ORDER BY sale_date ASC, id ASC',
            $branchId !== null ? [$customerId, 'completed', $branchId]
                               : [$customerId, 'completed']
        );

        // Same reasoning: a collection made against a since-pushed sale would
        // otherwise show up twice — once here, once as the ledger's own
        // deposit entry for that push.
        $payParams = [$customerId];
        $paySql    = 'SELECT p.id, p.payment_date AS txn_date, p.amount,
                             p.payment_method, p.reference_no, p.note,
                             s.invoice_number AS linked_invoice
                      FROM payments p
                      LEFT JOIN sales s ON s.id = p.sale_id
                      WHERE p.customer_id = ?
                        AND (p.sale_id IS NULL OR s.ledger_id IS NULL)';
        if ($branchId !== null) {
            $paySql .= ' AND (s.branch_id = ? OR p.sale_id IS NULL)';
            $payParams[] = $branchId;
        }
        $paySql .= ' ORDER BY p.payment_date ASC, p.id ASC';
        $payments = Database::fetchAll($paySql, $payParams);

        // মালামাল এন্ট্রি, টাকা জমা, টাকা ফেরত, রিটার্ন পণ্য, অন্যান্য খরচ — everything
        // recorded straight into the khata (customer_account.php), plus the
        // goods+deposit pair a pushed sale left behind. No branch column on
        // this table, so it isn't scoped by branch — same as the account page.
        $ledger = Database::fetchAll(
            'SELECT id, entry_type, entry_date AS txn_date, debit, credit, note
             FROM customer_ledger
             WHERE customer_id = ? AND status = "final"
             ORDER BY entry_date ASC, id ASC',
            [$customerId]
        );

        // customer_ledger carries no branch column, so its balance is never
        // branch-scoped anywhere in the app (customer_account.php doesn't
        // scope it either) — it's added in full regardless of $branchId.
        // Clamped at zero for the same reason Customer::outstanding() clamps
        // it: an advance sitting in the khata should not cancel out unrelated
        // invoice dues from a different branch.
        $ledgerBalance = array_sum(array_column($ledger, 'debit'))
                       - array_sum(array_column($ledger, 'credit'));

        $summary = [
            'total_purchase' => array_sum(array_column($sales, 'total_amount'))
                              + array_sum(array_column(
                                    array_filter($ledger, fn($l) => $l['entry_type'] === 'goods'),
                                    'debit')),
            'total_paid'     => array_sum(array_column($sales, 'paid_amount'))
                              + array_sum(array_column($payments, 'amount'))
                              + array_sum(array_column(
                                    array_filter($ledger, fn($l) => $l['entry_type'] === 'deposit'),
                                    'credit')),
            // Branch-scoped sales due (the rows actually shown above) plus
            // the ledger balance — same two-source shape as
            // Customer::outstanding(), but honoring the branch lock rather
            // than always going global.
            'total_due'      => array_sum(array_column($sales, 'due_amount'))
                              + max(0, $ledgerBalance),
        ];

        return compact('customer', 'sales', 'payments', 'ledger', 'summary');
    }

    public static function errorMessage(string $code): string
    {
        return [
            'CUSTOMER_NOT_FOUND' => 'কাস্টমার খুঁজে পাওয়া যায়নি।',
            'INVALID_AMOUNT'     => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'SALE_NOT_FOUND'     => 'বিক্রয় রেকর্ড খুঁজে পাওয়া যায়নি।',
            'NO_DUE'             => 'এই বিক্রয়ের কোনো বাকি নেই।',
            'EXCEEDS_DUE'        => 'পরিমাণ বাকির চেয়ে বেশি হতে পারবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
