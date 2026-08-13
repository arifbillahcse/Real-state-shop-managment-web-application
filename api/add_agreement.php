<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Agreement.php';

requireMethod('POST');
requireBranchWriteApi();

$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$result = Agreement::create(
    $customerId,
    $_POST['agreement_date'] ?? '',
    $items,
    (float)($_POST['deposit_amount'] ?? 0),
    $_POST['deposit_method'] ?? '',
    $_POST['note'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'চুক্তিপত্র তৈরি হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Agreement::errorMessage($result));
