<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireMethod('POST');
requireBranchWriteApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');
requireOwnTransferSide($id, 'from');

$result = Stock::updateTransfer($id, [
    'product_id'     => $_POST['product_id']     ?? null,
    // Locked users can't reassign the sending branch.
    'from_branch_id' => lockedBranchId() ?? ($_POST['from_branch_id'] ?? null),
    'to_branch_id'   => $_POST['to_branch_id']   ?? null,
    'quantity'       => $_POST['quantity']       ?? null,
    'note'           => $_POST['note']           ?? null,
]);

if ($result === true) {
    jsonResponse(true, 'ট্রান্সফার আপডেট হয়েছে।');
}
jsonResponse(false, Stock::adjustmentErrorMessage($result));
