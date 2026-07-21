<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Branch.php';

requireMethod('POST');
requireStrictAdminApi();

$result = Branch::addBranch(
    $_POST['name']    ?? '',
    $_POST['address'] ?? '',
    $_POST['phone']   ?? ''
);

if (is_int($result)) {
    jsonResponse(true, 'ব্রাঞ্চ যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Branch::errorMessage($result));
