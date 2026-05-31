<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';
requireMethod('POST');
requireAdminApi();

$productId = (int)($_POST['product_id'] ?? 0);
$qty       = (float)($_POST['quantity'] ?? 0);
$dir       = $_POST['direction'] ?? 'add';   // 'add' or 'subtract'
$reason    = trim($_POST['reason'] ?? 'other');
$note      = trim($_POST['note']   ?? '');
$branchId  = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

$finalQty = $dir === 'subtract' ? -abs($qty) : abs($qty);

$result = Stock::addAdjustment($productId, $finalQty, $reason, $note, $branchId);
if (is_int($result)) jsonResponse(true, 'স্টক সংশোধন সফল হয়েছে।', ['id' => $result]);
jsonResponse(false, Stock::adjustmentErrorMessage($result));
