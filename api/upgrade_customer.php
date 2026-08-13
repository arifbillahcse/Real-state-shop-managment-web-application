<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');
requireBranchWriteApi();

$id     = (int)($_POST['id'] ?? 0);
$bookNo = trim($_POST['book_no'] ?? '');
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = Customer::upgradeToFull($id, $bookNo);
if ($result === true) {
    $customer = Customer::getCustomerById($id);
    jsonResponse(true, 'ফুল একাউন্টে রূপান্তর হয়েছে।', [
        'account_no' => $customer['account_no'] ?? null,
    ]);
}
jsonResponse(false, Customer::errorMessage($result));
