<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$branchId = (int)($_GET['branch_id'] ?? 0);
if ($branchId <= 0) jsonResponse(false, 'সঠিক ব্রাঞ্চ নির্বাচন করুন।');

jsonResponse(true, 'OK', [
    'entries' => Transfer::getIncoming($branchId, trim($_GET['date'] ?? '')),
]);
