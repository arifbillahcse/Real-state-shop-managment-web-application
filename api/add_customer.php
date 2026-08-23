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

if (!is_int($result)) {
    jsonResponse(false, Customer::errorMessage($result));
}

// Optional reference person captured on the same form. The customer is
// already saved at this point, so a bad reference must not fail the whole
// request — report it alongside the success instead of losing the customer.
$refName  = trim($_POST['ref_name'] ?? '');
$refNote  = '';
if ($refName !== '') {
    $ref = Customer::addReference(
        $result,
        $refName,
        $_POST['ref_address'] ?? '',
        $_POST['ref_phone']   ?? '',
        $_POST['ref_photo']   ?? ''
    );
    $refNote = is_int($ref)
        ? ' রেফারেন্সও যোগ হয়েছে।'
        : ' তবে রেফারেন্স যোগ করা যায়নি — কাস্টমারের রেফারেন্স তালিকা থেকে আবার দিন।';
}

$customer = Customer::getCustomerById($result);
jsonResponse(true, 'কাস্টমার যোগ করা হয়েছে।' . $refNote, [
    'id'         => $result,
    'account_no' => $customer['account_no'] ?? null,
]);
