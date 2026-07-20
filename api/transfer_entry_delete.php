<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireAdminApi();

$result = Transfer::deleteEntry((int)($_POST['id'] ?? 0));
if ($result === true) {
    jsonResponse(true, 'ট্রান্সফার এন্ট্রি ডিলিট হয়েছে।');
}
jsonResponse(false, Transfer::errorMessage($result));
