<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Supplier.php';

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
        ?int   $supplierId  = null,
        string $inboundDate = '',
        string $note        = '',
        ?int   $branchId    = null
    ): int|string {
        if ($productId <= 0)                       return 'INVALID_PRODUCT';
        if (!Product::getProductById($productId))  return 'PRODUCT_NOT_FOUND';
        if ($quantity <= 0)                        return 'INVALID_QUANTITY';
        if ($buyPrice <= 0)                        return 'INVALID_PRICE';

        if ($supplierId !== null && $supplierId > 0) {
            if (!Supplier::getSupplierById($supplierId)) return 'SUPPLIER_NOT_FOUND';
        } else {
            $supplierId = null;
        }

        if ($branchId !== null && $branchId > 0) {
            $br = Database::fetchOne('SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]);
            if (!$br) return 'BRANCH_NOT_FOUND';
        } else {
            $branchId = null;
        }

        $inboundDate = $inboundDate !== '' ? $inboundDate : today();
        $userId      = $_SESSION['user_id'] ?? null;

        $id = Database::insert(
            'INSERT INTO stock_inbound
             (product_id, supplier_id, branch_id, quantity, buy_price, inbound_date, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$productId, $supplierId, $branchId, $quantity, $buyPrice, $inboundDate, trim($note), $userId]
        );

        self::log('add_stock', 'stock', (int)$id,
            "Stock inbound: product #$productId, qty $quantity, branch #$branchId");
        return (int)$id;
    }

    /**
     * Get stock inbound history (optionally filter by product).
     */
    public static function getStockInbound(?int $productId = null, ?int $branchId = null): array
    {
        $sql = 'SELECT si.*, p.name AS product_name, p.type AS product_type, p.unit,
                       s.name AS supplier_name,
                       b.name AS branch_name,
                       u.name AS created_by_name
                FROM stock_inbound si
                JOIN  products  p  ON p.id = si.product_id
                LEFT JOIN suppliers s ON s.id = si.supplier_id
                LEFT JOIN branches  b ON b.id = si.branch_id
                LEFT JOIN users     u ON u.id = si.created_by
                WHERE 1=1';
        $params = [];
        if ($productId !== null && $productId > 0) {
            $sql .= ' AND si.product_id = ?';
            $params[] = $productId;
        }
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND si.branch_id = ?';
            $params[] = $branchId;
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
        $buyPrice    = (float)($data['buy_price']    ?? $row['buy_price']);
        $supplierId  = $data['supplier_id'] ?? $row['supplier_id'];
        $inboundDate = trim($data['inbound_date'] ?? $row['inbound_date']);
        $note        = trim($data['note']         ?? $row['note']);
        // branch_id: keep existing if not provided
        $branchId    = array_key_exists('branch_id', $data)
                       ? ($data['branch_id'] !== '' && $data['branch_id'] !== null ? (int)$data['branch_id'] : null)
                       : ($row['branch_id'] !== null ? (int)$row['branch_id'] : null);

        if ($quantity <= 0) return 'INVALID_QUANTITY';
        if ($buyPrice <= 0) return 'INVALID_PRICE';

        if ($supplierId !== null && (int)$supplierId > 0) {
            if (!Supplier::getSupplierById((int)$supplierId)) return 'SUPPLIER_NOT_FOUND';
            $supplierId = (int)$supplierId;
        } else {
            $supplierId = null;
        }

        if ($branchId !== null) {
            $br = Database::fetchOne('SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]);
            if (!$br) return 'BRANCH_NOT_FOUND';
        }

        Database::execute(
            'UPDATE stock_inbound SET
                quantity = ?, buy_price = ?, supplier_id = ?, branch_id = ?, inbound_date = ?, note = ?
             WHERE id = ?',
            [$quantity, $buyPrice, $supplierId, $branchId, $inboundDate, $note, $id]
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

        // Check stock won't go negative — per-branch if branch is set
        if ($row['branch_id'] !== null) {
            $current = self::getCurrentBranchStock((int)$row['product_id'], (int)$row['branch_id']);
        } else {
            $current = self::getCurrentStock((int)$row['product_id']);
        }
        if ($current - (float)$row['quantity'] < 0) {
            return 'WOULD_GO_NEGATIVE';
        }

        Database::execute('DELETE FROM stock_inbound WHERE id = ?', [$id]);
        self::log('delete_stock', 'stock', $id, "Deleted stock inbound #$id");
        return true;
    }

    /**
     * Current stock for a single product across all branches (global).
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
     * Current stock for a single product in a specific branch.
     */
    public static function getCurrentBranchStock(int $productId, int $branchId): float
    {
        $row = Database::fetchOne(
            'SELECT current_stock FROM vw_branch_stock WHERE product_id = ? AND branch_id = ?',
            [$productId, $branchId]
        );
        return (float)($row['current_stock'] ?? 0);
    }

    /**
     * All products stock for a specific branch.
     */
    public static function getBranchStock(int $branchId): array
    {
        return Database::fetchAll(
            'SELECT * FROM vw_branch_stock WHERE branch_id = ? ORDER BY product_type, product_name',
            [$branchId]
        );
    }

    /**
     * Current stock for all products — global (uses vw_current_stock).
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
            'BRANCH_NOT_FOUND'   => 'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
            'NOT_FOUND'          => 'রেকর্ডটি খুঁজে পাওয়া যায়নি।',
            'WOULD_GO_NEGATIVE'  => 'এই রেকর্ড ডিলিট করলে স্টক ঋণাত্মক হয়ে যাবে।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }

    // ===== ADJUSTMENTS =====

    public static function addAdjustment(
        int    $productId,
        float  $quantity,
        string $reason,
        string $note     = '',
        ?int   $branchId = null
    ): int|string {
        if ($productId <= 0 || !Product::getProductById($productId)) return 'PRODUCT_NOT_FOUND';
        if ($quantity == 0) return 'INVALID_QUANTITY';

        if ($branchId !== null && $branchId > 0) {
            $br = Database::fetchOne('SELECT id FROM branches WHERE id = ? AND is_active = 1', [$branchId]);
            if (!$br) return 'BRANCH_NOT_FOUND';
            $current = self::getCurrentBranchStock($productId, $branchId);
        } else {
            $branchId = null;
            $current  = self::getCurrentStock($productId);
        }
        if ($current + $quantity < 0) return 'WOULD_GO_NEGATIVE';

        $userId = $_SESSION['user_id'] ?? null;
        $id = Database::insert(
            'INSERT INTO stock_adjustments (product_id, branch_id, quantity, reason, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$productId, $branchId, $quantity, trim($reason), trim($note), $userId]
        );
        self::log('adjust_stock', 'stock', (int)$id,
            "Adjusted product #$productId by $quantity" . ($branchId ? " (branch #$branchId)" : ''));
        return (int)$id;
    }

    public static function getAdjustments(?int $branchId = null): array
    {
        $sql = 'SELECT sa.*, p.name AS product_name, p.unit,
                       b.name AS branch_name, u.name AS created_by_name
                FROM stock_adjustments sa
                JOIN products p ON p.id = sa.product_id
                LEFT JOIN branches b ON b.id = sa.branch_id
                LEFT JOIN users u ON u.id = sa.created_by
                WHERE 1=1';
        $params = [];
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND sa.branch_id = ?';
            $params[] = $branchId;
        }
        $sql .= ' ORDER BY sa.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    // ===== TRANSFERS =====

    public static function addTransfer(
        int    $productId,
        int    $fromBranchId,
        int    $toBranchId,
        float  $quantity,
        string $note = ''
    ): int|string {
        if ($productId <= 0 || !Product::getProductById($productId)) return 'PRODUCT_NOT_FOUND';
        if ($quantity <= 0)                return 'INVALID_QUANTITY';
        if ($fromBranchId === $toBranchId) return 'SAME_BRANCH';

        $fromBr = Database::fetchOne('SELECT id FROM branches WHERE id = ? AND is_active = 1', [$fromBranchId]);
        if (!$fromBr) return 'FROM_BRANCH_NOT_FOUND';
        $toBr = Database::fetchOne('SELECT id FROM branches WHERE id = ? AND is_active = 1', [$toBranchId]);
        if (!$toBr) return 'TO_BRANCH_NOT_FOUND';

        $fromStock = self::getCurrentBranchStock($productId, $fromBranchId);
        if ($fromStock < $quantity) return 'INSUFFICIENT_STOCK';

        $userId = $_SESSION['user_id'] ?? null;
        $id = Database::insert(
            'INSERT INTO stock_transfers (product_id, from_branch_id, to_branch_id, quantity, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$productId, $fromBranchId, $toBranchId, $quantity, trim($note), $userId]
        );
        self::log('transfer_stock', 'stock', (int)$id,
            "Transferred $quantity of #$productId from branch #$fromBranchId to #$toBranchId");
        return (int)$id;
    }

    public static function getTransfers(?int $branchId = null): array
    {
        $sql = 'SELECT st.*, p.name AS product_name, p.unit,
                       fb.name AS from_branch_name, tb.name AS to_branch_name,
                       u.name AS created_by_name
                FROM stock_transfers st
                JOIN products p ON p.id = st.product_id
                JOIN branches fb ON fb.id = st.from_branch_id
                JOIN branches tb ON tb.id = st.to_branch_id
                LEFT JOIN users u ON u.id = st.created_by
                WHERE 1=1';
        $params = [];
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND (st.from_branch_id = ? OR st.to_branch_id = ?)';
            $params[] = $branchId;
            $params[] = $branchId;
        }
        $sql .= ' ORDER BY st.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getAllBranchStock(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vw_branch_stock ORDER BY product_type, product_name, branch_name'
        );
    }

    public static function adjustmentErrorMessage(string $code): string
    {
        return [
            'PRODUCT_NOT_FOUND'    => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'INVALID_QUANTITY'     => 'পরিমাণ ০ হতে পারবে না।',
            'BRANCH_NOT_FOUND'     => 'ব্রাঞ্চটি খুঁজে পাওয়া যায়নি।',
            'WOULD_GO_NEGATIVE'    => 'এই পরিমাণ কমালে স্টক ঋণাত্মক হয়ে যাবে।',
            'SAME_BRANCH'          => 'উৎস ও গন্তব্য ব্রাঞ্চ একই হতে পারবে না।',
            'FROM_BRANCH_NOT_FOUND'=> 'উৎস ব্রাঞ্চ পাওয়া যায়নি।',
            'TO_BRANCH_NOT_FOUND'  => 'গন্তব্য ব্রাঞ্চ পাওয়া যায়নি।',
            'INSUFFICIENT_STOCK'   => 'উৎস ব্রাঞ্চে পর্যাপ্ত স্টক নেই।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
