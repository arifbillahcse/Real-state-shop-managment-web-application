<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Installment.php';
requireMethod('POST');
requireAdminApi();

$id     = (int)($_POST['id']     ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$note   = trim($_POST['note']    ?? '');

if ($id <= 0) jsonResponse(false, 'সঠিক কিস্তি নির্বাচন করুন।');

$result = Installment::payInstallment($id, $amount, $note);
if ($result === true) jsonResponse(true, 'কিস্তি পরিশোধ সম্পন্ন হয়েছে।');

$msgs = ['NOT_FOUND' => 'কিস্তি পাওয়া যায়নি।',
         'ALREADY_PAID' => 'এই কিস্তি ইতিমধ্যে পরিশোধ হয়েছে।',
         'INVALID_AMOUNT' => 'পরিমাণ সঠিক নয়।'];
jsonResponse(false, $msgs[$result] ?? 'সমস্যা হয়েছে।');
