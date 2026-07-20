<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

requireMethod('POST');

$action = $_POST['action'] ?? 'status';

if ($action === 'delete') {
    requireAdminApi();
    $result = Staff::deleteTask((int)($_POST['id'] ?? 0));
    if ($result === true) jsonResponse(true, 'কাজ ডিলিট হয়েছে।');
    jsonResponse(false, Staff::errorMessage($result));
}

$result = Staff::setTaskStatus(
    (int)($_POST['id'] ?? 0),
    $_POST['status'] ?? '',
    getUserId(),
    isAdminOrManager()
);

if ($result === true) {
    jsonResponse(true, 'কাজের স্ট্যাটাস আপডেট হয়েছে।');
}
jsonResponse(false, Staff::errorMessage($result));
