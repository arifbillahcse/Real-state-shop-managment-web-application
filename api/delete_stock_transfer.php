<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireMethod('POST');
requireBranchWriteApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');
requireOwnTransferSide($id, 'from');

$result = Stock::deleteTransfer($id);
if ($result === true) {
    jsonResponse(true, 'ট্রান্সফার রেকর্ড ডিলিট হয়েছে।');
}
jsonResponse(false, Stock::adjustmentErrorMessage($result));
