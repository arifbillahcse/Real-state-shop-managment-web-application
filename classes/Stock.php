<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Product.php';

class Stock extends BaseModel
{
    protected static string $table = 'stock_inbound';

    /**
     * Add a stock inbound (purchase) record.
     * Returns new ID (int) or an error code string.
     */
    public static function addStockInbound(
        int    $productId,
        float  $quantity,
        float  $buyPrice,
        ?int   $supplierId = null,
        string $inboundDate = '',
        string $note = ''
    ): int|string {
        // --- Validation ---
        if ($productId <= 0)                       return 'INVALID_PRODUCT';
        if (!Product::getProductById($productId))  return 'PRODUCT_NOT_FOUND';
        if ($quantity <= 0)                        return 'INVALID_QUANTITY';
        if ($buyPrice <= 0)                        return 'INVALID_PRICE';

        if ($supplierId !== null && $supplierId > 0) {
            if (!Supplier::getSupplierById($supplierId)) return 'SUPPLIER_NOT_FOUND';
        } else {
            $supplierId = null;
        }

        $inboundDate = $inboundDate !== '' ? $inboundDate : today();
        $userId      = $_SESSION['user_id'] ?? null;

        $id = Database::insert(
            'INSERT INTO stock_inbound
             (product_id, supplier_id, quantity, buy_price, inbound_date, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$productId, $supplierId, $quantity, $buyPrice, $inboundDate, trim($note), $userId]
        );

        self::log('add_stock', 'stock', (int)$id,
            "Stock inbound: product #$productId, qty $quantity");
        return (int)$id;
    }

    /**
     * Get stock inbound history (optionally filter by product).
     */
    public static function getStockInbound(?int $productId = null): array
    {
        $sql = 'SELECT si.*, p.name AS product_name, p.type AS product_type, p.unit,
                       s.name AS supplier_name,
                       u.name AS created_by_name
                FROM stock_inbound si
                JOIN products p   ON p.id = si.product_id
                LEFT JOIN suppliers s ON s.id = si.supplier_id
                LEFT JOIN users u     ON u.id = si.created_by';
        $params = [];
        if ($productId !== null && $productId > 0) {
            $sql .= ' WHERE si.product_id = ?';
            $params[] = $productId;
        }
        $sql .= ' ORDER BY si.inbound_date DESC, si.id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getStockInboundById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT * FROM stock_inbound WHERE id = ? LIMIT 1', [$id]
        );
    }

    /**
     * Update a stock inbound record.
     */
    public static function updateStockInbound(int $id, array $data): bool|string
    {
        $row = self::getStockInboundById($id);
        if (!$row) return 'NOT_FOUND';

        $quantity    = (float)($data['quantity']     ?? $row['quantity']);
        $buyPrice    = (float)($data['buy_price']     ?? $row['buy_price']);
        $supplierId  = $data['supplier_id'] ?? $row['supplier_id'];
        $inboundDate = trim($data['inbound_date'] ?? $row['inbound_date']);
        $note        = trim($data['note']         ?? $row['note']);

        if ($quantity <= 0) return 'INVALID_QUANTITY';
        if ($buyPrice <= 0) return 'INVALID_PRICE';

        if ($supplierId !== null && (int)$supplierId > 0) {
            if (!Supplier::getSupplierById((int)$supplierId)) return 'SUPPLIER_NOT_FOUND';
            $supplierId = (int)$supplierId;
        } else {
            $supplierId = null;
        }

        Database::execute(
            'UPDATE stock_inbound SET
                quantity = ?, buy_price = ?, supplier_id = ?, inbound_date = ?, note = ?
             WHERE id = ?',
            [$quantity, $buyPrice, $supplierId, $inboundDate, $note, $id]
        );
        self::log('update_stock', 'stock', $id, "Updated stock inbound #$id");
        return true;
    }

    /**
     * Delete a stock inbound record.
     * Blocks if deleting would make current stock negative.
     */
    public static function deleteStockInbound(int $id): bool|string
    {
        $row = self::getStockInboundById($id);
        if (!$row) return 'NOT_FOUND';

        $current = self::getCurrentStock((int)$row['product_id']);
        if ($current - (float)$row['quantity'] < 0) {
            return 'WOULD_GO_NEGATIVE';
        }

        Database::execute('DELETE FROM stock_inbound WHERE id = ?', [$id]);
        self::log('delete_stock', 'stock', $id, "Deleted stock inbound #$id");
        return true;
    }

    /**
     * Current stock for a single product (inbound - sold).
     */
    public static function getCurrentStock(int $productId): float
    {
        $row = Database::fetchOne(
            'SELECT current_stock FROM vw_current_stock WHERE product_id = ?',
            [$productId]
        );
        return (float)($row['current_stock'] ?? 0);
    }

    /**
     * Current stock for all products (uses the view).
     */
    public static function getAllStock(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vw_current_stock ORDER BY product_type, product_name'
        );
    }

    public static function errorMessage(string $code): string
    {
        return [
            'INVALID_PRODUCT'    => 'সঠিক পণ্য নির্বাচন করুন।',
            'PRODUCT_NOT_FOUND'  => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'INVALID_QUANTITY'   => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'INVALID_PRICE'      => 'ক্রয় দাম ০ এর বেশি হতে হবে।',
            'SUPPLIER_NOT_FOUND' => 'সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।',
            'NOT_FOUND'          => 'রেকর্ডটি খুঁজে পাওয়া যায়নি।',
            'WOULD_GO_NEGATIVE'  => 'এই রেকর্ড ডিলিট করলে স্টক ঋণাত্মক হয়ে যাবে।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
