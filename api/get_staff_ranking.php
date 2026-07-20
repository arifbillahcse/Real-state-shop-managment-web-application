<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

requireAdminApi();

$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) jsonResponse(false, 'সঠিক মাস দিন (YYYY-MM)।');

jsonResponse(true, 'OK', ['ranking' => Staff::collectionRanking($month)]);
