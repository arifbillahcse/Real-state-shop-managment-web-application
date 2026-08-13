<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/AlertCenter.php';

// Branch-locked users only see their own branch's alerts.
$branchId     = resolveBranchId($_GET['branch_id'] ?? null);
$includeGreen = ($_GET['all'] ?? '') === '1';

// Loading the center also records today's alerts for history tracking
AlertCenter::logToday($branchId);

jsonResponse(true, 'OK', [
    'alerts' => AlertCenter::getAlerts($branchId, $includeGreen),
]);
