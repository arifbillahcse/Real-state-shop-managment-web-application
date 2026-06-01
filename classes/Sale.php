<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Customer.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Stock.php';

class Sale extends BaseModel
{
    protected static string $table = 'sales';

    public static function generateInvoiceNumber(): string
    {
        $prefix = Setting::get('invoice_prefix', 'INV');
        $count  = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM sales WHERE DATE(created_at) = CURDATE()"
        );
        $seq = str_pad((int)($count['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . date('Ymd') . '-' . $seq;
    }

    /**
     * Create a sale with items inside a transaction.
     * $items: [['product_id'=>int,'quantity'=>float,'unit_price'=>float], ...]
     */
    public static function createSale(
        ?int   $customerId,
        array  $items,
        float  $discount      = 0,
        float  $paidAmount    = 0,
        string $paymentMethod = 'cash',
        string $saleDate      = '',
        string $note          = '',
        ?int   $branchId      = null
    ): int|string {
        if (empty($items)) return 'NO_ITEMS';

        if ($customerId !== null && $customerId > 0) {
            if (!Customer::getCustomerById($customerId)) return 'CUSTOMER_NOT_FOUND';
        } else {
            $customerId = null;
        }

        if ($branchId !== null && $branchId > 0) {
            $br = Database::fetchOne(
                'SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]
            );
            if (!$br) return 'BRANCH_NOT_FOUND';
        } else {
            $branchId = null;
        }

        $subtotal   = 0;
        $validItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty       = (float)($item['quantity']   ?? 0);
            $price     = (float)($item['unit_price']  ?? 0);

            if ($productId <= 0) return 'INVALID_PRODUCT';
            if ($qty   <= 0)     return 'INVALID_QUANTITY';
            if ($price <= 0)     return 'INVALID_PRICE';
            if (!Product::getProductById($productId)) return 'PRODUCT_NOT_FOUND';

            $currentStock = $branchId !== null
                ? Stock::getCurrentBranchStock($productId, $branchId)
                : Stock::getCurrentStock($productId);
            if ($currentStock < $qty) return 'INSUFFICIENT_STOCK:' . $productId;

            $validItems[] = [
                'product_id' => $productId,
                'quantity'   => $qty,
                'unit_price' => $price,
            ];
            $subtotal += $qty * $price;
        }

        $discount    = max(0, $discount);
        $totalAmount = max(0, $subtotal - $discount);
        $paidAmount  = max(0, min($paidAmount, $totalAmount));
        $dueAmount   = $totalAmount - $paidAmount;
        $saleDate    = $saleDate !== '' ? $saleDate : today();
        $invoiceNo   = self::generateInvoiceNumber();
        $userId      = $_SESSION['user_id'] ?? null;

        Database::beginTransaction();
        try {
            $saleId = Database::insert(
                'INSERT INTO sales
                 (invoice_number, customer_id, branch_id, sale_date, subtotal, discount,
                  total_amount, paid_amount, due_amount, payment_method, note, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceNo, $customerId, $branchId, $saleDate, $subtotal, $discount,
                 $totalAmount, $paidAmount, $dueAmount, $paymentMethod, trim($note), $userId]
            );

            foreach ($validItems as $it) {
                Database::insert(
                    'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
                     VALUES (?, ?, ?, ?)',
                    [$saleId, $it['product_id'], $it['quantity'], $it['unit_price']]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }

        self::log('create_sale', 'sales', (int)$saleId, "Sale #$invoiceNo created");
        return (int)$saleId;
    }

    public static function getSales(array $filters = []): array
    {
        $sql    = 'SELECT s.*, COALESCE(c.name, \'Walk-in\') AS customer_name,
                          u.name AS created_by_name,
                          b.name AS branch_name,
                          (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
                   FROM sales s
                   LEFT JOIN customers c ON c.id = s.customer_id
                   LEFT JOIN users     u ON u.id = s.created_by
                   LEFT JOIN branches  b ON b.id = s.branch_id
                   WHERE 1=1';
        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= ' AND s.sale_date >= ?'; $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND s.sale_date <= ?'; $params[] = $filters['date_to'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND s.customer_id = ?'; $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND s.status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['branch_id'])) {
            $sql .= ' AND s.branch_id = ?'; $params[] = (int)$filters['branch_id'];
        }

        $sql .= ' ORDER BY s.sale_date DESC, s.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }
        return Database::fetchAll($sql, $params);
    }

    public static function getSaleById(int $id): array|false
    {
        $sale = Database::fetchOne(
            'SELECT s.*, COALESCE(c.name, \'Walk-in\') AS customer_name,
                    c.phone AS customer_phone, u.name AS created_by_name,
                    b.name AS branch_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users     u ON u.id = s.created_by
             LEFT JOIN branches  b ON b.id = s.branch_id
             WHERE s.id = ? LIMIT 1',
            [$id]
        );
        if (!$sale) return false;

        $sale['items'] = Database::fetchAll(
            'SELECT si.*, p.name AS product_name, pc.name AS product_type, p.unit
             FROM sale_items si
             JOIN products p ON p.id = si.product_id
             JOIN product_categories pc ON pc.id = p.category_id
             WHERE si.sale_id = ? ORDER BY si.id',
            [$id]
        );
        return $sale;
    }

    public static function cancelSale(int $id): bool|string
    {
        $sale = Database::fetchOne(
            'SELECT * FROM sales WHERE id = ? LIMIT 1', [$id]
        );
        if (!$sale)                          return 'NOT_FOUND';
        if ($sale['status'] === 'cancelled') return 'ALREADY_CANCELLED';

        Database::execute(
            'UPDATE sales SET status = ? WHERE id = ?', ['cancelled', $id]
        );
        self::log('cancel_sale', 'sales', $id,
            'Cancelled sale #' . $sale['invoice_number']);
        return true;
    }

    public static function errorMessage(string $code): string
    {
        if (str_starts_with($code, 'INSUFFICIENT_STOCK:')) {
            return 'পর্যাপ্ত স্টক নেই।';
        }
        return [
            'NO_ITEMS'           => 'কমপক্ষে একটি পণ্য যোগ করুন।',
            'CUSTOMER_NOT_FOUND' => 'কাস্টমার খুঁজে পাওয়া যায়নি।',
            'BRANCH_NOT_FOUND'   => 'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
            'INVALID_PRODUCT'    => 'সঠিক পণ্য নির্বাচন করুন।',
            'PRODUCT_NOT_FOUND'  => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'INVALID_QUANTITY'   => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'INVALID_PRICE'      => 'মূল্য ০ এর বেশি হতে হবে।',
            'NOT_FOUND'          => 'বিক্রয় রেকর্ড খুঁজে পাওয়া যায়নি।',
            'ALREADY_CANCELLED'  => 'এই বিক্রয় ইতিমধ্যে বাতিল।',
            'DB_ERROR'           => 'ডেটাবেস সমস্যা। আবার চেষ্টা করুন।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
