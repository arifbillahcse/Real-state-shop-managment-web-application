<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Quotation.php';
requireMethod('POST');
requireBranchWriteApi();

$id     = (int)($_POST['id']     ?? 0);
$status = trim($_POST['status']  ?? '');
if ($id <= 0) jsonResponse(false, 'সঠিক আইডি দিন।');

$result = Quotation::updateStatus($id, $status);
if ($result === true) {
    $msg = $status === 'converted' ? 'বিক্রয়ে রূপান্তর হয়েছে।' : 'কোটেশন বাতিল হয়েছে।';
    jsonResponse(true, $msg);
}
$msgs = ['NOT_FOUND' => 'পাওয়া যায়নি।', 'NOT_ACTIVE' => 'সক্রিয় নয়।', 'INVALID_STATUS' => 'ভুল স্ট্যাটাস।'];
jsonResponse(false, $msgs[$result] ?? 'সমস্যা হয়েছে।');
