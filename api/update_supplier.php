<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Supplier.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক সাপ্লাইয়ার নির্বাচন করুন।');

$result = Supplier::updateSupplier($id, [
    'name'    => $_POST['name']    ?? null,
    'phone'   => $_POST['phone']   ?? null,
    'address' => $_POST['address'] ?? null,
]);

if ($result === true) {
    jsonResponse(true, 'সাপ্লাইয়ার আপডেট হয়েছে।');
}
jsonResponse(false, Supplier::errorMessage($result));
