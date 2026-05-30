<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

requireMethod('POST');
requireAdminApi();

$result = Product::addProduct(
    $_POST['type']       ?? '',
    $_POST['name']       ?? '',
    $_POST['size_brand'] ?? '',
    $_POST['unit']       ?? 'pcs',
    (float)($_POST['buy_price']  ?? 0),
    (float)($_POST['sell_price'] ?? 0),
    (float)($_POST['min_stock']  ?? 0)
);

if (is_int($result)) {
    jsonResponse(true, 'পণ্য সফলভাবে যোগ হয়েছে।', ['id' => $result]);
}

jsonResponse(false, Product::errorMessage($result));
