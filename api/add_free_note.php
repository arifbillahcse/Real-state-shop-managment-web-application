<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

$customerName = trim($_POST['customer_name'] ?? '');
$note         = trim($_POST['note']          ?? '');
$noteDate     = trim($_POST['note_date']     ?? '');

if ($customerName === '') jsonResponse(false, 'কাস্টমারের নাম লিখুন।');
if ($note         === '') jsonResponse(false, 'নোট লিখুন।');
if ($noteDate     === '') $noteDate = date('Y-m-d');

$author = $_SESSION['user_name'] ?? 'অজানা';

$id = Database::insert(
    'INSERT INTO free_notes (customer_name, note, note_date, author) VALUES (?, ?, ?, ?)',
    [$customerName, $note, $noteDate, $author]
);

jsonResponse(true, 'নোট সংরক্ষণ হয়েছে।', ['id' => (int)$id]);
