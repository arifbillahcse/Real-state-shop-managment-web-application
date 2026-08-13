<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Quotation.php';
requirePostMethod();
requireBranchWriteApi();

$id    = (int)($_POST['id'] ?? 0);
$items = json_decode($_POST['items'] ?? '[]', true);
if ($id <= 0)       jsonResponse(false, 'সঠিক আইডি দিন।');
if (!is_array($items)) jsonResponse(false, 'আইটেম তথ্য সঠিক নয়।');

$result = Quotation::update($id, $_POST, $items);
if ($result === true) {
    jsonResponse(true, 'কোটেশন আপডেট হয়েছে।');
}
$msg = match($result) {
    'NOT_FOUND'    => 'কোটেশন খুঁজে পাওয়া যায়নি।',
    'NOT_ACTIVE'   => 'শুধুমাত্র সক্রিয় কোটেশন সম্পাদনা করা যাবে।',
    'NO_ITEMS'     => 'কমপক্ষে একটি পণ্য যোগ করুন।',
    'INVALID_ITEM' => 'পণ্যের তথ্য সঠিক নয়।',
    default        => 'সমস্যা হয়েছে।',
};
jsonResponse(false, $msg);
