<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

requireMethod('POST');
requireAdminApi();

$result = Staff::addTask(
    (int)($_POST['user_id'] ?? 0),
    $_POST['title']    ?? '',
    $_POST['details']  ?? '',
    $_POST['due_date'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'কাজ অ্যাসাইন করা হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Staff::errorMessage($result));
