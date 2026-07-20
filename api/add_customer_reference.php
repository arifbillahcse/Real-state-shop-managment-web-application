<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');

$result = Customer::addReference(
    (int)($_POST['customer_id'] ?? 0),
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
