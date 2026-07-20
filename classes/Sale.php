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
     * $items: [['product_id'=>int,'quantity'=>float,'unit_price'=>float,
     *           'rate_type'=>'retail|wholesale|custom',
     *           'unload_bill'=>float,'labor_bill'=>float,'transport_bill'=>float], ...]
     * $charges: ['unload'=>f, 'labor'=>f, 'transport'=>f, 'delivery'=>f] (combined)
     *
     * Total = subtotal + item charges + combined charges + delivery − discount.
     * Over-limit credit sales require $approvedBy (manager id) — the API layer
     * verifies manager credentials before passing it.
     */
    public static function createSale(
        ?int   $customerId,
        array  $items,
        float  $discount      = 0,
        float  $paidAmount    = 0,
        string $paymentMethod = 'cash',
        string $saleDate      = '',
        string $note          = '',
        ?int   $branchId      = null,
        array  $charges       = [],
        string $discountNote  = '',
        ?int   $approvedBy    = null
    ): int|string {
        if (empty($items)) return 'NO_ITEMS';

        if ($customerId !== null && $customerId > 0) {
            $customer = Customer::getCustomerById($customerId);
            if (!$customer) return 'CUSTOMER_NOT_FOUND';
        } else {
            $customerId = null;
            $customer   = null;
        }

        if ($branchId !== null && $branchId > 0) {
            $br = Database::fetchOne(
                'SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]
            );
            if (!$br) return 'BRANCH_NOT_FOUND';
        } else {
            $branchId = null;
        }

        $subtotal    = 0;
        $itemCharges = 0;
        $validItems  = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty       = (float)($item['quantity']   ?? 0);
            $price     = (float)($item['unit_price']  ?? 0);
            $rateType  = in_array($item['rate_type'] ?? '', ['retail', 'wholesale', 'custom'], true)
                       ? $item['rate_type'] : 'retail';

            if ($productId <= 0) return 'INVALID_PRODUCT';
            if ($qty   <= 0)     return 'INVALID_QUANTITY';
            if ($price <= 0)     return 'INVALID_PRICE';
            if (!Product::getProductById($productId)) return 'PRODUCT_NOT_FOUND';

            $currentStock = $branchId !== null
                ? Stock::getCurrentBranchStock($productId, $branchId)
                : Stock::getCurrentStock($productId);
            if ($currentStock < $qty) return 'INSUFFICIENT_STOCK:' . $productId;

            $u = max(0, (float)($item['unload_bill']    ?? 0));
            $l = max(0, (float)($item['labor_bill']     ?? 0));
            $t = max(0, (float)($item['transport_bill'] ?? 0));

            $validItems[] = [
                'product_id' => $productId,
                'quantity'   => $qty,
                'unit_price' => $price,
                'rate_type'  => $rateType,
                'unload'     => $u, 'labor' => $l, 'transport' => $t,
            ];
            $subtotal    += $qty * $price;
            $itemCharges += $u + $l + $t;
        }

        $unloadBill     = max(0, (float)($charges['unload']    ?? 0));
        $laborBill      = max(0, (float)($charges['labor']     ?? 0));
        $transportBill  = max(0, (float)($charges['transport'] ?? 0));
        $deliveryCharge = max(0, (float)($charges['delivery']  ?? 0));

        $discount    = max(0, $discount);
        $totalAmount = max(0, $subtotal + $itemCharges
                            + $unloadBill + $laborBill + $transportBill
                            + $deliveryCharge - $discount);
        $paidAmount  = max(0, min($paidAmount, $totalAmount));
        $dueAmount   = $totalAmount - $paidAmount;

        // Credit-limit gate: only for named customers with a limit set.
        // Manager approval (approved_by) bypasses the block and is recorded.
        if ($dueAmount > 0 && $customer && (float)($customer['due_limit'] ?? 0) > 0) {
            $currentDue = Database::fetchOne(
                'SELECT COALESCE(SUM(due_amount),0) AS due
                 FROM sales WHERE customer_id = ? AND status = "completed"',
                [$customerId]
            );
            $projected = (float)($currentDue['due'] ?? 0) + $dueAmount;
            if ($projected > (float)$customer['due_limit'] && $approvedBy === null) {
                return 'LIMIT_EXCEEDED:' . $customer['due_limit'] . ':' . ($currentDue['due'] ?? 0);
            }
        }

        $saleDate  = $saleDate !== '' ? $saleDate : today();
        $invoiceNo = self::generateInvoiceNumber();
        $userId    = $_SESSION['user_id'] ?? null;

        Database::beginTransaction();
        try {
            $saleId = Database::insert(
                'INSERT INTO sales
                 (invoice_number, customer_id, branch_id, sale_date, subtotal, discount,
                  unload_bill, labor_bill, transport_bill, delivery_charge, discount_note,
                  total_amount, paid_amount, due_amount, payment_method, note,
                  created_by, approved_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceNo, $customerId, $branchId, $saleDate, $subtotal, $discount,
                 $unloadBill, $laborBill, $transportBill, $deliveryCharge,
                 trim($discountNote) ?: null,
                 $totalAmount, $paidAmount, $dueAmount, $paymentMethod, trim($note),
                 $userId, $approvedBy]
            );

            foreach ($validItems as $it) {
                Database::insert(
                    'INSERT INTO sale_items
                     (sale_id, product_id, quantity, unit_price, rate_type,
                      unload_bill, labor_bill, transport_bill)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$saleId, $it['product_id'], $it['quantity'], $it['unit_price'],
                     $it['rate_type'], $it['unload'], $it['labor'], $it['transport']]
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
                    c.phone AS customer_phone, c.address AS customer_address,
                    u.name AS created_by_name, b.name AS branch_name
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

    public static function updateSale(int $id, array $data, array $newItems): bool|string
    {
        $sale = Database::fetchOne('SELECT * FROM sales WHERE id = ? LIMIT 1', [$id]);
        if (!$sale)                          return 'NOT_FOUND';
        if ($sale['status'] === 'cancelled') return 'CANNOT_EDIT_CANCELLED';
        if (empty($newItems))                return 'NO_ITEMS';

        // Old items keyed by product_id → quantity (to credit back when checking stock)
        $oldItems = Database::fetchAll(
            'SELECT product_id, quantity FROM sale_items WHERE sale_id = ?', [$id]
        );
        $oldQtyMap = [];
        foreach ($oldItems as $oi) {
            $oldQtyMap[(int)$oi['product_id']] = (float)$oi['quantity'];
        }

        // Validate new items & check stock
        $subtotal  = 0;
        $validItems = [];
        foreach ($newItems as $it) {
            $pid   = (int)($it['product_id']  ?? 0);
            $qty   = (float)($it['quantity']   ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            if ($pid <= 0 || $qty <= 0 || $price <= 0) return 'INVALID_ITEM';

            $currentStock = $sale['branch_id']
                ? Stock::getCurrentBranchStock($pid, (int)$sale['branch_id'])
                : Stock::getCurrentStock($pid);
            $available = $currentStock + ($oldQtyMap[$pid] ?? 0);
            if ($available < $qty) return 'INSUFFICIENT_STOCK:' . $pid;

            $validItems[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_price' => $price];
            $subtotal    += $qty * $price;
        }

        $discount    = max(0, (float)($data['discount']       ?? $sale['discount']));
        $paidAmount  = max(0, (float)($data['paid_amount']    ?? $sale['paid_amount']));
        $totalAmount = max(0, $subtotal - $discount);
        $paidAmount  = min($paidAmount, $totalAmount);
        $dueAmount   = $totalAmount - $paidAmount;
        $saleDate    = $data['sale_date']       ?? $sale['sale_date'];
        $payMethod   = $data['payment_method']  ?? $sale['payment_method'];
        $note        = trim($data['note']        ?? $sale['note'] ?? '');
        $customerId  = array_key_exists('customer_id', $data)
            ? (($data['customer_id'] > 0) ? (int)$data['customer_id'] : null)
            : $sale['customer_id'];

        Database::beginTransaction();
        try {
            Database::execute('DELETE FROM sale_items WHERE sale_id = ?', [$id]);
            foreach ($validItems as $it) {
                Database::insert(
                    'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
                     VALUES (?, ?, ?, ?)',
                    [$id, $it['product_id'], $it['quantity'], $it['unit_price']]
                );
            }
            Database::execute(
                'UPDATE sales SET customer_id=?, sale_date=?, subtotal=?, discount=?,
                  total_amount=?, paid_amount=?, due_amount=?, payment_method=?, note=?
                 WHERE id=?',
                [$customerId, $saleDate, $subtotal, $discount,
                 $totalAmount, $paidAmount, $dueAmount, $payMethod, $note, $id]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return 'DB_ERROR';
        }
        self::log('update_sale', 'sales', $id, 'Sale #' . $sale['invoice_number'] . ' updated');
        return true;
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
        if (str_starts_with($code, 'LIMIT_EXCEEDED:')) {
            $parts = explode(':', $code);
            return 'বাকির সীমা (' . number_format((float)($parts[1] ?? 0), 2)
                 . ' ৳) ছাড়িয়ে যাচ্ছে। ম্যানেজার অনুমোদন প্রয়োজন।';
        }
        return [
            'NO_ITEMS'              => 'কমপক্ষে একটি পণ্য যোগ করুন।',
            'CANNOT_EDIT_CANCELLED' => 'বাতিল বিক্রয় সম্পাদনা করা যাবে না।',
            'NOT_FOUND'             => 'বিক্রয় রেকর্ড খুঁজে পাওয়া যায়নি।',
            'INVALID_ITEM'          => 'পণ্যের তথ্য সঠিক নয়।',
            'CUSTOMER_NOT_FOUND'    => 'কাস্টমার খুঁজে পাওয়া যায়নি।',
            'BRANCH_NOT_FOUND'      => 'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
            'INVALID_PRODUCT'       => 'সঠিক পণ্য নির্বাচন করুন।',
            'PRODUCT_NOT_FOUND'     => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'INVALID_QUANTITY'      => 'পরিমাণ ০ এর বেশি হতে হবে।',
            'INVALID_PRICE'         => 'মূল্য ০ এর বেশি হতে হবে।',
            'ALREADY_CANCELLED'     => 'এই বিক্রয় ইতিমধ্যে বাতিল।',
            'DB_ERROR'              => 'ডেটাবেস সমস্যা। আবার চেষ্টা করুন।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
