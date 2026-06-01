<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/SaleReturn.php';
requireMethod('POST');
requireAdminApi();

$saleId = (int)($_POST['sale_id'] ?? 0);
$reason = trim($_POST['reason']   ?? '');
$note   = trim($_POST['note']     ?? '');
$items  = json_decode($_POST['items'] ?? '[]', true);

if ($saleId <= 0)          jsonResponse(false, 'সঠিক বিক্রয় নির্বাচন করুন।');
if ($reason === '')         jsonResponse(false, 'কারণ লিখুন।');
if (!is_array($items))     jsonResponse(false, 'পণ্য তালিকা সঠিক নয়।');

$result = SaleReturn::create($saleId, $items, $reason, $note);
if (is_int($result)) jsonResponse(true, 'পণ্য ফেরত সম্পন্ন হয়েছে এবং স্টকে যোগ হয়েছে।', ['id' => $result]);

$msgs = ['NO_ITEMS' => 'কমপক্ষে একটি পণ্য নির্বাচন করুন।',
         'SALE_NOT_FOUND' => 'বিক্রয় পাওয়া যায়নি।',
         'SALE_CANCELLED' => 'বাতিল বিক্রয়ে ফেরত দেওয়া যাবে না।',
         'DB_ERROR' => 'ডেটাবেস সমস্যা।'];
jsonResponse(false, $msgs[$result] ?? 'সমস্যা হয়েছে।');
