<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/SaleReturn.php';
jsonResponse(true, '', ['data' => SaleReturn::getAll($_GET)]);
