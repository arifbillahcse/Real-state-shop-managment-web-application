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

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$result = Transfer::addEntryBatch($data, $items, getUserId());

if (is_array($result)) {
    $n = count($result);
    jsonResponse(true, $n > 1 ? "{$n}টি পণ্য ট্রান্সফার শিটে যুক্ত হয়েছে।" : 'ট্রান্সফার শিটে যুক্ত হয়েছে।',
                 ['ids' => $result]);
}
jsonResponse(false, Transfer::errorMessage($result));
