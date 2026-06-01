<?php
require_once __DIR__ . '/BaseModel.php';

class SaleReturn extends BaseModel
{
    protected static string $table = 'sale_returns';

    public static function create(int $saleId, array $items, string $reason, string $note = ''): int|string
    {
        if (empty($items)) return 'NO_ITEMS';

        $sale = Database::fetchOne('SELECT * FROM sales WHERE id = ? LIMIT 1', [$saleId]);
        if (!$sale)                          return 'SALE_NOT_FOUND';
        if ($sale['status'] === 'cancelled') return 'SALE_CANCELLED';

        $valid      = [];
        $totalRefund = 0;

        foreach ($items as $it) {
            $productId   = (int)($it['product_id']   ?? 0);
            $qty         = (float)($it['quantity']     ?? 0);
            $unitPrice   = (float)($it['unit_price']   ?? 0);
            $productName = trim($it['product_name']    ?? '');
            if ($productId <= 0 || $qty <= 0) continue;
            $refund       = $qty * $unitPrice;
            $valid[]      = compact('productId', 'productName', 'qty', 'unitPrice', 'refund');
            $totalRefund += $refund;
        }
        if (empty($valid)) return 'NO_ITEMS';

        $returnDate = today();
        $userId     = $_SESSION['user_id'] ?? null;

        Database::beginTransaction();
        try {
            $retId = Database::insert(
                'INSERT INTO sale_returns (sale_id, return_date, reason, total_refund, note, created_by)
                 VALUES (?,?,?,?,?,?)',
                [$saleId, $returnDate, trim($reason), $totalRefund, trim($note), $userId]
            );

            foreach ($valid as $it) {
                Database::insert(
                    'INSERT INTO sale_return_items
                     (return_id, product_id, product_name, quantity, unit_price, refund_amount)
                     VALUES (?,?,?,?,?,?)',
                    [$retId, $it['productId'], $it['productName'],
                     $it['qty'], $it['unitPrice'], $it['refund']]
                );
                // Add stock back via adjustment
                Database::insert(
                    'INSERT INTO stock_adjustments (product_id, quantity, reason, note, created_by)
                     VALUES (?,?,?,?,?)',
                    [$it['productId'], $it['qty'], 'return',
                     'ফেরত — বিক্রয় #' . $sale['invoice_number'], $userId]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }

        self::log('sale_return', 'sale_returns', (int)$retId,
            "Return for sale #{$sale['invoice_number']}, refund ৳$totalRefund");
        return (int)$retId;
    }

    public static function getAll(array $f = []): array
    {
        $sql = 'SELECT sr.*, s.invoice_number,
                       COALESCE(c.name, \'Walk-in\') AS customer_name,
                       u.name AS created_by_name
                FROM sale_returns sr
                JOIN sales s ON s.id = sr.sale_id
                LEFT JOIN customers c ON c.id = s.customer_id
                LEFT JOIN users u ON u.id = sr.created_by
                WHERE 1=1';
        $params = [];
        if (!empty($f['search'])) {
            $sql .= ' AND s.invoice_number LIKE ?';
            $params[] = '%'.$f['search'].'%';
        }
        $sql .= ' ORDER BY sr.return_date DESC, sr.id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getById(int $id): array|false
    {
        $r = Database::fetchOne(
            'SELECT sr.*, s.invoice_number, COALESCE(c.name,\'Walk-in\') AS customer_name
             FROM sale_returns sr
             JOIN sales s ON s.id = sr.sale_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE sr.id = ? LIMIT 1', [$id]
        );
        if (!$r) return false;
        $r['items'] = Database::fetchAll(
            'SELECT * FROM sale_return_items WHERE return_id = ?', [$id]
        );
        return $r;
    }
}
