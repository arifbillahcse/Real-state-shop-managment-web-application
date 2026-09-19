<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Installment.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক আইডি দিন।');
$p = Installment::getPlanById($id);
if (!$p) jsonResponse(false, 'পরিকল্পনা পাওয়া যায়নি।');
jsonResponse(true, '', ['data' => $p]);
