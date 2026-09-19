<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

$id     = (int)($_POST['id'] ?? 0);
$active = (int)($_POST['active'] ?? 0) === 1;

if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = User::setStatus($id, $active);
if ($result === true) {
    jsonResponse(true, $active ? 'অ্যাকাউন্ট সক্রিয় করা হয়েছে।' : 'অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে।');
} else {
    jsonResponse(false, User::errorMessage($result));
}
