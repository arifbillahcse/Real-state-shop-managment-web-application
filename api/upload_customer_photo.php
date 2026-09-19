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
@chmod($dir, 0755); // normalize in case the folder pre-existed with stricter perms

$name = 'c_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = "$dir/$name";
if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    $err = error_get_last();
    error_log('upload_customer_photo: move_uploaded_file failed — ' . ($err['message'] ?? 'unknown'));
    jsonResponse(false, 'ছবি সংরক্ষণ করা যায়নি।');
}

// On some cPanel/PHP-FPM setups, uploaded files land with permissions the
// web server's own static-file process can't read (upload succeeds, but
// the image 404s/403s when the browser requests it). Force world-readable.
@chmod($dest, 0644);
if (!is_readable($dest)) {
    error_log("upload_customer_photo: $dest not readable after chmod — check hosting file ownership");
}

jsonResponse(true, 'ছবি আপলোড হয়েছে।', ['path' => 'uploads/customers/' . $name]);
