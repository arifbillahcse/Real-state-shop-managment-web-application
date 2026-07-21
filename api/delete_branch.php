<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Branch.php';

requireMethod('POST');
requireStrictAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'ব্রাঞ্চ আইডি দিন।');

$result = Branch::deleteBranch($id);

if ($result === true) {
    jsonResponse(true, 'ব্রাঞ্চ মুছে ফেলা হয়েছে।');
}
jsonResponse(false, Branch::errorMessage($result));
