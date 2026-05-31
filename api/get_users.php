<?php
require_once __DIR__ . '/_guard.php';

requireAdminApi();

$users = User::getAll();
jsonResponse(true, '', ['data' => $users]);
