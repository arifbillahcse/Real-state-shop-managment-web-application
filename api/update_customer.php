<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$data = $_POST;
if (isset($_POST['phones'])) {
    $phones = json_decode($_POST['phones'], true);
    $data['phones'] = is_array($phones) ? $phones : [];
}

$result = Customer::updateCustomer($id, $data);
if ($result === true) {
    jsonResponse(true, 'কাস্টমার আপডেট করা হয়েছে।');
} else {
    jsonResponse(false, Customer::errorMessage($result));
}
