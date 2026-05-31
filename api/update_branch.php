<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Branch.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'ব্রাঞ্চ আইডি দিন।');

$result = Branch::updateBranch(
    $id,
    $_POST['name']    ?? '',
    $_POST['address'] ?? '',
    $_POST['phone']   ?? ''
);

if ($result === true) {
    jsonResponse(true, 'ব্রাঞ্চ আপডেট হয়েছে।');
}
jsonResponse(false, Branch::errorMessage($result));
