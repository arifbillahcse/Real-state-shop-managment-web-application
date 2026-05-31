<?php

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel
{
    protected static string $table = 'products';

    public static function addProduct(
        int    $categoryId,
        string $name,
        string $sizeBrand,
        string $unit,
        float  $buyPrice,
        float  $sellPrice,
        float  $minStock
    ): int|string {
        $name = trim($name);

        if ($categoryId <= 0)  return 'INVALID_CATEGORY';
        if ($name === '')      return 'NAME_REQUIRED';
        if ($buyPrice  <= 0)   return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0)   return 'INVALID_SELL_PRICE';
        if ($minStock  <  0)   return 'INVALID_MIN_STOCK';

        if (!Category::exists($categoryId)) return 'INVALID_CATEGORY';

        $exists = Database::fetchOne(
            'SELECT id FROM products
             WHERE category_id = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 LIMIT 1',
            [$categoryId, $name, trim($sizeBrand)]
        );
        if ($exists) return 'DUPLICATE';

        $id = Database::insert(
            'INSERT INTO products
             (category_id, name, size_brand, unit, buy_price, sell_price, min_stock)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $name, trim($sizeBrand), trim($unit), $buyPrice, $sellPrice, $minStock]
        );

        self::log('create_product', 'products', (int)$id, "Added product: $name");
        return (int)$id;
    }

    public static function getProducts(?int $categoryId = null): array
    {
        if ($categoryId !== null && $categoryId > 0) {
            return Database::fetchAll(
                'SELECT p.*, pc.name AS category_name
                 FROM products p
                 JOIN product_categories pc ON pc.id = p.category_id
                 WHERE p.category_id = ? AND p.is_active = 1
                 ORDER BY p.name',
                [$categoryId]
            );
        }
        return Database::fetchAll(
            'SELECT p.*, pc.name AS category_name
             FROM products p
             JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.is_active = 1
             ORDER BY pc.name, p.name'
        );
    }

    public static function getProductById(int $id): array|false
    {
        return Database::fetchOne(
            'SELECT p.*, pc.name AS category_name
             FROM products p
             JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1 LIMIT 1',
            [$id]
        );
    }

    public static function updateProduct(int $id, array $data): bool|string
    {
        $product = self::getProductById($id);
        if (!$product) return 'NOT_FOUND';

        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : (int)$product['category_id'];
        $name       = trim($data['name']       ?? $product['name']);
        $sizeBrand  = trim($data['size_brand'] ?? $product['size_brand']);
        $unit       = trim($data['unit']       ?? $product['unit']);
        $buyPrice   = (float)($data['buy_price']  ?? $product['buy_price']);
        $sellPrice  = (float)($data['sell_price'] ?? $product['sell_price']);
        $minStock   = (float)($data['min_stock']  ?? $product['min_stock']);

        if ($categoryId <= 0)  return 'INVALID_CATEGORY';
        if ($name === '')      return 'NAME_REQUIRED';
        if ($buyPrice  <= 0)   return 'INVALID_BUY_PRICE';
        if ($sellPrice <= 0)   return 'INVALID_SELL_PRICE';
        if ($minStock  <  0)   return 'INVALID_MIN_STOCK';

        if (!Category::exists($categoryId)) return 'INVALID_CATEGORY';

        $dup = Database::fetchOne(
            'SELECT id FROM products
             WHERE category_id = ? AND name = ? AND IFNULL(size_brand,"") = ?
               AND is_active = 1 AND id <> ? LIMIT 1',
            [$categoryId, $name, $sizeBrand, $id]
        );
        if ($dup) return 'DUPLICATE';

        Database::execute(
            'UPDATE products SET
                category_id = ?, name = ?, size_brand = ?, unit = ?,
                buy_price = ?, sell_price = ?, min_stock = ?
             WHERE id = ?',
            [$categoryId, $name, $sizeBrand, $unit, $buyPrice, $sellPrice, $minStock, $id]
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
            'INVALID_CATEGORY'   => 'সঠিক ক্যাটাগরি নির্বাচন করুন।',
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
