<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';
requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');

$result = Expense::updateExpense($id, [
    'category_id'  => $_POST['category_id']  ?? null,
    'branch_id'    => $_POST['branch_id']    ?? null,
    'amount'       => $_POST['amount']       ?? 0,
    'expense_date' => $_POST['expense_date'] ?? '',
    'description'  => $_POST['description']  ?? '',
]);

if ($result === true) jsonResponse(true, 'খরচ আপডেট হয়েছে।');
jsonResponse(false, Expense::errorMessage((string)$result));
