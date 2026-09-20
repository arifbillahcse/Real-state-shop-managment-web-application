<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireBranchWriteApi();

$branchId = resolveBranchId($_GET['branch_id'] ?? null);
jsonResponse(true, 'OK', ['data' => Stock::getTransfers($branchId)]);
