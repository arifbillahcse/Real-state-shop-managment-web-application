<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Stock.php';

// Current stock of all products
$stock = Stock::getAllStock();
jsonResponse(true, 'OK', ['stock' => $stock, 'count' => count($stock)]);
