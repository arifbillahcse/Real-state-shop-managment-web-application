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
            if (!$sale)                        return 'SALE_NOT_FOUND';
            if ((float)$sale['due_amount'] <= 0) return 'NO_DUE';
            if ($amount > (float)$sale['due_amount']) return 'EXCEEDS_DUE';

            $newPaid = (float)$sale['paid_amount'] + $amount;
            $newDue  = max(0, (float)$sale['total_amount'] - $newPaid);
            Database::execute(
                'UPDATE sales SET paid_amount = ?, due_amount = ? WHERE id = ?',
                [$newPaid, $newDue, $saleId]
            );
        } else {
            $saleId = null;
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
        return Database::fetchAll(
            'SELECT id, invoice_number, sale_date, total_amount, paid_amount, due_amount
             FROM sales
             WHERE customer_id = ? AND status = ? AND due_amount > 0
             ORDER BY sale_date ASC, id ASC',
            [$customerId, 'completed']
        );
    }

    /**
     * Full ledger for a customer: sales + payments, sorted by date.
     */
    public static function getCustomerLedger(int $customerId): array
    {
        $customer = Customer::getCustomerById($customerId);
        if (!$customer) return [];

        $sales = Database::fetchAll(
            'SELECT id, invoice_number, sale_date AS txn_date,
                    total_amount, paid_amount, due_amount, payment_method, status
             FROM sales
             WHERE customer_id = ? AND status = ?
             ORDER BY sale_date ASC, id ASC',
            [$customerId, 'completed']
        );

        $payments = Database::fetchAll(
            'SELECT p.id, p.payment_date AS txn_date, p.amount,
                    p.payment_method, p.reference_no, p.note,
                    s.invoice_number AS linked_invoice
             FROM payments p
             LEFT JOIN sales s ON s.id = p.sale_id
             WHERE p.customer_id = ?
             ORDER BY p.payment_date ASC, p.id ASC',
            [$customerId]
        );

        // Summary from the view
        $summary = Database::fetchOne(
            'SELECT total_purchase, total_paid, total_due
             FROM vw_customer_dues WHERE customer_id = ?',
            [$customerId]
        );

        return compact('customer', 'sales', 'payments', 'summary');
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
