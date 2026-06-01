<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';
requireMethod('POST');
requireAdminApi();

$result = Expense::addExpense([
    'category_id'  => $_POST['category_id']  ?? null,
    'branch_id'    => $_POST['branch_id']    ?? null,
    'amount'       => $_POST['amount']       ?? 0,
    'expense_date' => $_POST['expense_date'] ?? '',
    'description'  => $_POST['description']  ?? '',
    'created_by'   => $_SESSION['user_id']   ?? null,
]);

if (is_int($result) && $result > 0) {
    jsonResponse(true, 'খরচ সংরক্ষণ হয়েছে।');
}
jsonResponse(false, Expense::errorMessage((string)$result));
