<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireAdminApi();

$result = Transfer::send((int)($_POST['id'] ?? 0));
if ($result === true) {
    jsonResponse(true, 'পণ্য গন্তব্য ব্রাঞ্চে পাঠানো হয়েছে।');
}
jsonResponse(false, Transfer::errorMessage($result));
