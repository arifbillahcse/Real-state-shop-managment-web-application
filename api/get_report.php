<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Report.php';

requireAdminApi();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to']   ?? today());

// basic date sanity
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = today();

$data = [
    'range'         => ['from' => $from, 'to' => $to],
    'sales_summary' => Report::salesSummary($from, $to),
    'daily_sales'   => Report::dailySales($from, $to),
    'top_products'  => Report::topProducts($from, $to, 10),
    'sales_by_type' => Report::salesByType($from, $to),
    'purchase'      => Report::purchaseSummary($from, $to),
    'payments'      => Report::paymentsSummary($from, $to),
    'profit'        => Report::profitEstimate($from, $to),
    'stock'         => Report::stockValuation(),
    'customer_dues' => Report::customerDues(),
];

jsonResponse(true, '', ['data' => $data]);
