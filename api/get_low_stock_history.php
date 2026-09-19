<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/AlertCenter.php';

$days = max(7, min(365, (int)($_GET['days'] ?? 90)));
jsonResponse(true, 'OK', ['history' => AlertCenter::history($days)]);
