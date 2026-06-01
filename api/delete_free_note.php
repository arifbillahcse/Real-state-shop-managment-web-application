<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'সঠিক নোট নির্বাচন করুন।');

$row = Database::fetchOne('SELECT id FROM free_notes WHERE id = ? LIMIT 1', [$id]);
if (!$row) jsonResponse(false, 'নোটটি খুঁজে পাওয়া যায়নি।');

Database::execute('DELETE FROM free_notes WHERE id = ?', [$id]);
jsonResponse(true, 'নোট মুছে ফেলা হয়েছে।');
