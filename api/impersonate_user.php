<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = User::impersonate($id);
if ($result === true) {
    jsonResponse(true, 'লগইন করা হয়েছে।');
}
jsonResponse(false, User::errorMessage($result));
