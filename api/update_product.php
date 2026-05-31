<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(false, 'সঠিক পণ্য নির্বাচন করুন।');
}

$result = Product::updateProduct($id, [
    'type'       => $_POST['type']       ?? null,
    'name'       => $_POST['name']       ?? null,
    'size_brand' => $_POST['size_brand'] ?? null,
    'unit'       => $_POST['unit']       ?? null,
    'buy_price'  => $_POST['buy_price']  ?? null,
    'sell_price' => $_POST['sell_price'] ?? null,
    'min_stock'  => $_POST['min_stock']  ?? null,
]);

if ($result === true) {
    jsonResponse(true, 'পণ্য সফলভাবে আপডেট হয়েছে।');
}

jsonResponse(false, Product::errorMessage($result));
