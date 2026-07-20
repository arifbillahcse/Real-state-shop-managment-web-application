<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireAdminApi();

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$result = Ledger::addGoods(
    (int)($_POST['customer_id'] ?? 0),
    $_POST['entry_date'] ?? '',
    $items,
    (float)($_POST['unload_bill']    ?? 0),
    (float)($_POST['labor_bill']     ?? 0),
    (float)($_POST['transport_bill'] ?? 0),
    $_POST['note'] ?? '',
    ($_POST['finalize'] ?? '1') === '1',
    getUserId()
);

if (is_int($result)) {
    $msg = ($_POST['finalize'] ?? '1') === '1'
        ? 'মালামাল এন্ট্রি সম্পন্ন হয়েছে।'
        : 'খসড়া মেমো সংরক্ষণ হয়েছে।';
    jsonResponse(true, $msg, ['id' => $result]);
}
jsonResponse(false, Ledger::errorMessage($result));
