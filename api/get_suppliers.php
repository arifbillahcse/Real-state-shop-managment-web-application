<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Supplier.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $supplier = Supplier::getSupplierById($id);
    if ($supplier) jsonResponse(true, 'OK', ['supplier' => $supplier]);
    jsonResponse(false, 'সাপ্লাইয়ার খুঁজে পাওয়া যায়নি।');
}

$suppliers = Supplier::getSuppliers();
jsonResponse(true, 'OK', ['suppliers' => $suppliers, 'count' => count($suppliers)]);
