<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');
requireBranchWriteApi();   // admin + manager only

$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);
$note       = trim($_POST['note'] ?? '');

if ($customerId <= 0) jsonResponse(false, 'সঠিক গ্রাহক নির্বাচন করুন।');

$result = Customer::addNote($customerId, $note, $_SESSION['user_id'] ?? null);

if (is_int($result)) {
    jsonResponse(true, 'নোট যোগ করা হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Customer::errorMessage($result));
