<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Category.php';

requireMethod('POST');
requireAdminApi();

$result = Product::addProduct(
    (int)($_POST['category_id'] ?? 0),
    $_POST['name']       ?? '',
    $_POST['size_brand'] ?? '',
    $_POST['unit']       ?? 'pcs',
    (float)($_POST['buy_price']  ?? 0),
    (float)($_POST['sell_price'] ?? 0),
    (float)($_POST['min_stock']  ?? 0),
    (int)($_POST['subcategory_id'] ?? 0) ?: null,
    $_POST['product_code'] ?? '',
    (float)($_POST['wholesale_price'] ?? 0),
    $_POST['image'] ?? ''
);

if (is_int($result)) {
    jsonResponse(true, 'পণ্য সফলভাবে যোগ হয়েছে।', ['id' => $result]);
}

jsonResponse(false, Product::errorMessage($result));
