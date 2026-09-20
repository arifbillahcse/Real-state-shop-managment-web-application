<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireMethod('POST');
requireBranchWriteApi();

$productId     = (int)($_POST['product_id']      ?? 0);
// Branch-locked users can only send FROM their own branch.
$fromBranchId  = (int)(lockedBranchId() ?? ($_POST['from_branch_id'] ?? 0));
$toBranchId    = (int)($_POST['to_branch_id']    ?? 0);
$quantity      = (float)($_POST['quantity']       ?? 0);
$note          = trim($_POST['note'] ?? '');

$result = Stock::addTransfer($productId, $fromBranchId, $toBranchId, $quantity, $note);
if (is_int($result)) jsonResponse(true, 'স্টক ট্রান্সফার সফল হয়েছে।', ['id' => $result]);
jsonResponse(false, Stock::adjustmentErrorMessage($result));
