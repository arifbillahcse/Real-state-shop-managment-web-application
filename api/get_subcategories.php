<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
jsonResponse(true, 'OK', ['subcategories' => Product::getSubcategories($categoryId)]);
