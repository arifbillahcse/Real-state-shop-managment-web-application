<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
header('Content-Type: text/plain; charset=utf-8');

// ── Helpers ──────────────────────────────────────────────────────────────────
function tableExists(string $table): bool {
    $row = Database::fetchOne(
        'SELECT TABLE_TYPE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
    return (bool)$row;
}

function tableType(string $table): ?string {
    $row = Database::fetchOne(
        'SELECT TABLE_TYPE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
    return $row['TABLE_TYPE'] ?? null;
}

function columnExists(string $table, string $column): bool {
    $row = Database::fetchOne(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$table, $column]
    );
    return (bool)$row;
}

$issues = [];
$ok     = 0;

function check(string $label, bool $pass, array &$issues, int &$ok): void {
    if ($pass) {
        $ok++;
        echo "  [OK]      $label\n";
    } else {
        $issues[] = $label;
        echo "  [MISSING] $label\n";
    }
}

echo "======================================================================\n";
echo " SCHEMA HEALTH CHECK — v3.0.0 (migrations v11 through v18)\n";
echo " Generated: " . date('Y-m-d H:i:s') . "\n";
echo "======================================================================\n\n";

// ── v11: Product model ───────────────────────────────────────────────────────
echo "-- v11: Product model --\n";
check('table product_subcategories', tableExists('product_subcategories'), $issues, $ok);
check('table branch_products',       tableExists('branch_products'),       $issues, $ok);
check('products.subcategory_id',     columnExists('products', 'subcategory_id'),  $issues, $ok);
check('products.product_code',       columnExists('products', 'product_code'),   $issues, $ok);
check('products.image',              columnExists('products', 'image'),          $issues, $ok);
check('products.wholesale_price',    columnExists('products', 'wholesale_price'),$issues, $ok);
echo "\n";

// ── v12: Customer profile ────────────────────────────────────────────────────
echo "-- v12: Customer profile --\n";
check('customers.whatsapp',     columnExists('customers', 'whatsapp'),     $issues, $ok);
check('customers.imo',          columnExists('customers', 'imo'),          $issues, $ok);
check('customers.photo',        columnExists('customers', 'photo'),        $issues, $ok);
check('customers.book_no',      columnExists('customers', 'book_no'),      $issues, $ok);
check('customers.account_no',   columnExists('customers', 'account_no'),   $issues, $ok);
check('customers.account_type', columnExists('customers', 'account_type'), $issues, $ok);
check('customers.due_limit',    columnExists('customers', 'due_limit'),    $issues, $ok);
check('table customer_phones',     tableExists('customer_phones'),     $issues, $ok);
check('table customer_references', tableExists('customer_references'), $issues, $ok);
echo "\n";

// ── v13: Customer ledger / agreements / notifications ───────────────────────
echo "-- v13: Customer ledger + agreements + notifications --\n";
check('table customer_ledger',           tableExists('customer_ledger'),           $issues, $ok);
check('table customer_ledger_items',     tableExists('customer_ledger_items'),     $issues, $ok);
check('table purchase_agreements',       tableExists('purchase_agreements'),       $issues, $ok);
check('table purchase_agreement_items',  tableExists('purchase_agreement_items'),  $issues, $ok);
check('table agreement_deliveries',      tableExists('agreement_deliveries'),      $issues, $ok);
check('table notification_log',          tableExists('notification_log'),          $issues, $ok);
echo "\n";

// ── v14: Sales upgrade ───────────────────────────────────────────────────────
echo "-- v14: Sales upgrade --\n";
check('sales.unload_bill',      columnExists('sales', 'unload_bill'),      $issues, $ok);
check('sales.labor_bill',       columnExists('sales', 'labor_bill'),       $issues, $ok);
check('sales.transport_bill',   columnExists('sales', 'transport_bill'),   $issues, $ok);
check('sales.delivery_charge',  columnExists('sales', 'delivery_charge'),  $issues, $ok);
check('sales.discount_note',    columnExists('sales', 'discount_note'),    $issues, $ok);
check('sales.approved_by',      columnExists('sales', 'approved_by'),      $issues, $ok);
check('sale_items.rate_type',      columnExists('sale_items', 'rate_type'),      $issues, $ok);
check('sale_items.unload_bill',    columnExists('sale_items', 'unload_bill'),    $issues, $ok);
check('sale_items.labor_bill',     columnExists('sale_items', 'labor_bill'),     $issues, $ok);
check('sale_items.transport_bill', columnExists('sale_items', 'transport_bill'), $issues, $ok);
echo "\n";

// ── v15: Transfer workflow ───────────────────────────────────────────────────
echo "-- v15: Transfer workflow --\n";
check('stock_transfers.status',           columnExists('stock_transfers', 'status'),           $issues, $ok);
check('stock_transfers.transfer_date',    columnExists('stock_transfers', 'transfer_date'),    $issues, $ok);
check('stock_transfers.customer_name',    columnExists('stock_transfers', 'customer_name'),    $issues, $ok);
check('stock_transfers.customer_address', columnExists('stock_transfers', 'customer_address'), $issues, $ok);
check('stock_transfers.customer_mobile',  columnExists('stock_transfers', 'customer_mobile'),  $issues, $ok);
check('stock_transfers.order_manager',    columnExists('stock_transfers', 'order_manager'),    $issues, $ok);
check('stock_transfers.driver_name',      columnExists('stock_transfers', 'driver_name'),      $issues, $ok);
check('stock_transfers.driver_mobile',    columnExists('stock_transfers', 'driver_mobile'),    $issues, $ok);
check('stock_transfers.return_note',      columnExists('stock_transfers', 'return_note'),      $issues, $ok);
check('stock_transfers.received_by',      columnExists('stock_transfers', 'received_by'),      $issues, $ok);
check('stock_transfers.received_at',      columnExists('stock_transfers', 'received_at'),      $issues, $ok);
echo "\n";

// ── v16: Staff accountability ────────────────────────────────────────────────
echo "-- v16: Staff accountability + daily statement --\n";
check('customer_ledger.collected_by', columnExists('customer_ledger', 'collected_by'), $issues, $ok);
check('table due_assignments', tableExists('due_assignments'), $issues, $ok);
check('table staff_tasks',     tableExists('staff_tasks'),     $issues, $ok);
echo "\n";

// ── v17: Low stock alerts ────────────────────────────────────────────────────
echo "-- v17: Low stock alert center --\n";
check('table low_stock_history', tableExists('low_stock_history'), $issues, $ok);
echo "\n";

// ── v18: Assistant Manager ───────────────────────────────────────────────────
echo "-- v18: Assistant Manager role --\n";
$roleCol = Database::fetchOne(
    "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
);
$roleType = (string)($roleCol['t'] ?? '');
check("users.role accepts 'assistant_manager'  [now: " . ($roleType ?: 'column missing') . "]",
      str_contains($roleType, 'assistant_manager'), $issues, $ok);
check('quotations.branch_id',        columnExists('quotations', 'branch_id'),        $issues, $ok);
check('installment_plans.branch_id', columnExists('installment_plans', 'branch_id'), $issues, $ok);
echo "\n";

// ── Deployed code — is the PHP on this server actually the new build? ────────
// Uploading the SQL but not the PHP/JS (or vice versa) looks exactly like
// "the upgrade did nothing", so check both halves, not just the database.
echo "-- Deployed code version --\n";
foreach ([
    'includes/init.php'  => 'isAssistantManager',
    'includes/init.php#' => 'resolveBranchId',
    'api/_guard.php'     => 'requireVisibleCustomer',
    'classes/User.php'   => 'BRANCH_ROLES',
] as $target => $needle) {
    $file = __DIR__ . '/../' . explode('#', $target)[0];
    $has  = is_readable($file) && str_contains((string)file_get_contents($file), $needle);
    check(basename($file) . " contains $needle()", $has, $issues, $ok);
}
$stale = [];
foreach (['assets/js/transfers.js', 'assets/js/customer_account.js', 'assets/js/products.js',
          'assets/js/customers.js', 'pages/staff_panel.php', 'pages/alert_center.php',
          'pages/daily_statement.php'] as $rel) {
    $f = __DIR__ . '/../' . $rel;
    $src = is_readable($f) ? (string)file_get_contents($f) : '';
    // The old broken pattern. daily_statement.php mentions it only in a comment.
    if (preg_match('/data\.data\.[a-zA-Z_]+/', $src) && !str_contains($rel, 'daily_statement')) {
        $stale[] = $rel;
    }
}
check('front-end files are the fixed build (no data.data.* reads)'
      . ($stale ? ' — stale: ' . implode(', ', $stale) : ''),
      empty($stale), $issues, $ok);
echo "\n";

// ── Views — must be VIEW, not a stray BASE TABLE ─────────────────────────────
echo "-- Views (must be TYPE=VIEW, not a stray table) --\n";
foreach (['vw_current_stock', 'vw_branch_stock', 'vw_customer_dues', 'vw_customer_ledger_balance'] as $v) {
    $type = tableType($v);
    if ($type === 'VIEW') {
        $ok++;
        echo "  [OK]      $v (VIEW)\n";
    } elseif ($type === 'BASE TABLE') {
        $issues[] = "$v exists but is a TABLE, not a VIEW (stray table — needs fixing)";
        echo "  [WRONG]   $v exists but is a TABLE, not a VIEW\n";
    } else {
        $issues[] = "$v does not exist";
        echo "  [MISSING] $v\n";
    }
}
echo "\n";

// ── Live query smoke tests — catch broken JOINs/SQL, not just missing objects
echo "-- Smoke tests (actual queries the app runs) --\n";
function smoke(string $label, callable $fn, array &$issues, int &$ok): void {
    try {
        $fn();
        $ok++;
        echo "  [OK]      $label\n";
    } catch (Throwable $e) {
        $issues[] = "$label — " . $e->getMessage();
        echo "  [ERROR]   $label\n            " . $e->getMessage() . "\n";
    }
}
smoke('SELECT FROM vw_current_stock', fn() => Database::fetchAll('SELECT * FROM vw_current_stock LIMIT 1'), $issues, $ok);
smoke('SELECT FROM vw_branch_stock',  fn() => Database::fetchAll('SELECT * FROM vw_branch_stock LIMIT 1'),  $issues, $ok);
smoke('Product::getProducts()', function () {
    require_once __DIR__ . '/../classes/Product.php';
    Product::getProducts();
}, $issues, $ok);
smoke('DailyStatement::build(today)', function () {
    require_once __DIR__ . '/../classes/DailyStatement.php';
    DailyStatement::build(date('Y-m-d'));
}, $issues, $ok);
smoke('Customer ledger balance view join', fn() => Database::fetchAll(
    'SELECT c.id, COALESCE(d.total_due,0) FROM customers c
     LEFT JOIN vw_customer_dues d ON d.customer_id = c.id LIMIT 1'
), $issues, $ok);
echo "\n";

// ── Summary ───────────────────────────────────────────────────────────────────
echo "======================================================================\n";
echo " SUMMARY: $ok OK, " . count($issues) . " issue(s)\n";
echo "======================================================================\n";
if ($issues) {
    echo "\nISSUES FOUND:\n";
    foreach ($issues as $i => $msg) {
        echo ($i + 1) . ". $msg\n";
    }
    echo "\n>>> Run sql/upgrade_to_v3.sql on this database to fix missing\n";
    echo ">>> tables/columns/views, then reload this page to confirm.\n";
} else {
    echo "\nEverything looks good — schema is fully up to date.\n";
}
