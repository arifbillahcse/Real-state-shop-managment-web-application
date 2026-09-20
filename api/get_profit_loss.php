<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Expense.php';
require_once __DIR__ . '/../classes/Report.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$sales    = Report::salesSummary($from, $to);
$expenses = Expense::getTotalExpenses($from, $to);
$catBreak = Expense::getCategoryTotals($from, $to);

$revenue    = (float)($sales['total']  ?? 0);
$paid       = (float)($sales['paid']   ?? 0);
$due        = (float)($sales['due']    ?? 0);
$netProfit  = $revenue - $expenses;

jsonResponse(true, '', [
    'revenue'         => $revenue,
    'paid'            => $paid,
    'due'             => $due,
    'total_expenses'  => $expenses,
    'net_profit'      => $netProfit,
    'sale_count'      => (int)($sales['sale_count'] ?? 0),
    'category_breakdown' => $catBreak,
]);
