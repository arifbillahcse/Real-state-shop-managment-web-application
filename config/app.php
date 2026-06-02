<?php

define('APP_NAME', 'রড সিমেন্ট ম্যানেজমেন্ট');
define('APP_VERSION', '2.2.1');
// Auto-detect base URL; override with env var BASE_URL if set
if (!defined('BASE_URL')) {
    $detectedUrl = (isset($_SERVER['BASE_URL']))
        ? $_SERVER['BASE_URL']
        : (function () {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // If app lives in a subdirectory, set BASE_URL env var instead
            return $scheme . '://' . $host;
        })();
    define('BASE_URL', rtrim($detectedUrl, '/'));
}
define('ROOT_PATH', dirname(__DIR__));

// Session lifetime in seconds (2 hours)
define('SESSION_LIFETIME', 7200);

// Timezone
date_default_timezone_set('Asia/Dhaka');
