<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$result = Ledger::finalizeDraft(
    (int)($_POST['id'] ?? 0),
    $_POST['entry_date'] ?? date('Y-m-d')
);

if ($result === true) {
    jsonResponse(true, 'খসড়া মেমো মূল একাউন্টে যুক্ত হয়েছে।');
}
jsonResponse(false, Ledger::errorMessage($result));
