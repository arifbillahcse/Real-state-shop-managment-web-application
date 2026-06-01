<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';
requireMethod('POST');
requireAdminApi();

$result = Expense::addCategory(
    $_POST['name'] ?? '',
    $_POST['icon'] ?? 'bi-receipt'
);

if (is_int($result) && $result > 0) {
    jsonResponse(true, 'ক্যাটাগরি যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Expense::errorMessage((string)$result));
