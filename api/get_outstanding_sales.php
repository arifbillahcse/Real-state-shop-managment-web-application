<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Payment.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) jsonResponse(false, 'সঠিক কাস্টমার নির্বাচন করুন।');

$sales = Payment::getOutstandingSales($customerId);
jsonResponse(true, '', ['data' => $sales]);
