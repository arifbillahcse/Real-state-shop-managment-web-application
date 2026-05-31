<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

$id       = (int)($_POST['id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$role     = trim($_POST['role'] ?? 'staff');
$branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = User::updateUser($id, $name, $role, $branchId);
if ($result === true) {
    jsonResponse(true, 'ব্যবহারকারী আপডেট করা হয়েছে।');
} else {
    jsonResponse(false, User::errorMessage($result));
}
