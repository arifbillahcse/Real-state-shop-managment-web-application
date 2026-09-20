<?php

/**
 * Bootstrap file — include this at the top of every page.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Setting.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Regenerate session periodically to prevent fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Helper: redirect helper
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Helper: is user logged in?
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

// Helper: current user id (null when not logged in)
function getUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// Helper: require login (call at top of protected pages)
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/index.php');
    }
}

// Helper: require strict admin role (users page, settings page)
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        redirect(BASE_URL . '/pages/dashboard.php');
    }
}

// Helper: require admin OR manager role (all other privileged pages)
function requireManagerOrAdmin(): void
{
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true)) {
        redirect(BASE_URL . '/pages/dashboard.php');
    }
}

/**
 * Require admin, manager OR assistant manager — i.e. pages an assistant
 * manager is allowed to use (branch-scoped: sales, stock, transfers,
 * customers, dues, quotations, installments, expenses, reports…).
 *
 * Use requireManagerOrAdmin() instead for anything an assistant manager
 * must NOT reach (product catalogue, branches, users, settings, backup).
 */
function requireBranchStaffOrAbove(): void
{
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'assistant_manager'], true)) {
        redirect(BASE_URL . '/pages/dashboard.php');
    }
}

// Helper: is current user a staff member?
function isStaff(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'staff';
}

// Helper: is current user a manager?
function isManager(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'manager';
}

// Helper: is current user an assistant manager (tied to one branch)?
function isAssistantManager(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'assistant_manager';
}

// Helper: is current user admin OR manager (full, unrestricted privilege)?
// NOTE: deliberately excludes assistant_manager — they are branch-scoped and
// must not inherit blanket write access. Grant them access explicitly instead.
function isAdminOrManager(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true);
}

// Helper: may the current user write branch-level data (sales, stock, …)?
function canWriteBranchData(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'assistant_manager'], true);
}

// Helper: get current user's assigned branch id (null for admin and manager)
function getSessionBranchId(): ?int
{
    $bid = $_SESSION['user_branch_id'] ?? null;
    return $bid !== null ? (int)$bid : null;
}

/**
 * The branch a user is LOCKED to, or null if they may work across all
 * branches. Staff and assistant managers are locked to their own branch;
 * admin and manager are not.
 *
 * This is the single source of truth for branch scoping — use it both to
 * filter reads and to override any client-supplied branch_id on writes,
 * so a locked user can't reach another branch's data by tampering with a
 * request.
 */
function lockedBranchId(): ?int
{
    if (isStaff() || isAssistantManager()) {
        return getSessionBranchId();
    }
    return null;
}

/**
 * Resolve the branch_id a write should use: a locked user always gets their
 * own branch regardless of what the request asked for; everyone else gets
 * the requested value.
 */
function resolveBranchId($requested): ?int
{
    $locked = lockedBranchId();
    if ($locked !== null) return $locked;
    return ($requested !== null && $requested !== '') ? (int)$requested : null;
}

// Helper: return JSON and exit (for API files)
/**
 * Emit an API response as JSON and stop.
 *
 * json_encode() returns false — printing NOTHING — when the payload holds
 * something it can't represent: text that isn't valid UTF-8 (a row saved
 * through a latin1 connection), or an INF/NAN from a division. The client
 * then gets an empty body, JSON.parse() throws, and the page shows a
 * generic "could not load" that says nothing about the real cause.
 *
 * So encode defensively: substitute bad UTF-8 and accept partial output
 * rather than silently emitting nothing, and if even that fails, send a
 * real error message naming the encoding problem.
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');

    $payload = array_merge(['success' => $success, 'message' => $message], $data);
    $json    = json_encode($payload);

    if ($json === false) {
        $reason = json_last_error_msg();
        error_log('jsonResponse encode failed: ' . $reason);
        $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'message' => 'ডেটা পাঠাতে সমস্যা হয়েছে (' . $reason . ')।',
            ]);
        }
    }

    echo $json;
    exit;
}

// Helper: sanitize output
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * URL for a static file under assets/, with a cache-busting ?v= query string
 * built from the file's own last-modified time.
 *
 * Every page loads its JS/CSS with a bare <script src="...">, so a browser
 * that already cached transfers.js keeps running the old one after a deploy
 * — a code fix can be live on the server and still invisible to a user who
 * pulled it, because nothing in the URL changed to tell the browser to
 * re-fetch it. Appending the file's mtime means the URL itself changes
 * whenever the file's content does, so every deploy busts the cache
 * automatically with no version number to remember to bump.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $full = ROOT_PATH . '/' . $path;
    $v    = is_file($full) ? filemtime($full) : time();
    return BASE_URL . '/' . $path . '?v=' . $v;
}

// Helper: format money
function money(float $amount): string
{
    return number_format($amount, 2) . ' ৳';
}

// Helper: today's date
function today(): string
{
    return date('Y-m-d');
}
