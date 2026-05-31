<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';

// Single record (for edit form)
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $row = Stock::getStockInboundById($id);
    if ($row) jsonResponse(true, 'OK', ['record' => $row]);
    jsonResponse(false, 'রেকর্ডটি খুঁজে পাওয়া যায়নি।');
}

// Full inbound history (optionally filter by product)
$productId = (int)($_GET['product_id'] ?? 0) ?: null;
$history   = Stock::getStockInbound($productId);
jsonResponse(true, 'OK', ['history' => $history, 'count' => count($history)]);
