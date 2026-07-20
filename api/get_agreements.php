<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Agreement.php';

$id         = (int)($_GET['id'] ?? 0);
$customerId = (int)($_GET['customer_id'] ?? 0);

if ($id > 0) {
    $agreement = Agreement::getById($id);
    if (!$agreement) jsonResponse(false, 'চুক্তিপত্র খুঁজে পাওয়া যায়নি।');
    jsonResponse(true, 'OK', ['agreement' => $agreement]);
}

if ($customerId > 0) {
    jsonResponse(true, 'OK', ['agreements' => Agreement::getForCustomer($customerId)]);
}

jsonResponse(false, 'customer_id বা id প্রয়োজন।');
