<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

$type = $_GET['type'] ?? null;
if ($type !== null && !in_array($type, Product::TYPES, true)) {
    $type = null;
}

// Single product fetch (for edit form)
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $product = Product::getProductById($id);
    if ($product) {
        jsonResponse(true, 'OK', ['product' => $product]);
    }
    jsonResponse(false, 'পণ্যটি খুঁজে পাওয়া যায়নি।');
}

$products = Product::getProducts($type);
jsonResponse(true, 'OK', ['products' => $products, 'count' => count($products)]);
