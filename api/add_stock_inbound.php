<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Supplier.php';
require_once __DIR__ . '/../classes/Stock.php';

requireMethod('POST');
requireBranchWriteApi();

$supplierId = isset($_POST['supplier_id']) && $_POST['supplier_id'] !== ''
    ? (int)$_POST['supplier_id'] : null;

// Branch-locked users always buy into their own branch.
$branchId = resolveBranchId($_POST['branch_id'] ?? null);

$result = Stock::addStockInbound(
    (int)($_POST['product_id'] ?? 0),
    (float)($_POST['quantity']  ?? 0),
    (float)($_POST['buy_price'] ?? 0),
    $supplierId,
    $_POST['inbound_date'] ?? '',
    $_POST['note'] ?? '',
    $branchId
);

if (is_int($result)) {
    jsonResponse(true, 'স্টক সফলভাবে যোগ হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Stock::errorMessage($result));
