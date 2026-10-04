<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

// Cross-branch performance figures — same audience as collection ranking
// and referral sales.
requireAdminApi();

$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) jsonResponse(false, 'সঠিক মাস দিন।');

$from = $month . '-01';
$to   = date('Y-m-t', strtotime($from));

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId > 0) {
    jsonResponse(true, 'OK', ['customers' => Staff::customerEntryDetail($userId, $from, $to)]);
}

jsonResponse(true, 'OK', ['rows' => Staff::customerEntrySummary($from, $to)]);
