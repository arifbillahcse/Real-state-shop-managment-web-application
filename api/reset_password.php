<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

$id       = (int)($_POST['id'] ?? 0);
$password = (string)($_POST['password'] ?? '');

if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = User::resetPassword($id, $password);
if ($result === true) {
    jsonResponse(true, 'পাসওয়ার্ড পরিবর্তন করা হয়েছে।');
} else {
    jsonResponse(false, User::errorMessage($result));
}
