<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) jsonResponse(false, 'সঠিক কাস্টমার নির্বাচন করুন।');
requireVisibleCustomer($customerId);

jsonResponse(true, 'OK', [
    'entries'         => Ledger::getEntries($customerId),
    'balance'         => Ledger::balance($customerId),
    'product_summary' => Ledger::getProductSummary($customerId),
]);
