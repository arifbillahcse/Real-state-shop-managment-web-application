<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Agreement.php';

requireMethod('POST');
requireAdminApi();

$result = Agreement::setStatus(
    (int)($_POST['id'] ?? 0),
    $_POST['status'] ?? ''
);

if ($result === true) {
    jsonResponse(true, 'চুক্তিপত্রের স্ট্যাটাস আপডেট হয়েছে।');
}
jsonResponse(false, Agreement::errorMessage($result));
