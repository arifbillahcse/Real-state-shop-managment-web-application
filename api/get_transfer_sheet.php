<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$date = trim($_GET['date'] ?? '');
if ($date === '' || !strtotime($date)) jsonResponse(false, 'সঠিক তারিখ দিন।');

$branchId = (int)($_GET['from_branch_id'] ?? 0) ?: null;
jsonResponse(true, 'OK', ['entries' => Transfer::getSheet($date, $branchId)]);
