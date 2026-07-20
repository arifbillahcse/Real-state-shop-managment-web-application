<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

requireMethod('POST');
requireAdminApi();

$result = Product::deleteSubcategory((int)($_POST['id'] ?? 0));

if ($result === true) {
    jsonResponse(true, 'সাব-ক্যাটাগরি ডিলিট হয়েছে।');
}
jsonResponse(false, Product::errorMessage($result));
