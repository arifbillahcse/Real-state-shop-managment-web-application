<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

requireMethod('POST');
requireAdminApi();

$result = Staff::closeAssignment((int)($_POST['id'] ?? 0));
if ($result === true) {
    jsonResponse(true, 'হিসাব ট্রান্সফার বন্ধ হয়েছে।');
}
jsonResponse(false, Staff::errorMessage($result));
