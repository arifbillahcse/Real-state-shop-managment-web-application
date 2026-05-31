<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

$allowed = [
    'shop_name', 'shop_address', 'shop_phone',
    'shop_email', 'currency', 'invoice_prefix',
];

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
