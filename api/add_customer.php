<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');

$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$address = trim($_POST['address'] ?? '');

$phones = json_decode($_POST['phones'] ?? '[]', true);
if (!is_array($phones)) $phones = [];

$result = Customer::addCustomer($name, $phone, $address, [
    'whatsapp'     => $_POST['whatsapp']     ?? '',
    'imo'          => $_POST['imo']          ?? '',
    'photo'        => $_POST['photo']        ?? '',
    'book_no'      => $_POST['book_no']      ?? '',
    'account_type' => $_POST['account_type'] ?? 'full',
    'due_limit'    => $_POST['due_limit']    ?? 0,
    'phones'       => $phones,
]);

if (is_int($result)) {
    $customer = Customer::getCustomerById($result);
    jsonResponse(true, 'কাস্টমার যোগ করা হয়েছে।', [
        'id'         => $result,
        'account_no' => $customer['account_no'] ?? null,
    ]);
} else {
    jsonResponse(false, Customer::errorMessage($result));
}
