<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Quotation.php';
jsonResponse(true, '', ['data' => Quotation::getAll($_GET)]);
