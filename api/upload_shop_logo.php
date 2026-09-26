<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['logo']['error'] ?? null;
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        jsonResponse(false, 'ছবির সাইজ সার্ভারের অনুমোদিত সীমার চেয়ে বড়।');
    }
    jsonResponse(false, 'ছবি আপলোড হয়নি।');
}

$file = $_FILES['logo'];
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

$dir = __DIR__ . '/../uploads/shop';
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    $err = error_get_last();
    error_log('upload_shop_logo: mkdir failed for ' . $dir . ' — ' . ($err['message'] ?? 'unknown'));
    jsonResponse(false, 'আপলোড ফোল্ডার তৈরি করা যায়নি। হোস্টিং-এ uploads/shop ফোল্ডারের পারমিশন (755) চেক করুন।');
}
if (!is_writable($dir)) {
    error_log('upload_shop_logo: dir not writable: ' . $dir);
    jsonResponse(false, 'আপলোড ফোল্ডারে লেখার অনুমতি নেই। হোস্টিং-এ uploads/shop ফোল্ডারের পারমিশন 755 করুন।');
}
@chmod($dir, 0755); // normalize in case the folder pre-existed with stricter perms

// One name always ("logo.<ext>"), not a timestamped one: settings.shop_logo
// stores a single current logo, so re-uploading should replace it in place
// rather than piling up orphaned files nothing points to. A stale file with
// a different extension from an earlier upload is cleaned up first.
foreach (['jpg', 'png', 'webp'] as $oldExt) {
    if ($oldExt !== $ext) @unlink("$dir/logo.$oldExt");
}
$name = 'logo.' . $ext;
$dest = "$dir/$name";
if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    $err = error_get_last();
    error_log('upload_shop_logo: move_uploaded_file failed — ' . ($err['message'] ?? 'unknown'));
    jsonResponse(false, 'ছবি সংরক্ষণ করা যায়নি।');
}

// Same cPanel/PHP-FPM permission quirk as the other upload endpoints:
// force world-readable so the web server's static-file process can serve it.
@chmod($dest, 0644);
if (!is_readable($dest)) {
    error_log("upload_shop_logo: $dest not readable after chmod — check hosting file ownership");
}

$path = 'uploads/shop/' . $name;
Setting::set('shop_logo', $path);
User::log('update_settings', 'settings', 0, 'Shop logo uploaded');

// Cache-busted so the settings page and every open invoice tab pick up the
// new image immediately instead of the browser's cached copy of the old one
// at the exact same "logo.png" path.
jsonResponse(true, 'লোগো আপলোড হয়েছে।', ['path' => $path, 'version' => time()]);
