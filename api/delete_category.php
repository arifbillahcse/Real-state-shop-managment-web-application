<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Category.php';

requireMethod('POST');
requireAdminApi();

$id     = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ক্যাটাগরি নির্বাচন করুন।');

$result = Category::delete($id);
if ($result === true) {
    jsonResponse(true, 'ক্যাটাগরি মুছে ফেলা হয়েছে।');
}
jsonResponse(false, Category::errorMessage($result));
