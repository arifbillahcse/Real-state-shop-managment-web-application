<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

requireMethod('POST');
requireAdminApi();

$productId = (int)($_POST['product_id'] ?? 0);
$rows      = json_decode($_POST['rows'] ?? '[]', true);

if ($productId <= 0 || !is_array($rows) || empty($rows)) {
    jsonResponse(false, 'ভুল ডেটা।');
}

$toNullable = function ($v): ?float {
    if ($v === null || $v === '' || !is_numeric($v)) return null;
    return (float)$v;
};

foreach ($rows as $r) {
    $branchId = (int)($r['branch_id'] ?? 0);
    if ($branchId <= 0) continue;
    $result = Product::saveBranchPrice(
        $branchId,
        $productId,
        $toNullable($r['buy_price']       ?? null),
        $toNullable($r['sell_price']      ?? null),
        $toNullable($r['wholesale_price'] ?? null),
        $toNullable($r['min_stock']       ?? null),
        !isset($r['is_active']) || (int)$r['is_active'] === 1
    );
    if ($result !== true) {
        jsonResponse(false, Product::errorMessage($result));
    }
}

jsonResponse(true, 'ব্রাঞ্চ মূল্য সংরক্ষণ হয়েছে।');
