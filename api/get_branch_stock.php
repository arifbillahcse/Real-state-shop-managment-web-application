<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';

requireMethod('GET');

// Branch-locked users can only read their own branch's stock.
$branchId = resolveBranchId($_GET['branch_id'] ?? null);
if ($branchId === null || $branchId <= 0) jsonResponse(false, 'সঠিক ব্রাঞ্চ নির্বাচন করুন।');

$stock = Stock::getBranchStock($branchId);
jsonResponse(true, 'OK', ['stock' => $stock]);
