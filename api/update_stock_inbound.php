<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Supplier.php';
require_once __DIR__ . '/../classes/Stock.php';

requireMethod('POST');
requireBranchWriteApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক রেকর্ড নির্বাচন করুন।');
requireOwnBranchRecord('stock_inbound', $id);

$result = Stock::updateStockInbound($id, [
    'quantity'     => $_POST['quantity']     ?? null,
    'buy_price'    => $_POST['buy_price']    ?? null,
    'supplier_id'  => $_POST['supplier_id']  ?? null,
    'inbound_date' => $_POST['inbound_date'] ?? null,
    'note'         => $_POST['note']         ?? null,
    // Locked users can't move a record to a different branch.
    'branch_id'    => resolveBranchId($_POST['branch_id'] ?? null),
]);

if ($result === true) {
    jsonResponse(true, 'স্টক আপডেট হয়েছে।');
}
jsonResponse(false, Stock::errorMessage($result));
