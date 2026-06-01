<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');

$qty = (float)($_POST['quantity'] ?? 0);
$dir = $_POST['direction'] ?? 'add';   // 'add' or 'subtract'
$finalQty = $dir === 'subtract' ? -abs($qty) : abs($qty);

$result = Stock::updateAdjustment($id, [
    'product_id' => $_POST['product_id'] ?? null,
    'quantity'   => $finalQty,
    'reason'     => $_POST['reason']    ?? null,
    'note'       => $_POST['note']      ?? null,
    'branch_id'  => $_POST['branch_id'] ?? null,
]);

if ($result === true) {
    jsonResponse(true, 'স্টক সংশোধন আপডেট হয়েছে।');
}
jsonResponse(false, Stock::adjustmentErrorMessage($result));
