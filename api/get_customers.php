<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

$customers = Customer::getCustomers();
jsonResponse(true, '', ['data' => $customers]);
