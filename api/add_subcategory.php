<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Category.php';

requireMethod('POST');
requireAdminApi();

$result = Product::addSubcategory(
    (int)($_POST['category_id'] ?? 0),
    $_POST['name'] ?? ''
);

if (is_int($result)) {
    jsonResponse(true, 'সাব-ক্যাটাগরি যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Product::errorMessage($result));
