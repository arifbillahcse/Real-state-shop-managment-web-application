<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Agreement.php';

requireMethod('POST');
requireBranchWriteApi();

$result = Agreement::addDelivery(
    (int)($_POST['agreement_id'] ?? 0),
    $_POST['delivery_date'] ?? '',
    (int)($_POST['product_id'] ?? 0) ?: null,
    $_POST['product_name'] ?? '',
    (float)($_POST['quantity'] ?? 0),
    $_POST['unit'] ?? '',
    $_POST['note'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'ডেলিভারি এন্ট্রি হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Agreement::errorMessage($result));
