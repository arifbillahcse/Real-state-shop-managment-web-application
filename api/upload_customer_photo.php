<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'ছবি আপলোড হয়নি।');
}

$file = $_FILES['photo'];
if ($file['size'] > 3 * 1024 * 1024) {
    jsonResponse(false, 'ছবির সাইজ সর্বোচ্চ ৩ MB।');
}

$info = @getimagesize($file['tmp_name']);
$allowed = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];
if (!$info || !isset($allowed[$info[2]])) {
    jsonResponse(false, 'শুধু JPG / PNG / WebP ছবি দেওয়া যাবে।');
}
$ext = $allowed[$info[2]];

$dir = __DIR__ . '/../uploads/customers';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    jsonResponse(false, 'আপলোড ফোল্ডার তৈরি করা যায়নি।');
}

$name = 'c_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
    jsonResponse(false, 'ছবি সংরক্ষণ করা যায়নি।');
}

jsonResponse(true, 'ছবি আপলোড হয়েছে।', ['path' => 'uploads/customers/' . $name]);
