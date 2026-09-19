<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$result = Ledger::deleteDraft((int)($_POST['id'] ?? 0));

if ($result === true) {
    jsonResponse(true, 'খসড়া মেমো ডিলিট হয়েছে।');
}
jsonResponse(false, Ledger::errorMessage($result));
