<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';
requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');

Expense::deleteExpense($id);
jsonResponse(true, 'খরচ ডিলিট হয়েছে।');
