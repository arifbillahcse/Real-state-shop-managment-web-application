<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$result = Ledger::addMoneyReturn(
    (int)($_POST['customer_id'] ?? 0),
    $_POST['entry_date'] ?? '',
    (float)($_POST['amount'] ?? 0),
    $_POST['reason']      ?? '',
    $_POST['received_by'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'টাকা ফেরত এন্ট্রি হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
