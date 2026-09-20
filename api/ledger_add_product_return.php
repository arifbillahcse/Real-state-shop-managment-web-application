<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

// A branch-locked user always returns goods into their own branch; everyone
// else says which branch is taking them back.
$branchId = resolveBranchId($_POST['branch_id'] ?? null);

$result = Ledger::addProductReturn(
    $customerId,
    $_POST['entry_date'] ?? '',
    $items,
    $_POST['note'] ?? '',
    getUserId(),
    $branchId
);

if (is_int($result)) {
    jsonResponse(true, 'রিটার্ন পণ্য এন্ট্রি হয়েছে। পণ্য ব্রাঞ্চের স্টকে যুক্ত হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
