<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';

User::logout();
redirect(BASE_URL . '/index.php');
