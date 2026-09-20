<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

$userId = (int)($_GET['user_id'] ?? 0) ?: null;
$status = trim($_GET['status'] ?? '');

// Staff only see their own tasks
if (isStaff()) $userId = getUserId();

jsonResponse(true, 'OK', ['tasks' => Staff::getTasks($userId, $status)]);
