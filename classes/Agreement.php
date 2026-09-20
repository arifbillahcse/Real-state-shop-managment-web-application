<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Customer.php';

/**
 * Advance Purchase Agreements (চুক্তিপত্র / ডিট) — §6.8
 * Receipt for advance goods purchase + a delivery tracking sheet
 * that draws down against the agreed quantities.
 */
class Agreement extends BaseModel
{
    protected static string $table = 'purchase_agreements';

    public static function create(
        int $customerId, string $date, array $items,
        float $depositAmount, string $depositMethod, string $note, ?int $userId
    ): int|string {
        if (!Customer::getCustomerById($customerId)) return 'NOT_FOUND';
        if (empty($items)) return 'ITEMS_REQUIRED';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';
        if ($depositAmount < 0) return 'INVALID_AMOUNT';

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
            // Serial agreement number: AGR-YYYY-NNNN
            $year = date('Y', strtotime($date));
            $row  = Database::fetchOne(
                'SELECT COUNT(*) AS c FROM purchase_agreements WHERE agreement_no LIKE ?',
                ["AGR-$year-%"]
            );
            $agreementNo = sprintf('AGR-%s-%04d', $year, (int)$row['c'] + 1);

            $id = (int)Database::insert(
                'INSERT INTO purchase_agreements
                    (agreement_no, customer_id, agreement_date, total_amount,
                     deposit_amount, deposit_method, note, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$agreementNo, $customerId, $date, round($total, 2),
                 $depositAmount, trim($depositMethod) ?: null, trim($note) ?: null, $userId]
            );
            foreach ($cleanItems as $ci) {
                Database::insert(
                    'INSERT INTO purchase_agreement_items
                        (agreement_id, product_id, product_name, quantity, unit, unit_price, line_total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $ci['product_id'], $ci['product_name'], $ci['quantity'],
                     $ci['unit'], $ci['unit_price'], $ci['line_total']]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('Agreement create failed: ' . $e->getMessage());
            return 'DB_ERROR';
        }

        self::log('create_agreement', 'purchase_agreements', $id,
                  "Agreement $agreementNo for customer #$customerId: $total");
        return $id;
    }

    public static function getForCustomer(int $customerId): array
    {
        $agreements = Database::fetchAll(
            'SELECT a.*, u.name AS created_by_name
             FROM purchase_agreements a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.customer_id = ?
             ORDER BY a.agreement_date DESC, a.id DESC',
            [$customerId]
        );
        foreach ($agreements as &$a) {
            $a['items']      = self::getItems((int)$a['id']);
            $a['deliveries'] = self::getDeliveries((int)$a['id']);
        }
        return $agreements;
    }

    public static function getById(int $id): array|false
    {
        $a = Database::fetchOne(
            'SELECT a.*, c.name AS customer_name, c.phone AS customer_phone,
                    c.address AS customer_address
             FROM purchase_agreements a
             JOIN customers c ON c.id = a.customer_id
             WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if (!$a) return false;
        $a['items']      = self::getItems($id);
        $a['deliveries'] = self::getDeliveries($id);
        return $a;
    }

    private static function getItems(int $agreementId): array
    {
        return Database::fetchAll(
            'SELECT i.*,
                    COALESCE((SELECT SUM(d.quantity) FROM agreement_deliveries d
                              WHERE d.agreement_id = i.agreement_id
                                AND ((i.product_id IS NOT NULL AND d.product_id = i.product_id)
                                     OR (i.product_id IS NULL AND d.product_name = i.product_name))), 0)
                        AS delivered_qty
             FROM purchase_agreement_items i
             WHERE i.agreement_id = ?
             ORDER BY i.id',
            [$agreementId]
        );
    }

    private static function getDeliveries(int $agreementId): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.name AS created_by_name
             FROM agreement_deliveries d
             LEFT JOIN users u ON u.id = d.created_by
             WHERE d.agreement_id = ?
             ORDER BY d.delivery_date ASC, d.id ASC',
            [$agreementId]
        );
    }

    public static function addDelivery(
        int $agreementId, string $date, ?int $productId,
        string $productName, float $quantity, string $unit,
        string $note, ?int $userId
    ): int|string {
        $agreement = Database::fetchOne(
            'SELECT * FROM purchase_agreements WHERE id = ? LIMIT 1', [$agreementId]
        );
        if (!$agreement) return 'NOT_FOUND';
        if ($agreement['status'] !== 'active') return 'NOT_ACTIVE';
        if ($quantity <= 0) return 'INVALID_AMOUNT';
        if (trim($productName) === '') return 'INVALID_ITEM';
        if ($date === '' || !strtotime($date)) return 'INVALID_DATE';

        $id = (int)Database::insert(
            'INSERT INTO agreement_deliveries
                (agreement_id, delivery_date, product_id, product_name, quantity, unit, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$agreementId, $date, $productId ?: null, trim($productName),
             $quantity, trim($unit), trim($note) ?: null, $userId]
        );

        self::log('agreement_delivery', 'purchase_agreements', $agreementId,
                  "Delivery for agreement #{$agreement['agreement_no']}: $productName × $quantity");
        return $id;
    }

    public static function setStatus(int $id, string $status): bool|string
    {
        if (!in_array($status, ['active', 'completed', 'cancelled'], true)) return 'INVALID_STATUS';
        $found = Database::fetchOne('SELECT id FROM purchase_agreements WHERE id = ? LIMIT 1', [$id]);
        if (!$found) return 'NOT_FOUND';
        Database::execute('UPDATE purchase_agreements SET status = ? WHERE id = ?', [$status, $id]);
        self::log('agreement_status', 'purchase_agreements', $id, "Status → $status");
        return true;
    }

    public static function errorMessage(string $code): string
    {
        return [
            'NOT_FOUND'      => 'চুক্তিপত্র/কাস্টমার খুঁজে পাওয়া যায়নি।',
            'ITEMS_REQUIRED' => 'কমপক্ষে একটি পণ্য যোগ করুন।',
            'INVALID_ITEM'   => 'পণ্যের নাম, পরিমাণ ও দাম সঠিকভাবে দিন।',
            'INVALID_AMOUNT' => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'INVALID_DATE'   => 'সঠিক তারিখ দিন।',
            'INVALID_STATUS' => 'ভুল স্ট্যাটাস।',
            'NOT_ACTIVE'     => 'এই চুক্তিপত্রটি সক্রিয় নেই।',
            'DB_ERROR'       => 'ডাটাবেজে সমস্যা হয়েছে। আবার চেষ্টা করুন।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
