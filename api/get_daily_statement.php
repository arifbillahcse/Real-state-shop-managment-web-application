<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/DailyStatement.php';

$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!strtotime($date)) jsonResponse(false, 'সঠিক তারিখ দিন।');

// Branch-locked users get a statement covering only their own branch.
$branchId = resolveBranchId($_GET['branch_id'] ?? null);

jsonResponse(true, 'OK', ['statement' => DailyStatement::build($date, $branchId)]);
