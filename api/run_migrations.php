<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Migrator.php';

requireMethod('POST');
// Schema changes are an admin action, like settings and user management.
requireStrictAdminApi();

$result = Migrator::runPending();

jsonResponse($result['failed'] === null, $result['message'], [
    'applied' => $result['applied'],
    'failed'  => $result['failed'],
]);
