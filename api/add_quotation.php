<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Quotation.php';
requireMethod('POST');
requireBranchWriteApi();

$data  = $_POST;
$items = json_decode($_POST['items'] ?? '[]', true);

$result = Quotation::create($data, is_array($items) ? $items : []);
if (is_int($result)) {
    jsonResponse(true, 'কোটেশন তৈরি হয়েছে।', ['id' => $result]);
}
$msgs = ['NO_ITEMS' => 'কমপক্ষে একটি পণ্য যোগ করুন।',
         'INVALID_ITEM' => 'সঠিক পণ্য তথ্য দিন।',
         'DB_ERROR' => 'ডেটাবেস সমস্যা।'];
jsonResponse(false, $msgs[$result] ?? 'সমস্যা হয়েছে।');
