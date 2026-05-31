<?php

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel
{
    protected static string $table = 'products';

    public const TYPES = ['rod', 'cement'];

    /**
     * Add a new product.
     * Returns new ID (int) or an error code string.
     */
    public static function addProduct(
        string $type,
        string $name,
        string $sizeBrand,
        string $unit,
        float  $buyPrice,
        float  $sellPrice,
        float  $minStock
    ): int|string {
        $type = strtolower(trim($type));
        $name = trim($name);

        // --- Validation ---
        if (!in_array($type, self::TYPES, true)) return 'INVALID_TYPE';
        if ($name === '')        return 'NAME_REQUIRED';
        if ($buyPrice  <= 0)     return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0)     return 'INVALID_SELL_PRICE';
        if ($minStock  <  0)     return 'INVALID_MIN_STOCK';

        // --- Duplicate check (same type + name + size/brand) ---
        $exists = Database::fetchOne(
            'SELECT id FROM products
             WHERE type = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 LIMIT 1',
            [$type, $name, trim($sizeBrand)]
        );
        if ($exists) return 'DUPLICATE';

        $id = Database::insert(
            'INSERT INTO products
             (type, name, size_brand, unit, buy_price, sell_price, min_stock)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$type, $name, trim($sizeBrand), trim($unit), $buyPrice, $sellPrice, $minStock]
        );

        self::log('create_product', 'products', (int)$id, "Added product: $name");
        return (int)$id;
    }

    /**
     * Get products, optionally filtered by type.
     */
    public static function getProducts(?string $type = null): array
    {
        if ($type !== null && in_array($type, self::TYPES, true)) {
            return Database::fetchAll(
                'SELECT * FROM products WHERE type = ? AND is_active = 1 ORDER BY name',
                [$type]
            );
        }
        return Database::fetchAll(
            'SELECT * FROM products WHERE is_active = 1 ORDER BY type, name'
        );
    }

    public static function getProductById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1',
            [$id]
        );
    }

    /**
     * Update an existing product.
     * Returns true, or an error code string.
     */
    public static function updateProduct(int $id, array $data): bool|string
    {
        $product = self::getProductById($id);
        if (!$product) return 'NOT_FOUND';

        $type      = strtolower(trim($data['type']       ?? $product['type']));
        $name      = trim($data['name']                  ?? $product['name']);
        $sizeBrand = trim($data['size_brand']            ?? $product['size_brand']);
        $unit      = trim($data['unit']                  ?? $product['unit']);
        $buyPrice  = (float)($data['buy_price']          ?? $product['buy_price']);
        $sellPrice = (float)($data['sell_price']         ?? $product['sell_price']);
        $minStock  = (float)($data['min_stock']          ?? $product['min_stock']);

        // --- Validation ---
        if (!in_array($type, self::TYPES, true)) return 'INVALID_TYPE';
        if ($name === '')    return 'NAME_REQUIRED';
        if ($buyPrice  <= 0) return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0) return 'INVALID_SELL_PRICE';
        if ($minStock  <  0) return 'INVALID_MIN_STOCK';

        // --- Duplicate check (exclude current id) ---
        $dup = Database::fetchOne(
            'SELECT id FROM products
             WHERE type = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 AND id <> ? LIMIT 1',
            [$type, $name, $sizeBrand, $id]
        );
        if ($dup) return 'DUPLICATE';

        Database::execute(
            'UPDATE products SET
                type = ?, name = ?, size_brand = ?, unit = ?,
                buy_price = ?, sell_price = ?, min_stock = ?
             WHERE id = ?',
            [$type, $name, $sizeBrand, $unit, $buyPrice, $sellPrice, $minStock, $id]
        );

        self::log('update_product', 'products', $id, "Updated product: $name");
        return true;
    }

    /**
     * Soft-delete a product. Blocks if it has stock or sales history.
     */
    public static function deleteProduct(int $id): bool|string
    {
        $product = self::getProductById($id);
        if (!$product) return 'NOT_FOUND';

        // Block delete if product is referenced by stock or sales
        $inStock = Database::fetchOne(
            'SELECT id FROM stock_inbound WHERE product_id = ? LIMIT 1', [$id]
        );
        $inSales = Database::fetchOne(
            'SELECT id FROM sale_items WHERE product_id = ? LIMIT 1', [$id]
        );
        if ($inStock || $inSales) return 'HAS_HISTORY';

        // Soft delete
        Database::execute('UPDATE products SET is_active = 0 WHERE id = ?', [$id]);
        self::log('delete_product', 'products', $id, "Deleted product: {$product['name']}");
        return true;
    }

    /**
     * Products at or below their minimum stock level.
     */
    public static function checkMinStock(): array
    {
        return Database::fetchAll(
            'SELECT * FROM vw_current_stock
             WHERE current_stock <= min_stock AND min_stock > 0
             ORDER BY current_stock ASC'
        );
    }

    /**
     * Human-readable message for an error code.
     */
    public static function errorMessage(string $code): string
    {
        return [
            'INVALID_TYPE'       => 'পণ্যের ধরন সঠিক নয় (rod / cement)।',
            'NAME_REQUIRED'      => 'পণ্যের নাম দিন।',
            'INVALID_BUY_PRICE'  => 'ক্রয় দাম ০ এর বেশি হতে হবে।',
            'INVALID_SELL_PRICE' => 'বিক্রয় দাম ০ এর বেশি হতে হবে।',
            'INVALID_MIN_STOCK'  => 'মিনিমাম স্টক ঋণাত্মক হতে পারবে না।',
            'DUPLICATE'          => 'এই পণ্যটি ইতিমধ্যে আছে।',
            'NOT_FOUND'          => 'পণ্যটি খুঁজে পাওয়া যায়নি।',
            'HAS_HISTORY'        => 'এই পণ্যের স্টক/বিক্রয় রেকর্ড আছে, ডিলিট করা যাবে না।',
        ][$code] ?? 'একটি সমস্যা হয়েছে।';
    }
}
