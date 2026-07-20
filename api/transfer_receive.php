<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');

$result = Transfer::receive(
    (int)($_POST['id'] ?? 0),
    $_POST['action'] ?? '',
    $_POST['note']   ?? '',
    getUserId()
);
if ($result === true) {
    $msg = ($_POST['action'] ?? '') === 'in'
        ? 'পণ্য এই ব্রাঞ্চের স্টকে যুক্ত হয়েছে।'
        : 'পণ্য প্রেরক ব্রাঞ্চে ফেরত পাঠানো হয়েছে।';
    jsonResponse(true, $msg);
}
jsonResponse(false, Transfer::errorMessage($result));
