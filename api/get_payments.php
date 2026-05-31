<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Payment.php';

$filters = [];
if (!empty($_GET['customer_id'])) $filters['customer_id'] = (int)$_GET['customer_id'];
if (!empty($_GET['date_from']))   $filters['date_from']   = trim($_GET['date_from']);
if (!empty($_GET['date_to']))     $filters['date_to']     = trim($_GET['date_to']);

$payments = Payment::getPayments($filters);
jsonResponse(true, '', ['data' => $payments]);
