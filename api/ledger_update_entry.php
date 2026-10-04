<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Ledger.php';

requireMethod('POST');
requireBranchWriteApi();

$id         = (int)($_POST['id'] ?? 0);
$customerId = (int)($_POST['customer_id'] ?? 0);
requireVisibleCustomer($customerId);

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items)) $items = [];

$data = [
    'entry_date'     => $_POST['entry_date']     ?? '',
    'note'           => $_POST['note']           ?? '',
    'items'          => $items,
    'unload_bill'    => $_POST['unload_bill']    ?? 0,
    'labor_bill'     => $_POST['labor_bill']     ?? 0,
    'transport_bill' => $_POST['transport_bill'] ?? 0,
    'branch_id'      => $_POST['branch_id']      ?? '',
    'amount'         => $_POST['amount']         ?? 0,
    'method'         => $_POST['method']         ?? '',
    'reason'         => $_POST['reason']         ?? '',
    'received_by'    => $_POST['received_by']    ?? '',
    'description'    => $_POST['description']    ?? '',
];

$result = Ledger::updateEntry($id, $customerId, $data, getUserId());

if ($result === true) {
    jsonResponse(true, 'এন্ট্রি সংশোধন করা হয়েছে।');
}
jsonResponse(false, Ledger::errorMessage($result));
