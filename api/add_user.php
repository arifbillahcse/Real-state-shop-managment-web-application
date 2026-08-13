<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

$name     = trim($_POST['name']     ?? '');
$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');
$role     = trim($_POST['role']     ?? 'staff');
$branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

if ($name === '')              jsonResponse(false, 'নাম দিন।');
if ($username === '')          jsonResponse(false, 'ইউজারনেম দিন।');
if (strlen($password) < 4)     jsonResponse(false, 'পাসওয়ার্ড কমপক্ষে ৪ অক্ষরের হতে হবে।');
if (!in_array($role, User::ROLES, true)) jsonResponse(false, 'সঠিক রোল নির্বাচন করুন।');

$result = User::create($name, $username, $password, $role, $branchId);
if (is_int($result)) {
    jsonResponse(true, 'ব্যবহারকারী যোগ করা হয়েছে।', ['id' => $result]);
} else {
    jsonResponse(false, User::errorMessage($result));
}
