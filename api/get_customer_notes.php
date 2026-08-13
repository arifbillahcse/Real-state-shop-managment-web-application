<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    jsonResponse(false, 'সঠিক গ্রাহক নির্বাচন করুন।');
}
requireVisibleCustomer($customerId);

jsonResponse(true, 'OK', ['notes' => Customer::getNotes($customerId)]);
