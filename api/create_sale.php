<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../classes/Sale.php';

requireMethod('POST');

$customerId    = (isset($_POST['customer_id']) && $_POST['customer_id'] !== '')
                 ? (int)$_POST['customer_id'] : null;
// Branch-locked users (staff, assistant manager) always sell from their own
// branch — the submitted branch_id is ignored for them.
$branchId      = resolveBranchId($_POST['branch_id'] ?? null);
$itemsJson     = $_POST['items']          ?? '[]';
$discount      = (float)($_POST['discount']      ?? 0);
$paidAmount    = (float)($_POST['paid_amount']   ?? 0);
$paymentMethod = trim($_POST['payment_method']   ?? 'cash');
$saleDate      = trim($_POST['sale_date']         ?? '');
$note          = trim($_POST['note']              ?? '');
$discountNote  = trim($_POST['discount_note']     ?? '');

$charges = [
    'unload'    => (float)($_POST['unload_bill']     ?? 0),
    'labor'     => (float)($_POST['labor_bill']      ?? 0),
    'transport' => (float)($_POST['transport_bill']  ?? 0),
    'delivery'  => (float)($_POST['delivery_charge'] ?? 0),
];

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    jsonResponse(false, 'পণ্যের তালিকা সঠিক নয়।');
}

// Over-limit credit sales: a manager can approve inline with their credentials.
$approvedBy = null;
if (!empty($_POST['approver_username']) && !empty($_POST['approver_password'])) {
    $approver = Database::fetchOne(
        'SELECT id, password, role FROM users
         WHERE username = ? AND is_active = 1 LIMIT 1',
        [trim($_POST['approver_username'])]
    );
    if (!$approver
        || !password_verify($_POST['approver_password'], $approver['password'])
        || !in_array($approver['role'], ['admin', 'manager'], true)) {
        jsonResponse(false, 'ম্যানেজার অনুমোদন ব্যর্থ — ভুল ইউজারনেম/পাসওয়ার্ড বা অনুমতি নেই।');
    }
    $approvedBy = (int)$approver['id'];
}

// Who actually served the customer — printed on the invoice.
$soldBy = [
    'name'   => $_POST['sold_by_name']   ?? '',
    'mobile' => $_POST['sold_by_mobile'] ?? '',
];

// Previous balance to print on the memo. Taken from the ledger here rather
// than trusted from the browser, and deliberately NOT added to the sale's own
// total — the old invoices already carry it.
$previousDue = null;
if (!empty($_POST['include_previous_due']) && $customerId) {
    $row = Database::fetchOne(
        'SELECT COALESCE(SUM(due_amount), 0) AS due
         FROM sales WHERE customer_id = ? AND status = "completed"',
        [$customerId]
    );
    $previousDue = (float)($row['due'] ?? 0);
}

$result = Sale::createSale(
    $customerId, $items, $discount, $paidAmount, $paymentMethod,
    $saleDate, $note, $branchId, $charges, $discountNote, $approvedBy, $soldBy,
    $previousDue
);

if (is_int($result)) {
    $sale = Sale::getSaleById($result);
    jsonResponse(true, 'বিক্রয় সফলভাবে সম্পন্ন হয়েছে।', [
        'sale_id'        => $result,
        'invoice_number' => $sale['invoice_number'] ?? '',
    ]);
}

if (str_starts_with($result, 'LIMIT_EXCEEDED:')) {
    $parts = explode(':', $result);
    jsonResponse(false, Sale::errorMessage($result), [
        'code'        => 'LIMIT_EXCEEDED',
        'due_limit'   => (float)($parts[1] ?? 0),
        'current_due' => (float)($parts[2] ?? 0),
    ]);
}

jsonResponse(false, Sale::errorMessage($result));
