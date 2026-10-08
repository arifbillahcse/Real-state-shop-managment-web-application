<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

// Branch-locked users only see what their own branch has received/returned.
$branchId = resolveBranchId($_GET['branch_id'] ?? null);
if ($branchId === null || $branchId <= 0) jsonResponse(false, 'সঠিক ব্রাঞ্চ নির্বাচন করুন।');

jsonResponse(true, 'OK', [
    'entries' => Transfer::getReceivedHistory($branchId, trim($_GET['date'] ?? '')),
]);
