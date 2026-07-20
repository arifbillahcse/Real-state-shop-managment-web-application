<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$customer = Customer::getCustomerById($id);
if (!$customer) jsonResponse(false, 'কাস্টমার খুঁজে পাওয়া যায়নি।');

jsonResponse(true, 'OK', [
    'customer'   => $customer,
    'phones'     => Customer::getPhones($id),
    'references' => Customer::getReferences($id),
]);
