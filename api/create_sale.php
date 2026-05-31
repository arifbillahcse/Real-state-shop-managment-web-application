<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Sale.php';

requireMethod('POST');

$customerId    = (isset($_POST['customer_id']) && $_POST['customer_id'] !== '')
                 ? (int)$_POST['customer_id'] : null;
$itemsJson     = $_POST['items']          ?? '[]';
$discount      = (float)($_POST['discount']      ?? 0);
$paidAmount    = (float)($_POST['paid_amount']   ?? 0);
$paymentMethod = trim($_POST['payment_method']   ?? 'cash');
$saleDate      = trim($_POST['sale_date']         ?? '');
$note          = trim($_POST['note']              ?? '');

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    jsonResponse(false, 'পণ্যের তালিকা সঠিক নয়।');
}

$result = Sale::createSale($customerId, $items, $discount, $paidAmount, $paymentMethod, $saleDate, $note);

if (is_int($result)) {
    $sale = Sale::getSaleById($result);
    jsonResponse(true, 'বিক্রয় সফলভাবে সম্পন্ন হয়েছে।', [
        'sale_id'        => $result,
        'invoice_number' => $sale['invoice_number'] ?? '',
    ]);
} else {
    jsonResponse(false, Sale::errorMessage($result));
}
