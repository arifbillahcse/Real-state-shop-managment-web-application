<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

requireMethod('POST');
requireAdminApi();

$result = Staff::assignDue(
    (int)($_POST['customer_id'] ?? 0),
    (int)($_POST['staff_id']    ?? 0),
    $_POST['note'] ?? '',
    getUserId()
);

if (is_int($result)) {
    jsonResponse(true, 'হিসাব স্টাফের কাছে ট্রান্সফার হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Staff::errorMessage($result));
