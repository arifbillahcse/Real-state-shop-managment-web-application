<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Installment.php';
requireMethod('POST');
requireAdminApi();

$result = Installment::createPlan($_POST);
if (is_int($result)) jsonResponse(true, 'কিস্তি পরিকল্পনা তৈরি হয়েছে।', ['id' => $result]);

$msgs = ['NAME_REQUIRED' => 'কাস্টমারের নাম লিখুন।',
         'INVALID_AMOUNT' => 'মোট পরিমাণ সঠিক নয়।',
         'INVALID_COUNT'  => 'কিস্তি সংখ্যা সঠিক নয়।',
         'DB_ERROR' => 'ডেটাবেস সমস্যা।'];
jsonResponse(false, $msgs[$result] ?? 'সমস্যা হয়েছে।');
