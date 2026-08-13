<?php

/**
 * Common guard for all API endpoints.
 * - boots the app
 * - requires login
 * - requires POST for write actions (caller may relax for GET reads)
 *
 * API responses must always be pure JSON. On some hosts a stray PHP
 * warning/notice/deprecation (e.g. from a PHP-version difference) gets
 * printed to the output before jsonResponse() runs. That breaks
 * res.json() on the client, which then shows a generic "server error"
 * toast — even though the database change underneath already
 * succeeded. This is confusing: the action "failed" on screen but
 * actually worked.
 *
 * Three layers of defense so this class of bug can never reach the
 * client, regardless of the host's php.ini:
 *   1. Output buffering — nothing physically reaches the browser until
 *      we decide to flush it, so headers can always be set cleanly.
 *   2. A custom error handler that logs warnings/notices instead of
 *      letting PHP print them, no matter what display_errors is set to.
 *   3. A shutdown handler that, if a fatal error slips through, discards
 *      whatever partial output exists and replaces it with one clean
 *      JSON error instead of a broken response or a PHP error page.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("API warning [$errno] $errstr in $errfile:$errline");
    return true; // tell PHP not to run its own (possibly printing) handler
});

register_shutdown_function(function () {
    $error = error_get_last();
    $fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);

    if ($fatal) {
        error_log('API fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'সার্ভারে একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।']);
        return;
    }

    // Normal completion — release the buffered response body to the client.
    while (ob_get_level() > 0) { ob_end_flush(); }
});

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(false, 'অনুমতি নেই। আবার লগইন করুন।');
}

/**
 * Ensure the request method matches. Otherwise return error JSON.
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonResponse(false, 'ভুল রিকোয়েস্ট মেথড।');
    }
}

/**
 * Ensure current user is admin or manager (most privileged endpoints).
 * Deliberately excludes assistant_manager — see requireBranchWriteApi().
 */
function requireAdminApi(): void
{
    if (!isAdminOrManager()) {
        jsonResponse(false, 'এই কাজের অনুমতি নেই।');
    }
}

/**
 * Ensure current user may write branch-level data (admin, manager or
 * assistant manager). Endpoints using this MUST also scope the write to
 * resolveBranchId(), so an assistant manager can only ever touch their
 * own branch.
 */
function requireBranchWriteApi(): void
{
    if (!canWriteBranchData()) {
        jsonResponse(false, 'এই কাজের অনুমতি নেই।');
    }
}

/**
 * For edit/delete of an EXISTING row: make sure a branch-locked user owns it.
 *
 * resolveBranchId() alone only fixes the branch a write is stamped with — it
 * can't stop someone passing another branch's record id. This closes that
 * hole by checking the stored row's branch before the write proceeds.
 *
 * @param string $table         table holding the record
 * @param int    $id            record id
 * @param string $branchColumn  column holding its branch (default branch_id)
 */
function requireOwnBranchRecord(string $table, int $id, string $branchColumn = 'branch_id'): void
{
    $locked = lockedBranchId();
    if ($locked === null) return; // admin / manager — unrestricted

    // Only allow known table names through — never interpolate caller input.
    $allowed = [
        'sales', 'stock_inbound', 'stock_adjustments', 'stock_transfers',
        'expenses', 'quotations', 'installment_plans',
    ];
    if (!in_array($table, $allowed, true) || !preg_match('/^[a-z_]+$/', $branchColumn)) {
        jsonResponse(false, 'এই কাজের অনুমতি নেই।');
    }

    $row = Database::fetchOne(
        "SELECT `$branchColumn` AS bid FROM `$table` WHERE id = ? LIMIT 1",
        [$id]
    );
    if (!$row) {
        jsonResponse(false, 'রেকর্ডটি খুঁজে পাওয়া যায়নি।');
    }
    if ((int)$row['bid'] !== $locked) {
        jsonResponse(false, 'এটি আপনার ব্রাঞ্চের রেকর্ড নয়।');
    }
}

/**
 * Transfers have two branch sides, so ownership depends on the action:
 *   'from' — creating/editing/sending: user must own the sending branch
 *   'to'   — receiving (In/Return):    user must own the destination branch
 */
function requireOwnTransferSide(int $id, string $side): void
{
    $locked = lockedBranchId();
    if ($locked === null) return; // admin / manager — unrestricted

    $column = $side === 'to' ? 'to_branch_id' : 'from_branch_id';
    $row = Database::fetchOne(
        "SELECT `$column` AS bid FROM stock_transfers WHERE id = ? LIMIT 1",
        [$id]
    );
    if (!$row) {
        jsonResponse(false, 'ট্রান্সফার এন্ট্রি খুঁজে পাওয়া যায়নি।');
    }
    if ((int)$row['bid'] !== $locked) {
        jsonResponse(false, $side === 'to'
            ? 'এই ট্রান্সফারটি আপনার ব্রাঞ্চে আসেনি।'
            : 'এটি আপনার ব্রাঞ্চের ট্রান্সফার নয়।');
    }
}

/**
 * For any customer-scoped endpoint: a branch-locked user must not reach a
 * customer that belongs solely to another branch. See
 * Customer::isVisibleToCurrentUser() for the exact rule.
 */
function requireVisibleCustomer(int $customerId): void
{
    if (lockedBranchId() === null) return; // admin / manager — unrestricted

    require_once __DIR__ . '/../classes/Customer.php';
    if (!Customer::isVisibleToCurrentUser($customerId)) {
        jsonResponse(false, 'এটি আপনার ব্রাঞ্চের কাস্টমার নয়।');
    }
}

/**
 * Ensure current user is strictly admin (user management, settings).
 */
function requireStrictAdminApi(): void
{
    if (!User::isAdmin()) {
        jsonResponse(false, 'এই কাজের অনুমতি শুধু অ্যাডমিনের আছে।');
    }
}
