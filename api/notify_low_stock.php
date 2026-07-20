<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/AlertCenter.php';

requireMethod('POST');
requireAdminApi();

$branchId = (int)($_POST['branch_id'] ?? 0) ?: null;
$result   = AlertCenter::notifyManager($branchId);

if ($result === true) {
    jsonResponse(true, 'নোটিফিকেশন পাঠানো হয়েছে (গেটওয়ে সেট থাকলে SMS যাবে, নাহলে কিউতে থাকবে)।');
}
jsonResponse(false, [
    'NO_ALERTS' => 'এই মুহূর্তে কোনো স্টক সতর্কতা নেই।',
    'NO_PHONE'  => 'সেটিংসে সতর্কতা পাঠানোর ফোন নাম্বার (alert_phone / shop_phone) সেট করুন।',
][$result] ?? 'একটি সমস্যা হয়েছে।');
