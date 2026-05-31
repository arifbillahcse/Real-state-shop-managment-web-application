<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Sale.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

$result = Sale::cancelSale($id);
if ($result === true) {
    jsonResponse(true, 'বিক্রয় বাতিল করা হয়েছে।');
} else {
    jsonResponse(false, Sale::errorMessage($result));
}
