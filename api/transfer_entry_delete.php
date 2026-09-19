<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireBranchWriteApi();

$id = (int)($_POST['id'] ?? 0);
requireOwnTransferSide($id, 'from');

$result = Transfer::deleteEntry($id);
if ($result === true) {
    jsonResponse(true, 'ট্রান্সফার এন্ট্রি ডিলিট হয়েছে।');
}
jsonResponse(false, Transfer::errorMessage($result));
