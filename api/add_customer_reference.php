<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');
// Matches delete_customer_reference.php — the UI hides this from staff, and
// the endpoint has to enforce the same thing.
requireBranchWriteApi();

$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);

$result = Customer::addReference(
    $customerId,
    $_POST['name']    ?? '',
    $_POST['address'] ?? '',
    $_POST['phone']   ?? '',
    $_POST['photo']   ?? '',
    (int)($_POST['ref_user_id'] ?? 0) ?: null
);

if (is_int($result)) {
    jsonResponse(true, 'রেফারেন্স যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Customer::errorMessage($result));
