<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Supplier.php';

requireMethod('POST');
requireAdminApi();

$result = Supplier::addSupplier(
    $_POST['name']    ?? '',
    $_POST['phone']   ?? '',
    $_POST['address'] ?? ''
);

if (is_int($result)) {
    jsonResponse(true, 'সাপ্লাইয়ার যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Supplier::errorMessage($result));
