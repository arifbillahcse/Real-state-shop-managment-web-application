<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$result = Ledger::addProductReturn(
    (int)($_POST['customer_id'] ?? 0),
    $_POST['entry_date'] ?? '',
    $items,
    $_POST['note'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'রিটার্ন পণ্য এন্ট্রি হয়েছে। পণ্য মূল স্টকে যুক্ত হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
