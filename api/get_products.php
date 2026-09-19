<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Product.php';

$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
    ? (int)$_GET['category_id']
    : null;

// Single product fetch (for edit form)
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $product = Product::getProductById($id);
    if ($product) {
        jsonResponse(true, 'OK', ['product' => $product]);
    }
    jsonResponse(false, 'পণ্যটি খুঁজে পাওয়া যায়নি।');
}

$products = Product::getProducts($categoryId);
jsonResponse(true, 'OK', ['products' => $products, 'count' => count($products)]);
