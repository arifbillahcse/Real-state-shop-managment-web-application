<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(false, 'সঠিক পণ্য নির্বাচন করুন।');
}

$result = Product::deleteProduct($id);

if ($result === true) {
    jsonResponse(true, 'পণ্য ডিলিট হয়েছে।');
}

jsonResponse(false, Product::errorMessage($result));
