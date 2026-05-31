<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');

$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$address = trim($_POST['address'] ?? '');

$result = Customer::addCustomer($name, $phone, $address);
if (is_int($result)) {
    jsonResponse(true, 'কাস্টমার যোগ করা হয়েছে।', ['id' => $result]);
} else {
    jsonResponse(false, Customer::errorMessage($result));
}
