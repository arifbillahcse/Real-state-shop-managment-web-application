<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

$id     = (int)trim($_POST['id']     ?? 0);
$action = trim($_POST['action'] ?? '');   // 'toggle_pin' | 'set_status'

if ($id <= 0) jsonResponse(false, 'সঠিক নোট নির্বাচন করুন।');

$row = Database::fetchOne('SELECT * FROM free_notes WHERE id = ? LIMIT 1', [$id]);
if (!$row) jsonResponse(false, 'নোটটি খুঁজে পাওয়া যায়নি।');

if ($action === 'toggle_pin') {
    $newPin = $row['is_pinned'] ? 0 : 1;
    Database::execute('UPDATE free_notes SET is_pinned = ? WHERE id = ?', [$newPin, $id]);
    jsonResponse(true, $newPin ? 'নোট পিন করা হয়েছে।' : 'পিন সরানো হয়েছে।', ['is_pinned' => $newPin]);
}

if ($action === 'set_status') {
    $newStatus = trim($_POST['status'] ?? '');
    if (!in_array($newStatus, ['pending', 'done'], true)) {
        jsonResponse(false, 'সঠিক স্ট্যাটাস দিন।');
    }
    Database::execute('UPDATE free_notes SET status = ? WHERE id = ?', [$newStatus, $id]);
    $msg = $newStatus === 'done' ? 'সফল হিসেবে চিহ্নিত হয়েছে।' : 'পেন্ডিং হিসেবে চিহ্নিত হয়েছে।';
    jsonResponse(true, $msg, ['status' => $newStatus]);
}

jsonResponse(false, 'অজানা অ্যাকশন।');
