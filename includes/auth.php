<?php
// ============================================================
//  auth.php  -  Role, Permission, and Audit Helpers
// ============================================================
//  Loaded automatically by includes/db.php at the end. Every
//  page therefore has these helpers available without needing
//  a separate require_once.
//
//  These helpers assume session is already started and (for the
//  ones that need it) the user is logged in.
// ============================================================


// ─── current_user_id ───────────────────────────────────────
// Returns the logged-in user's ID as an int.
// Returns 0 if no one is logged in (shouldn't happen on
// auth-guarded pages, but is safe to call defensively).
function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}


// ─── current_user_role ─────────────────────────────────────
// Returns 'user' or 'admin'. Defaults to 'user' if unset.
function current_user_role(): string
{
    return $_SESSION['role'] ?? 'user';
}


// ─── is_admin ──────────────────────────────────────────────
// Convenience bool. Used in header.php to show/hide the
// Admin section of the sidebar.
function is_admin(): bool
{
    return current_user_role() === 'admin';
}


// ─── current_user ──────────────────────────────────────────
// Returns the full users row for the logged-in user.
// Used when we need email, role, or created_at in addition
// to the ID already in session.
function current_user($conn): ?array
{
    $uid = current_user_id();
    if ($uid === 0) return null;
    return db_one($conn,
        "SELECT id, username, email, role, is_active, created_at
         FROM users WHERE id = ?",
        "i", [$uid]
    );
}


// ─── current_profile ───────────────────────────────────────
// Returns the user_profiles row for the logged-in user.
// Falls back to a minimal default if the profile is missing
// (shouldn't happen since registration creates it, but the
// fallback prevents header.php from breaking).
function current_profile($conn): array
{
    $uid = current_user_id();
    if ($uid === 0) {
        return ['user_id' => 0, 'name' => 'Guest', 'phone' => '',
                'profile_image' => null, 'monthly_goal' => 0];
    }
    $row = db_one($conn,
        "SELECT user_id, name, phone, profile_image, monthly_goal
         FROM user_profiles WHERE user_id = ?",
        "i", [$uid]
    );
    return $row ?? ['user_id' => $uid, 'name' => 'User', 'phone' => '',
                    'profile_image' => null, 'monthly_goal' => 0];
}


// ─── require_admin ─────────────────────────────────────────
// Call at the very top of any admin-only page. If the
// logged-in user is not an admin, redirect them away with
// an "unauthorized" status banner.
//
// USAGE:
//   require_once 'includes/db.php';
//   require_admin();
//   // ... rest of admin page ...
function require_admin(): void
{
    if (!is_admin()) {
        header('Location: dashboard.php?status=unauthorized');
        exit();
    }
}


// ─── user_owns ─────────────────────────────────────────────
// Verifies that a given record belongs to the logged-in user.
// Used BEFORE edit and delete operations to prevent IDOR
// (Insecure Direct Object Reference) attacks where a user
// changes ?id=N in the URL to access someone else's data.
//
// Admins bypass this check (they can manage any record),
// which matches typical admin behaviour.
//
// USAGE:
//   $id = (int) $_GET['id'];
//   if (!user_owns($conn, 'transactions', $id)) {
//       header('Location: transactions.php?status=unauthorized');
//       exit();
//   }
//
// Returns true if:
//   - The current user is admin, OR
//   - The record's user_id matches the current user's ID.
// Returns false if the record doesn't exist or belongs to
// someone else.
function user_owns($conn, string $table, int $id): bool
{
    // Whitelist allowed table names — never interpolate user
    // input into SQL identifier positions even if "we know"
    // what value will come through.
    $allowed = [
        'transactions', 'budgets', 'savings_goals',
        'categories', 'payment_methods', 'attachments',
        'notifications',
    ];
    if (!in_array($table, $allowed, true)) {
        error_log("user_owns: disallowed table '$table'");
        return false;
    }

    if (is_admin()) return true;

    $row = db_one($conn,
        "SELECT user_id FROM `$table` WHERE id = ?",
        "i", [$id]
    );

    return $row && (int)$row['user_id'] === current_user_id();
}


// ─── log_admin_action ──────────────────────────────────────
// Records an action in the admin_logs table. Should be called
// only from admin pages, AFTER the action has actually been
// performed (so we never log failed actions as completed).
//
// USAGE:
//   log_admin_action($conn, 'user_deactivated', $target_id,
//                    'Disabled user via manage_users');
function log_admin_action($conn, string $action, ?int $target_user_id = null, ?string $description = null): bool
{
    if (!is_admin()) return false;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    return db_run($conn, "
        INSERT INTO admin_logs (admin_id, action, target_user_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ", "isiss", [current_user_id(), $action, $target_user_id, $description, $ip]);
}

// ============================================================
//  NO CLOSING PHP TAG
// ============================================================
