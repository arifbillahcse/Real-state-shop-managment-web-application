<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Installment.php';
jsonResponse(true, '', ['data' => Installment::getPlans($_GET)]);
