<?php
// ============================================================
//  db.php  -  Database Connection + Helpers + Auth Guard
// ============================================================
//  Every PHP page in this app starts with:
//      require_once 'includes/db.php';
//
//  This file does FIVE jobs:
//      1. Starts the PHP session (for login)
//      2. Connects to the MySQL database
//      3. Blocks any non-logged-in user from seeing pages
//      4. Provides small helper functions used everywhere
//      5. Loads includes/auth.php for role helpers
// ============================================================


// ============================================================
//  SECTION 1 - START SESSION
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,    // JS cannot read the cookie (XSS defence)
        'samesite' => 'Lax',   // Browser won't send cookie on cross-site requests
    ]);
    session_start();
}


// ============================================================
//  SECTION 2 - DATABASE CREDENTIALS
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'budget_app');

// DEBUG = true  --> show full error details (use on XAMPP/development)
// DEBUG = false --> hide details from users, write to PHP error log only
define('DEBUG', true);

// Upload directory for receipt attachments and profile images.
// Resolved relative to this file's location so it works
// regardless of which page included db.php.
define('UPLOAD_BASE', __DIR__ . '/../uploads/');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    if (DEBUG) {
        $safe_err = htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8');
        die('
        <div style="font-family:monospace;padding:24px;background:#fff8f8;
                    color:#c0392b;border:1px solid #e74c3c;border-radius:8px;
                    max-width:600px;margin:24px auto">
            <b>Database Connection Failed</b><br><br>'
            . $safe_err . '<br><br>
            <small>
                Checklist:<br>
                - Is XAMPP MySQL service running?<br>
                - Did you import database/setup.sql via phpMyAdmin?<br>
                - Are DB_USER and DB_PASS correct in includes/db.php?
            </small>
        </div>');
    } else {
        error_log('DB connection failed: ' . mysqli_connect_error());
        die('
        <div style="font-family:sans-serif;padding:24px;background:#fff8f8;
                    color:#c0392b;border:1px solid #e74c3c;border-radius:8px;
                    max-width:600px;margin:24px auto;text-align:center">
            <b>Service temporarily unavailable.</b><br>
            <small>Please try again shortly.</small>
        </div>');
    }
}

mysqli_set_charset($conn, 'utf8mb4');


// ============================================================
//  SECTION 3 - DATABASE HELPERS  (unchanged from previous build)
// ============================================================
//  Three thin wrappers around mysqli prepared statements.
//
//  Fetch many:   db_all($conn, $sql, $types, $params)
//  Fetch one:    db_one($conn, $sql, $types, $params)
//  Write:        db_run($conn, $sql, $types, $params)
//
//  Type letters:
//      "i" = integer    "s" = string    "d" = float/decimal
// ============================================================

function db_all($conn, $sql, $types = '', $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('db_all() prepare failed: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return [];
    }
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_one($conn, $sql, $types = '', $params = [])
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_run($conn, $sql, $types = '', $params = []): bool
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('db_run() prepare failed: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return false;
    }
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Returns the auto-increment ID of the last INSERT.
// Used after db_run on an INSERT to get the new row's id.
function db_last_id($conn): int
{
    return (int) mysqli_insert_id($conn);
}


// ============================================================
//  SECTION 4 - AUTH GUARD
// ============================================================
//  If the visitor is not logged in, redirect to login.php.
//  Also blocks accounts that have been deactivated by an admin.
//
//  EXCEPTION: pages that need to work without a login
//  (login.php, register.php, logout.php, forgot_password.php)
//  must set this BEFORE requiring this file:
//      $skip_auth = true;
//      require_once 'includes/db.php';
// ============================================================

if (!isset($skip_auth)) {

    if (!isset($_SESSION['user_id'])) {
        // Remember where the user wanted to go
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $req  = $_SERVER['REQUEST_URI'] ?? '';
            $file = basename(parse_url($req, PHP_URL_PATH) ?? '');
            $qs   = parse_url($req, PHP_URL_QUERY);
            $skip_pages = ['login.php', 'logout.php', 'register.php', 'forgot_password.php'];

            if (
                preg_match('/^[a-z_]+\.php$/i', $file) &&
                !in_array($file, $skip_pages, true)
            ) {
                $_SESSION['login_redirect'] = $file . ($qs ? '?' . $qs : '');
            }
        }
        header('Location: login.php');
        exit();
    }

    // Verify the account is still active (admin may have disabled it)
    $_active = db_one($conn,
        "SELECT is_active FROM users WHERE id = ?",
        "i", [(int)$_SESSION['user_id']]
    );
    if (!$_active || (int)$_active['is_active'] !== 1) {
        // Account disabled — log them out
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?status=account_disabled');
        exit();
    }
}


// ============================================================
//  SECTION 5 - UTILITY FUNCTIONS
// ============================================================

// ─── Indian rupee formatting ────────────────────────────────
function fmt($n): string
{
    $n    = (float) $n;
    $sign = $n < 0 ? '-' : '';
    $n    = abs($n);

    [$int, $dec] = explode('.', number_format($n, 2, '.', ''));

    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest  = substr($int, 0, -3);
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int   = $rest . ',' . $last3;
    }

    return "\xe2\x82\xb9" . $sign . $int . '.' . $dec;
}

// ─── Read theme from cookie (default dark) ──────────────────
function get_theme(): string
{
    return (($_COOKIE['theme'] ?? 'dark') === 'light') ? 'light' : 'dark';
}

// ─── CSV formula-injection guard ────────────────────────────
function csv_safe($val): string
{
    $val = (string) $val;
    if ($val !== '' && in_array($val[0], ['=', '+', '-', '@'], true)) {
        return "'" . $val;
    }
    return $val;
}

// ─── Get categories for the current user ────────────────────
// Returns:
//   [
//     'income'  => [['id'=>1, 'name'=>'Salary'], ...],
//     'expense' => [['id'=>7, 'name'=>'Food'],   ...],
//   ]
//
// Returns BOTH system defaults (user_id IS NULL) AND the user's
// own custom categories. Defaults to the logged-in user.
function get_categories($conn, $user_id = null): array
{
    if ($user_id === null) $user_id = current_user_id();

    $rows = db_all($conn, "
        SELECT id, name, type
        FROM categories
        WHERE is_default = 1 OR user_id = ?
        ORDER BY is_default DESC, name
    ", "i", [$user_id]);

    $cats = ['income' => [], 'expense' => []];
    foreach ($rows as $r) {
        $cats[$r['type']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    }
    return $cats;
}

// ─── Get payment methods for the current user ───────────────
function get_payment_methods($conn, $user_id = null): array
{
    if ($user_id === null) $user_id = current_user_id();
    return db_all($conn, "
        SELECT id, name, type
        FROM payment_methods
        WHERE is_default = 1 OR user_id = ?
        ORDER BY is_default DESC, name
    ", "i", [$user_id]);
}

// ─── Insert a notification for a user ───────────────────────
function notify($conn, $user_id, $type, $title, $message): bool
{
    $valid_types = ['budget_warning','budget_exceeded','goal_milestone','goal_achieved','general'];
    if (!in_array($type, $valid_types, true)) $type = 'general';

    return db_run($conn, "
        INSERT INTO notifications (user_id, type, title, message)
        VALUES (?, ?, ?, ?)
    ", "isss", [(int)$user_id, $type, $title, $message]);
}

// ─── Count unread notifications (used by sidebar badge) ─────
function unread_notification_count($conn, $user_id = null): int
{
    if ($user_id === null) $user_id = current_user_id();
    $r = db_one($conn,
        "SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0",
        "i", [$user_id]
    );
    return (int)($r['c'] ?? 0);
}

// ─── Log a report generation event ──────────────────────────
function log_report($conn, $report_type, $period_from = null, $period_to = null): bool
{
    $valid = ['monthly','category','date_range','export_csv','export_pdf','export_excel'];
    if (!in_array($report_type, $valid, true)) return false;

    return db_run($conn, "
        INSERT INTO report_logs (user_id, report_type, period_from, period_to)
        VALUES (?, ?, ?, ?)
    ", "isss", [current_user_id(), $report_type, $period_from, $period_to]);
}


// ============================================================
//  SECTION 6 - STATUS MESSAGE MAP
// ============================================================
//  After a form submission, the handler redirects with a
//  status code in the URL. header.php reads $_GET['status']
//  and shows a banner.
//
//  Letter codes:  's' success  'i' info  'e' error
// ============================================================

$status_msgs = [
    // Transaction status
    'added'             => ['s', 'Transaction added successfully.'],
    'updated'           => ['s', 'Transaction updated.'],
    'deleted'           => ['i', 'Transaction deleted.'],

    // Budget status
    'budget_saved'      => ['s', 'Budget saved.'],
    'budget_deleted'    => ['i', 'Budget removed.'],

    // Goal status
    'goal_added'        => ['s', 'Savings goal created.'],
    'goal_updated'      => ['s', 'Savings goal updated.'],
    'goal_deleted'      => ['i', 'Savings goal removed.'],
    'contribution_added'=> ['s', 'Contribution recorded.'],

    // Profile status
    'profile_saved'     => ['s', 'Profile updated.'],
    'settings_saved'    => ['s', 'Settings updated.'],
    'pw_changed'        => ['s', 'Password changed successfully.'],
    'pw_mismatch'       => ['e', 'New passwords do not match.'],
    'pw_wrong'          => ['e', 'Current password is incorrect.'],
    'pw_short'          => ['e', 'New password must be at least 6 characters.'],

    // Category status
    'cat_added'         => ['s', 'Category added.'],
    'cat_deleted'       => ['i', 'Category deleted.'],

    // Payment method status
    'pm_added'          => ['s', 'Payment method added.'],
    'pm_deleted'        => ['i', 'Payment method deleted.'],

    // Account status
    'account_created'   => ['s', 'Account created. Please log in.'],
    'account_disabled'  => ['e', 'Your account has been deactivated. Contact admin.'],
    'username_taken'    => ['e', 'That username is already taken.'],
    'email_taken'       => ['e', 'That email is already registered.'],
    'reset_sent'        => ['i', 'If the account exists, a reset link has been generated.'],
    'reset_done'        => ['s', 'Password reset. You can now log in.'],

    // Admin status
    'user_activated'    => ['s', 'User account activated.'],
    'user_deactivated'  => ['i', 'User account deactivated.'],

    // Notification status
    'notif_marked'      => ['i', 'Notifications marked as read.'],
    'notif_deleted'     => ['i', 'Notification deleted.'],

    // Generic
    'validation'        => ['e', 'Please fill all required fields correctly.'],
    'error'             => ['e', 'Something went wrong. Please try again.'],
    'unauthorized'      => ['e', 'You are not authorised to perform that action.'],
    'not_found'         => ['e', 'The requested record was not found.'],
];


// ============================================================
//  SECTION 7 - LOAD AUTH HELPERS
// ============================================================
//  auth.php defines:
//      current_user_id()   — int from session
//      current_user_role() — 'user' or 'admin'
//      current_user($conn) — full user row
//      is_admin()          — bool
//      require_admin($conn) — redirects non-admins
//      user_owns($conn, $table, $id) — IDOR defence
//      log_admin_action($conn, $action, $target, $desc)
// ============================================================

require_once __DIR__ . '/auth.php';


// ============================================================
//  NO CLOSING PHP TAG - This is intentional.
//  Prevents accidental whitespace from breaking header() calls.
// ============================================================
