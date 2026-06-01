<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Sale.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$sale = Sale::getSaleById($id);
if (!$sale) jsonResponse(false, 'বিক্রয় রেকর্ড পাওয়া যায়নি।');

jsonResponse(true, '', $sale);
