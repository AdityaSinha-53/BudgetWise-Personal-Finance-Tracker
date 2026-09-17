<?php
// ============================================================
//  change_password.php  -  Change Account Password
// ============================================================
//  Three-step validation:
//      1. New password is at least 6 characters
//      2. New password matches the confirmation
//      3. Current password is correct
//
//  The current-password check defends against an attacker who
//  has temporarily hijacked a session — they cannot lock the
//  legitimate user out without knowing the existing password.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle submission ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Check 1 — new password length
    if (strlen($new) < 6) {
        header('Location: change_password.php?status=pw_short');
        exit();
    }

    // Check 2 — confirmation
    if ($new !== $confirm) {
        header('Location: change_password.php?status=pw_mismatch');
        exit();
    }

    // Check 3 — current password correct
    $u = db_one($conn, "SELECT password FROM users WHERE id = ?", "i", [$uid]);
    if (!$u || !password_verify($current, $u['password'])) {
        header('Location: change_password.php?status=pw_wrong');
        exit();
    }

    // All good — store the new hash
    $hash = password_hash($new, PASSWORD_DEFAULT);
    db_run($conn, "UPDATE users SET password = ? WHERE id = ?",
           "si", [$hash, $uid]);

    // Regenerate session ID so any previously-hijacked session
    // is invalidated alongside the password change.
    session_regenerate_id(true);

    header('Location: profile.php?status=pw_changed');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Profile';
$page    = 'profile.php';
$title   = 'Change Password';
require_once 'includes/header.php';
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Change Password</div>
        <form method="POST">

            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password"
                       required autocomplete="current-password" autofocus>
            </div>

            <div class="form-group">
                <label>New Password * (minimum 6 characters)</label>
                <input type="password" name="new_password"
                       required minlength="6" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password"
                       required minlength="6" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-warning btn-full">Change Password</button>
                <a href="profile.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Password Tips</div>
        <ul class="tip-list">
            <li>
                <div class="tip-head">Use at least 8 characters</div>
                <div class="tip-body">
                    The system minimum is 6 but longer passwords are
                    exponentially harder to guess.
                </div>
            </li>
            <li>
                <div class="tip-head">Mix character types</div>
                <div class="tip-body">
                    Combine uppercase, lowercase, digits, and a symbol
                    or two. Real-world strength comes from variety.
                </div>
            </li>
            <li>
                <div class="tip-head">Don't reuse passwords</div>
                <div class="tip-body">
                    Use a password unique to BudgetWise, so a breach
                    elsewhere cannot affect this account.
                </div>
            </li>
            <li>
                <div class="tip-head">Consider a password manager</div>
                <div class="tip-body">
                    Tools like Bitwarden or 1Password remove the burden
                    of remembering complex passwords.
                </div>
            </li>
        </ul>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
