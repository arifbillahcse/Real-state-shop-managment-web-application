<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Branch.php';

requireMethod('GET');

jsonResponse(true, 'OK', ['data' => Branch::getBranches()]);
