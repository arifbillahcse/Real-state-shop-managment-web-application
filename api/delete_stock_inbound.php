<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';

requireMethod('POST');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');

$result = Stock::deleteStockInbound($id);
if ($result === true) {
    jsonResponse(true, 'স্টক রেকর্ড ডিলিট হয়েছে।');
}
jsonResponse(false, Stock::errorMessage($result));
