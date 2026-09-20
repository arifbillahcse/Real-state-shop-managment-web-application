<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';

$from       = $_GET['from']        ?? null;
$to         = $_GET['to']          ?? null;
$categoryId = (int)($_GET['category_id'] ?? 0) ?: null;
// Branch-locked users only ever see their own branch's expenses.
$branchId   = resolveBranchId($_GET['branch_id'] ?? null);

$rows = Expense::getExpenses($from, $to, $categoryId, $branchId);
jsonResponse(true, '', ['data' => $rows]);
