<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Transfer.php';

requireMethod('POST');
requireBranchWriteApi();

// Branch-locked users can only send FROM their own branch.
$data = $_POST;
$locked = lockedBranchId();
if ($locked !== null) {
    $data['from_branch_id'] = $locked;
    if ((int)($data['to_branch_id'] ?? 0) === $locked) {
        jsonResponse(false, 'একই ব্রাঞ্চে ট্রান্সফার করা যায় না।');
    }
}

$result = Transfer::addEntry($data, getUserId());

if (is_int($result)) {
    jsonResponse(true, 'ট্রান্সফার শিটে যুক্ত হয়েছে।', ['id' => $result]);
}
jsonResponse(false, Transfer::errorMessage($result));
