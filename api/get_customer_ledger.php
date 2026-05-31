<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Payment.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) jsonResponse(false, 'সঠিক কাস্টমার নির্বাচন করুন।');

$ledger = Payment::getCustomerLedger($customerId);
if (empty($ledger)) jsonResponse(false, 'কাস্টমার খুঁজে পাওয়া যায়নি।');

jsonResponse(true, '', ['data' => $ledger]);
