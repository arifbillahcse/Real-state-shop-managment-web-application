<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক ID দিন।');

Customer::deleteReference($id);
jsonResponse(true, 'রেফারেন্স ডিলিট হয়েছে।');
