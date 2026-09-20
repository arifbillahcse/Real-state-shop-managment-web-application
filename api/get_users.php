<?php
require_once __DIR__ . '/_guard.php';

requireStrictAdminApi();

$users = User::getAll();
jsonResponse(true, '', ['data' => $users]);
