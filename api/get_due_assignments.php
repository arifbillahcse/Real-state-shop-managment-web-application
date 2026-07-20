<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Staff.php';

$staffId = (int)($_GET['staff_id'] ?? 0) ?: null;
// Staff only see their own assignments
if (isStaff()) $staffId = getUserId();

jsonResponse(true, 'OK', ['assignments' => Staff::getAssignments($staffId)]);
