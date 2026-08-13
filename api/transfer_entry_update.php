<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireBranchWriteApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক এন্ট্রি নির্বাচন করুন।');
requireOwnTransferSide($id, 'from');

$result = Transfer::updateEntry($id, $_POST);
if ($result === true) {
    jsonResponse(true, 'ট্রান্সফার এন্ট্রি আপডেট হয়েছে।');
}
jsonResponse(false, Transfer::errorMessage($result));
