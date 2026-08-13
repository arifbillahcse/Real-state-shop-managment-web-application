<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$date = trim($_GET['date'] ?? '');
if ($date === '' || !strtotime($date)) jsonResponse(false, 'সঠিক তারিখ দিন।');

// Branch-locked users only see their own branch's transfer sheet.
$branchId = resolveBranchId($_GET['from_branch_id'] ?? null);
jsonResponse(true, 'OK', ['entries' => Transfer::getSheet($date, $branchId)]);
