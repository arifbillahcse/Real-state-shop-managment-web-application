<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Category.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(false, 'সঠিক পণ্য নির্বাচন করুন।');
}

$data = [
    'category_id'     => $_POST['category_id']     ?? null,
    'name'            => $_POST['name']            ?? null,
    'size_brand'      => $_POST['size_brand']      ?? null,
    'unit'            => $_POST['unit']            ?? null,
    'buy_price'       => $_POST['buy_price']       ?? null,
    'sell_price'      => $_POST['sell_price']      ?? null,
    'min_stock'       => $_POST['min_stock']       ?? null,
    'product_code'    => $_POST['product_code']    ?? null,
    'wholesale_price' => $_POST['wholesale_price'] ?? null,
];
if (isset($_POST['subcategory_id'])) $data['subcategory_id'] = $_POST['subcategory_id'];
if (isset($_POST['image']))          $data['image']          = $_POST['image'];

$result = Product::updateProduct($id, $data);

if ($result === true) {
    jsonResponse(true, 'পণ্য সফলভাবে আপডেট হয়েছে।');
}

jsonResponse(false, Product::errorMessage($result));
