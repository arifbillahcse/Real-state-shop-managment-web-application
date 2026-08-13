<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Payment.php';

requireMethod('POST');

$customerId    = (int)($_POST['customer_id']    ?? 0);
$amount        = (float)($_POST['amount']        ?? 0);
$paymentMethod = trim($_POST['payment_method']  ?? 'cash');
$referenceNo   = trim($_POST['reference_no']    ?? '');
$paymentDate   = trim($_POST['payment_date']    ?? '');
$note          = trim($_POST['note']            ?? '');
$saleId        = isset($_POST['sale_id']) && $_POST['sale_id'] !== ''
                 ? (int)$_POST['sale_id'] : null;

if ($customerId <= 0) jsonResponse(false, 'সঠিক কাস্টমার নির্বাচন করুন।');
requireVisibleCustomer($customerId);
// Settling a specific invoice must be one of our own branch's invoices.
if ($saleId !== null) requireOwnBranchRecord('sales', $saleId);

$result = Payment::addPayment(
    $customerId, $amount, $paymentMethod,
    $referenceNo, $paymentDate, $note, $saleId
);

if (is_int($result)) {
    jsonResponse(true, 'পেমেন্ট সফলভাবে রেকর্ড করা হয়েছে।', ['id' => $result]);
} else {
    jsonResponse(false, Payment::errorMessage($result));
}
