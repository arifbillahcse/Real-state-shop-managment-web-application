<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireAdminApi();

$branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;
jsonResponse(true, 'OK', ['data' => Stock::getAdjustments($branchId)]);
