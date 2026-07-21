<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['photo']['error'] ?? null;
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        jsonResponse(false, 'ছবির সাইজ সার্ভারের অনুমোদিত সীমার চেয়ে বড়।');
    }
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
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    $err = error_get_last();
    error_log('upload_customer_photo: mkdir failed for ' . $dir . ' — ' . ($err['message'] ?? 'unknown'));
    jsonResponse(false, 'আপলোড ফোল্ডার তৈরি করা যায়নি। হোস্টিং-এ uploads/customers ফোল্ডারের পারমিশন (755) চেক করুন।');
}
if (!is_writable($dir)) {
    error_log('upload_customer_photo: dir not writable: ' . $dir);
    jsonResponse(false, 'আপলোড ফোল্ডারে লেখার অনুমতি নেই। হোস্টিং-এ uploads/customers ফোল্ডারের পারমিশন 755 করুন।');
}

$name = 'c_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
if (!@move_uploaded_file($file['tmp_name'], "$dir/$name")) {
    $err = error_get_last();
    error_log('upload_customer_photo: move_uploaded_file failed — ' . ($err['message'] ?? 'unknown'));
    jsonResponse(false, 'ছবি সংরক্ষণ করা যায়নি।');
}

jsonResponse(true, 'ছবি আপলোড হয়েছে।', ['path' => 'uploads/customers/' . $name]);
