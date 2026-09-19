<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

// Branch-locked users only see transfers they sent.
$branchId = resolveBranchId($_GET['from_branch_id'] ?? null);
jsonResponse(true, 'OK', ['dates' => Transfer::getDates($branchId)]);
