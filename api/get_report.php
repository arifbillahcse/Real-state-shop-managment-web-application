<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Report.php';

requireBranchWriteApi();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to']   ?? today());

// basic date sanity
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = today();

// Branch-locked users get a report covering only their own branch.
$branchId = resolveBranchId($_GET['branch_id'] ?? null);

$data = [
    'range'         => ['from' => $from, 'to' => $to],
    'branch_id'     => $branchId,
    'sales_summary' => Report::salesSummary($from, $to, $branchId),
    'daily_sales'   => Report::dailySales($from, $to, $branchId),
    'top_products'  => Report::topProducts($from, $to, 10, $branchId),
    'sales_by_type' => Report::salesByType($from, $to, $branchId),
    'purchase'      => Report::purchaseSummary($from, $to, $branchId),
    'payments'      => Report::paymentsSummary($from, $to, $branchId),
    'profit'        => Report::profitEstimate($from, $to, $branchId),
    'stock'         => Report::stockValuation($branchId),
    'customer_dues' => Report::customerDues($branchId),
];

jsonResponse(true, '', ['data' => $data]);
