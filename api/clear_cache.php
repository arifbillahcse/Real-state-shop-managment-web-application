<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
requireAdminApi();

// There's no app-level data cache in this project — products/stock/etc.
// are all read live from the DB. What CAN go stale on shared hosting is
// PHP's own bytecode cache (OPcache) and file-stat cache, which keep
// serving the PREVIOUS deploy's code/files until they expire on their
// own. This endpoint forces that to happen immediately after a deploy.
$cleared = [];

if (function_exists('opcache_reset') && ini_get('opcache.enable')) {
    opcache_reset();
    $cleared[] = 'OPcache';
}
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    $cleared[] = 'APCu';
}
clearstatcache(true);

jsonResponse(true, $cleared
    ? ('ক্যাশ ক্লিয়ার হয়েছে: ' . implode(', ', $cleared) . '।')
    : 'এই সার্ভারে কোনো PHP ক্যাশ সক্রিয় নেই (ক্লিয়ার করার কিছু নেই)।'
);
