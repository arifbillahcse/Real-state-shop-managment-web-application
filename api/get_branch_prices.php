<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

// Two modes:
//   product_id=N  → all branch rows for one product (pricing modal)
//   branch_id=N   → all product prices for one branch (sales form)
$productId = (int)($_GET['product_id'] ?? 0);
$branchId  = (int)($_GET['branch_id']  ?? 0);

if ($productId > 0) {
    jsonResponse(true, 'OK', ['prices' => Product::getBranchPrices($productId)]);
}
if ($branchId > 0) {
    jsonResponse(true, 'OK', ['prices' => Product::getBranchPriceMap($branchId)]);
}
jsonResponse(false, 'product_id বা branch_id প্রয়োজন।');
