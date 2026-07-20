<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
$productId  = (int)($_GET['product_id']  ?? 0);
if ($customerId <= 0 || $productId <= 0) {
    jsonResponse(false, 'কাস্টমার ও পণ্য নির্বাচন করুন।');
}

jsonResponse(true, 'OK', [
    'rates' => Ledger::getProductRates($customerId, $productId),
]);
