<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Quotation.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক আইডি দিন।');
$q = Quotation::getById($id);
if (!$q) jsonResponse(false, 'কোটেশন পাওয়া যায়নি।');
jsonResponse(true, '', ['data' => $q]);
