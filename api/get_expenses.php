<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';

$from       = $_GET['from']        ?? null;
$to         = $_GET['to']          ?? null;
$categoryId = (int)($_GET['category_id'] ?? 0) ?: null;
$branchId   = (int)($_GET['branch_id']   ?? 0) ?: null;

$rows = Expense::getExpenses($from, $to, $categoryId, $branchId);
jsonResponse(true, '', ['data' => $rows]);
