<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Sale.php';
require_once __DIR__ . '/../classes/Stock.php';
requirePostMethod();
requireBranchWriteApi();

$id    = (int)($_POST['id'] ?? 0);
$items = json_decode($_POST['items'] ?? '[]', true);
if ($id <= 0)          jsonResponse(false, 'সঠিক আইডি দিন।');
if (!is_array($items)) jsonResponse(false, 'আইটেম তথ্য সঠিক নয়।');
requireOwnBranchRecord('sales', $id);

$result = Sale::updateSale($id, $_POST, $items);
if ($result === true) {
    jsonResponse(true, 'বিক্রয় আপডেট হয়েছে।');
}
jsonResponse(false, Sale::errorMessage(is_string($result) ? $result : 'DB_ERROR'));
