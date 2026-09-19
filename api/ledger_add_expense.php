<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);

$result = Ledger::addExpense(
    $customerId,
    $_POST['entry_date'] ?? '',
    (float)($_POST['amount'] ?? 0),
    $_POST['description'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'খরচ এন্ট্রি হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
