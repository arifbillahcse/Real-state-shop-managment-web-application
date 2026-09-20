<?php

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel
{
    protected static string $table = 'products';

    public static function addProduct(
        int     $categoryId,
        string  $name,
        string  $sizeBrand,
        string  $unit,
        float   $buyPrice,
        float   $sellPrice,
        float   $minStock,
        ?int    $subcategoryId  = null,
        string  $productCode    = '',
        float   $wholesalePrice = 0,
        string  $image          = ''
    ): int|string {
        $name        = trim($name);
        $productCode = trim($productCode);

        if ($categoryId <= 0)  return 'INVALID_CATEGORY';
        if ($name === '')      return 'NAME_REQUIRED';
        if ($buyPrice  <= 0)   return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0)   return 'INVALID_SELL_PRICE';
        if ($minStock  <  0)   return 'INVALID_MIN_STOCK';
        if ($wholesalePrice < 0) return 'INVALID_WHOLESALE_PRICE';

        if (!Category::exists($categoryId)) return 'INVALID_CATEGORY';
        if ($subcategoryId !== null && $subcategoryId > 0) {
            $sc = Database::fetchOne(
                'SELECT id FROM product_subcategories WHERE id = ? AND category_id = ? LIMIT 1',
                [$subcategoryId, $categoryId]
            );
            if (!$sc) return 'INVALID_SUBCATEGORY';
        } else {
            $subcategoryId = null;
        }

        $exists = Database::fetchOne(
            'SELECT id FROM products
             WHERE category_id = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 LIMIT 1',
            [$categoryId, $name, trim($sizeBrand)]
        );
        if ($exists) return 'DUPLICATE';

        if ($productCode !== '') {
            $dup = Database::fetchOne(
                'SELECT id FROM products WHERE product_code = ? LIMIT 1', [$productCode]
            );
            if ($dup) return 'DUPLICATE_CODE';
        }

        $id = Database::insert(
            'INSERT INTO products
             (category_id, subcategory_id, name, product_code, size_brand, unit, image,
              buy_price, sell_price, wholesale_price, min_stock)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $subcategoryId, $name,
             $productCode !== '' ? $productCode : null,
             trim($sizeBrand), trim($unit), $image !== '' ? $image : null,
             $buyPrice, $sellPrice, $wholesalePrice, $minStock]
        );
        $id = (int)$id;

        // Auto-code if none given
        if ($productCode === '') {
            Database::execute(
                'UPDATE products SET product_code = ? WHERE id = ?',
                ['P-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]
            );
        }

        // Register in every active branch (central prices by default)
        Database::execute(
            'INSERT IGNORE INTO branch_products (branch_id, product_id)
             SELECT id, ? FROM branches WHERE is_active = 1', [$id]
        );

        self::log('create_product', 'products', $id, "Added product: $name");
        return $id;
    }

    public static function getProducts(?int $categoryId = null): array
    {
        $where  = 'p.is_active = 1';
        $params = [];
        if ($categoryId !== null && $categoryId > 0) {
            $where .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }
        return Database::fetchAll(
            "SELECT p.*, pc.name AS category_name, psc.name AS subcategory_name
             FROM products p
             JOIN product_categories pc ON pc.id = p.category_id
             LEFT JOIN product_subcategories psc ON psc.id = p.subcategory_id
             WHERE $where
             ORDER BY pc.name, p.name",
            $params
        );
    }

    public static function getProductById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT p.*, pc.name AS category_name, psc.name AS subcategory_name
             FROM products p
             JOIN product_categories pc ON pc.id = p.category_id
             LEFT JOIN product_subcategories psc ON psc.id = p.subcategory_id
             WHERE p.id = ? AND p.is_active = 1 LIMIT 1',
            [$id]
        );
    }

    public static function updateProduct(int $id, array $data): bool|string
    {
        $product = self::getProductById($id);
        if (!$product) return 'NOT_FOUND';

        $categoryId    = isset($data['category_id']) ? (int)$data['category_id'] : (int)$product['category_id'];
        $subcategoryId = array_key_exists('subcategory_id', $data)
                       ? ((int)$data['subcategory_id'] ?: null)
                       : ($product['subcategory_id'] !== null ? (int)$product['subcategory_id'] : null);
        $name          = trim($data['name']         ?? $product['name']);
        $productCode   = trim($data['product_code'] ?? ($product['product_code'] ?? ''));
        $sizeBrand     = trim($data['size_brand']   ?? $product['size_brand']);
        $unit          = trim($data['unit']         ?? $product['unit']);
        $image         = array_key_exists('image', $data) ? trim((string)$data['image']) : (string)($product['image'] ?? '');
        $buyPrice      = (float)($data['buy_price']       ?? $product['buy_price']);
        $sellPrice     = (float)($data['sell_price']      ?? $product['sell_price']);
        $wholesale     = (float)($data['wholesale_price'] ?? $product['wholesale_price']);
        $minStock      = (float)($data['min_stock']       ?? $product['min_stock']);

        if ($categoryId <= 0)  return 'INVALID_CATEGORY';
        if ($name === '')      return 'NAME_REQUIRED';
        if ($buyPrice  <= 0)   return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0)   return 'INVALID_SELL_PRICE';
        if ($wholesale <  0)   return 'INVALID_WHOLESALE_PRICE';
        if ($minStock  <  0)   return 'INVALID_MIN_STOCK';

        if (!Category::exists($categoryId)) return 'INVALID_CATEGORY';
        if ($subcategoryId !== null) {
            $sc = Database::fetchOne(
                'SELECT id FROM product_subcategories WHERE id = ? AND category_id = ? LIMIT 1',
                [$subcategoryId, $categoryId]
            );
            if (!$sc) $subcategoryId = null; // subcategory belongs to another category → clear
        }

        $dup = Database::fetchOne(
            'SELECT id FROM products
             WHERE category_id = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 AND id <> ? LIMIT 1',
            [$categoryId, $name, $sizeBrand, $id]
        );
        if ($dup) return 'DUPLICATE';

        if ($productCode !== '') {
            $dupCode = Database::fetchOne(
                'SELECT id FROM products WHERE product_code = ? AND id <> ? LIMIT 1',
                [$productCode, $id]
            );
            if ($dupCode) return 'DUPLICATE_CODE';
        }

        Database::execute(
            'UPDATE products SET
                category_id = ?, subcategory_id = ?, name = ?, product_code = ?,
                size_brand = ?, unit = ?, image = ?,
                buy_price = ?, sell_price = ?, wholesale_price = ?, min_stock = ?
             WHERE id = ?',
            [$categoryId, $subcategoryId, $name,
             $productCode !== '' ? $productCode : $product['product_code'],
             $sizeBrand, $unit, $image !== '' ? $image : null,
             $buyPrice, $sellPrice, $wholesale, $minStock, $id]
        );

        self::log('update_product', 'products', $id, "Updated product: $name");
        return true;
    }

    public static function deleteProduct(int $id): bool|string
    {
        $product = self::getProductById($id);
        if (!$product) return 'NOT_FOUND';

        $inStock = Database::fetchOne(
            'SELECT id FROM stock_inbound WHERE product_id = ? LIMIT 1', [$id]
        );
        $inSales = Database::fetchOne(
            'SELECT id FROM sale_items WHERE product_id = ? LIMIT 1', [$id]
        );
        if ($inStock || $inSales) return 'HAS_HISTORY';

        Database::execute('UPDATE products SET is_active = 0 WHERE id = ?', [$id]);
        self::log('delete_product', 'products', $id, "Deleted product: {$product['name']}");
        return true;
    }

    // ── Per-branch price resolution ───────────────────────────────────────────
    // Single source of truth: branch override → central fallback.
    public static function getPrice(int $productId, ?int $branchId = null): array|false
    {
        if ($branchId) {
            $row = Database::fetchOne(
                'SELECT
                    COALESCE(bp.buy_price,       p.buy_price)       AS buy_price,
                    COALESCE(bp.sell_price,      p.sell_price)      AS sell_price,
                    COALESCE(bp.wholesale_price, p.wholesale_price) AS wholesale_price,
                    COALESCE(bp.min_stock,       p.min_stock)       AS min_stock
                 FROM products p
                 LEFT JOIN branch_products bp
                        ON bp.product_id = p.id AND bp.branch_id = ?
                 WHERE p.id = ? AND p.is_active = 1 LIMIT 1',
                [$branchId, $productId]
            );
        } else {
            $row = Database::fetchOne(
                'SELECT buy_price, sell_price, wholesale_price, min_stock
                 FROM products WHERE id = ? AND is_active = 1 LIMIT 1',
                [$productId]
            );
        }
        return $row;
    }

    // All branch price rows for one product (for the pricing modal)
    public static function getBranchPrices(int $productId): array
    {
        return Database::fetchAll(
            'SELECT b.id AS branch_id, b.name AS branch_name,
                    bp.buy_price, bp.sell_price, bp.wholesale_price, bp.min_stock,
                    COALESCE(bp.is_active, 1) AS is_active
             FROM branches b
             LEFT JOIN branch_products bp
                    ON bp.branch_id = b.id AND bp.product_id = ?
             WHERE b.is_active = 1
             ORDER BY b.name',
            [$productId]
        );
    }

    // All price overrides for one branch (for sales form)
    public static function getBranchPriceMap(int $branchId): array
    {
        return Database::fetchAll(
            'SELECT bp.product_id,
                    COALESCE(bp.buy_price,       p.buy_price)       AS buy_price,
                    COALESCE(bp.sell_price,      p.sell_price)      AS sell_price,
                    COALESCE(bp.wholesale_price, p.wholesale_price) AS wholesale_price,
                    bp.is_active
             FROM branch_products bp
             JOIN products p ON p.id = bp.product_id
             WHERE bp.branch_id = ? AND p.is_active = 1',
            [$branchId]
        );
    }

    public static function saveBranchPrice(
        int $branchId, int $productId,
        ?float $buy, ?float $sell, ?float $wholesale, ?float $minStock,
        bool $isActive = true
    ): bool|string {
        if ($branchId <= 0 || $productId <= 0) return 'NOT_FOUND';
        foreach ([$buy, $sell, $wholesale, $minStock] as $v) {
            if ($v !== null && $v < 0) return 'INVALID_PRICE';
        }
        Database::execute(
            'INSERT INTO branch_products
               (branch_id, product_id, buy_price, sell_price, wholesale_price, min_stock, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               buy_price = VALUES(buy_price),
               sell_price = VALUES(sell_price),
               wholesale_price = VALUES(wholesale_price),
               min_stock = VALUES(min_stock),
               is_active = VALUES(is_active)',
            [$branchId, $productId, $buy, $sell, $wholesale, $minStock, $isActive ? 1 : 0]
        );
        self::log('save_branch_price', 'products', $productId,
                  "Branch $branchId price updated for product $productId");
        return true;
    }

    // ── Sub-categories ────────────────────────────────────────────────────────
    public static function getSubcategories(?int $categoryId = null): array
    {
        if ($categoryId) {
            return Database::fetchAll(
                'SELECT * FROM product_subcategories WHERE category_id = ? ORDER BY name',
                [$categoryId]
            );
        }
        return Database::fetchAll(
            'SELECT sc.*, pc.name AS category_name
             FROM product_subcategories sc
             JOIN product_categories pc ON pc.id = sc.category_id
             ORDER BY pc.name, sc.name'
        );
    }

    public static function addSubcategory(int $categoryId, string $name): int|string
    {
        $name = trim($name);
        if ($name === '') return 'NAME_REQUIRED';
        if (!Category::exists($categoryId)) return 'INVALID_CATEGORY';
        $dup = Database::fetchOne(
            'SELECT id FROM product_subcategories WHERE category_id = ? AND name = ? LIMIT 1',
            [$categoryId, $name]
        );
        if ($dup) return 'DUPLICATE';
        return (int)Database::insert(
            'INSERT INTO product_subcategories (category_id, name) VALUES (?, ?)',
            [$categoryId, $name]
        );
    }

    public static function deleteSubcategory(int $id): bool|string
    {
        $used = Database::fetchOne(
            'SELECT id FROM products WHERE subcategory_id = ? AND is_active = 1 LIMIT 1', [$id]
        );
        if ($used) return 'HAS_PRODUCTS';
        Database::execute('DELETE FROM product_subcategories WHERE id = ?', [$id]);
        return true;
    }

    public static function checkMinStock(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vw_current_stock
             WHERE current_stock <= min_stock AND min_stock > 0
             ORDER BY current_stock ASC'
        );
    }

    public static function errorMessage(string $code): string
    {
        return [
            'INVALID_CATEGORY'        => 'সঠিক ক্যাটাগরি নির্বাচন করুন।',
            'INVALID_SUBCATEGORY'     => 'সঠিক সাব-ক্যাটাগরি নির্বাচন করুন।',
            'NAME_REQUIRED'           => 'পণ্যের নাম দিন।',
            'INVALID_BUY_PRICE'       => 'ক্রয় দাম ০ এর বেশি হতে হবে।',
            'INVALID_SELL_PRICE'      => 'বিক্রয় দাম ০ এর বেশি হতে হবে।',
            'INVALID_WHOLESALE_PRICE' => 'পাইকারি দাম ঋণাত্মক হতে পারবে না।',
            'INVALID_MIN_STOCK'       => 'মিনিমাম স্টক ঋণাত্মক হতে পারবে না।',
            'INVALID_PRICE'           => 'দাম ঋণাত্মক হতে পারবে না।',
            'DUPLICATE'               => 'এই পণ্যটি ইতিমধ্যে আছে।',
            'DUPLICATE_CODE'          => 'এই পণ্য কোডটি ইতিমধ্যে ব্যবহৃত হয়েছে।',
            'NOT_FOUND'               => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'HAS_HISTORY'             => 'এই পণ্যের স্টক/বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
            'HAS_PRODUCTS'            => 'এই সাব-ক্যাটাগরিতে পণ্য আছে, ডিলিট করা যাবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
