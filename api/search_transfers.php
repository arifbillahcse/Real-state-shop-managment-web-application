<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

$q    = trim($_GET['q'] ?? '');
$type = ($_GET['type'] ?? 'customer') === 'driver' ? 'driver' : 'customer';
if ($q === '') jsonResponse(false, 'সার্চ টেক্সট দিন।');

jsonResponse(true, 'OK', ['entries' => Transfer::search($q, $type)]);
