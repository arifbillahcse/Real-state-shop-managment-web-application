<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireStrictAdminApi();

$allowed = [
    'shop_name', 'shop_address', 'shop_phone',
    'shop_email', 'shop_logo', 'currency', 'invoice_prefix',
    // Notification / SMS gateway (used by deposit SMS + low-stock alerts)
    'sms_gateway_url', 'sms_api_key', 'alert_phone',
];

// Clearing the logo (removeShopLogo()) leaves the old file orphaned on
// disk otherwise — upload_shop_logo.php only cleans up a stale extension
// when a NEW file replaces it, not when the setting is cleared to ''.
if (array_key_exists('shop_logo', $_POST) && trim((string)$_POST['shop_logo']) === '') {
    $oldLogo = Setting::get('shop_logo');
    if ($oldLogo !== '') {
        $oldPath = __DIR__ . '/../' . ltrim($oldLogo, '/');
        if (is_file($oldPath)) @unlink($oldPath);
    }
}

$saved = 0;
foreach ($allowed as $key) {
    if (array_key_exists($key, $_POST)) {
        Setting::set($key, trim((string)$_POST[$key]));
        $saved++;
    }
}

if ($saved === 0) {
    jsonResponse(false, 'সংরক্ষণ করার মতো কিছু নেই।');
}

User::log('update_settings', 'settings', 0, 'Shop settings updated');
jsonResponse(true, 'সেটিংস সংরক্ষণ করা হয়েছে।');
