<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক আইডি দিন।');

jsonResponse(true, 'OK', ['history' => Transfer::getHistory($id)]);
