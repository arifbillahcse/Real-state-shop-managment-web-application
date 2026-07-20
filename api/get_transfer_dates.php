<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$branchId = (int)($_GET['from_branch_id'] ?? 0) ?: null;
jsonResponse(true, 'OK', ['dates' => Transfer::getDates($branchId)]);
