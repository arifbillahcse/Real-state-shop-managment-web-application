<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
require_once __DIR__ . '/../classes/Branch.php';

$stock    = Stock::getAllBranchStock();
$branches = Branch::getBranches();
jsonResponse(true, 'OK', ['stock' => $stock, 'branches' => $branches]);
