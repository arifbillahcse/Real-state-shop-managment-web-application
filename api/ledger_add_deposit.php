<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');

$result = Ledger::addDeposit(
    (int)($_POST['customer_id'] ?? 0),
    $_POST['entry_date'] ?? '',
    (float)($_POST['amount'] ?? 0),
    $_POST['method'] ?? '',
    $_POST['note'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'টাকা জমা এন্ট্রি হয়েছে। কাস্টমারকে SMS পাঠানো হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
