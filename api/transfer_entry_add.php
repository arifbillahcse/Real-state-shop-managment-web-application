<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireAdminApi();

$result = Transfer::addEntry($_POST, getUserId());

if (is_int($result)) {
    jsonResponse(true, 'ট্রান্সফার শিটে যুক্ত হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Transfer::errorMessage($result));
