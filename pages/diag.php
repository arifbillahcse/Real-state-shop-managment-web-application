<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
header('Content-Type: text/plain; charset=utf-8');

function test(string $label, callable $fn): void {
    echo "=== $label ===\n";
    try {
        $result = $fn();
        echo "OK — " . (is_array($result) ? count($result) . " row(s)" : json_encode($result)) . "\n\n";
    } catch (Throwable $e) {
        echo "ERROR — " . $e->getMessage() . "\n\n";
    }
}

test('vw_current_stock exists', fn() =>
    Database::fetchAll("SELECT COUNT(*) AS c FROM vw_current_stock"));

test('vw_branch_stock exists', fn() =>
    Database::fetchAll("SELECT COUNT(*) AS c FROM vw_branch_stock"));

test('product_categories table', fn() =>
    Database::fetchAll("SELECT id, name FROM product_categories"));

test('products.category_id column', fn() =>
    Database::fetchAll("SELECT id, category_id FROM products LIMIT 3"));

test('products JOIN product_categories', fn() =>
    Database::fetchAll("SELECT p.id, p.name, pc.name AS cat FROM products p JOIN product_categories pc ON pc.id = p.category_id LIMIT 3"));

test('stock_inbound JOIN product_categories', fn() =>
    Database::fetchAll("SELECT si.id, pc.name AS product_type FROM stock_inbound si JOIN products p ON p.id = si.product_id JOIN product_categories pc ON pc.id = p.category_id LIMIT 3"));

test('users table — role column', fn() =>
    Database::fetchAll("SELECT id, username, role FROM users LIMIT 5"));

test('users table — branch_id column', fn() =>
    Database::fetchAll("SELECT id, username, branch_id FROM users LIMIT 5"));

echo "Done.\n";
