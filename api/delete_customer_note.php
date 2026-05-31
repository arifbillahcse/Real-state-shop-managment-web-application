<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Customer.php';

requireMethod('POST');
requireAdminApi();   // admin + manager only

$noteId = (int)($_POST['id'] ?? 0);
if ($noteId <= 0) jsonResponse(false, 'সঠিক নোট নির্বাচন করুন।');

Customer::deleteNote($noteId);
jsonResponse(true, 'নোট মুছে ফেলা হয়েছে।');
