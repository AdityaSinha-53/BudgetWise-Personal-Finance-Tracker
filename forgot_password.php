<?php
// ============================================================
//  forgot_password.php  -  Password Recovery
// ============================================================
//  Two-step recovery flow without email integration:
//
//      STEP 1: User enters username + email.
//              If both match a single account, we mark the
//              session as "reset_verified" for that user.
//
//      STEP 2: User enters new password + confirm.
//              We update the password and clear the verify
//              flag, then redirect to login.
//
//  This avoids needing SMTP configuration on XAMPP while
//  still providing a real recovery mechanism that proves
//  the requester knows two facts about the account.
//
//  Error messages are deliberately generic so the page does
//  not leak which usernames or emails exist in the system.
// ============================================================

$skip_auth = true;
require_once 'includes/db.php';

// Already logged in? Just send them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$step  = isset($_SESSION['reset_verified_user']) ? 2 : 1;


// ─── STEP 1 handler ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');

    if ($username && $email) {
        $row = db_one($conn, "
            SELECT id FROM users
            WHERE username = ? AND email = ? AND is_active = 1
            LIMIT 1
        ", "ss", [$username, $email]);

        if ($row) {
            // Match — record the verified user in session and
            // advance to step 2. Regenerate session ID so the
            // verification cannot be carried over from an
            // earlier compromised session.
            session_regenerate_id(true);
            $_SESSION['reset_verified_user'] = (int)$row['id'];
            header('Location: forgot_password.php');
            exit();
        } else {
            // Deliberately generic. Do not say which field was wrong.
            $error = 'No active account matches those details.';
        }
    } else {
        $error = 'Please enter both your username and email.';
    }
}


// ─── STEP 2 handler ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {

    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $uid     = (int) $_SESSION['reset_verified_user'];

    if (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($uid <= 0) {
        // Session lost — restart the flow
        unset($_SESSION['reset_verified_user']);
        header('Location: forgot_password.php');
        exit();
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $ok   = db_run($conn,
            "UPDATE users SET password = ? WHERE id = ?",
            "si", [$hash, $uid]
        );

        // Clear the verify flag regardless of success so a
        // failed update doesn't leave the session armed.
        unset($_SESSION['reset_verified_user']);

        if ($ok) {
            header('Location: login.php?status=reset_done');
            exit();
        } else {
            $error = 'Could not update password. Please try again.';
        }
    }
}


// Allow the user to start over (cancel step 2)
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_verified_user']);
    header('Location: forgot_password.php');
    exit();
}

$theme = get_theme();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &mdash; BudgetWise</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">

<div class="login-wrap">

    <!-- Logo -->
    <div class="login-logo">
        <span class="login-logo-icon">&#9672;</span>
        <div>
            <div class="login-logo-name">BudgetWise</div>
            <div class="login-logo-sub">Finance Tracker</div>
        </div>
    </div>

    <!-- Recovery card -->
    <div class="login-card">

    <?php if ($step === 1): ?>
        <!-- ─── STEP 1: identify the account ─── -->
        <h2 class="login-title">Forgot Password</h2>
        <p class="login-sub">Enter your username and email to verify your identity</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:18px">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required autocomplete="email">
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
                Verify &rarr;
            </button>
        </form>

    <?php else: ?>
        <!-- ─── STEP 2: set new password ─── -->
        <h2 class="login-title">Set New Password</h2>
        <p class="login-sub">Identity verified. Choose a new password for your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:18px">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">

            <div class="form-group">
                <label for="new_password">New Password * (minimum 6 characters)</label>
                <input type="password" id="new_password" name="new_password"
                       required minlength="6" autofocus autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       required minlength="6" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
                Reset Password &rarr;
            </button>
        </form>

        <div style="text-align:center;margin-top:14px;font-size:.78rem">
            <a href="forgot_password.php?cancel=1" style="color:var(--muted)">Start over</a>
        </div>
    <?php endif; ?>

        <div class="login-hint">
            Remembered your password?
            <a href="login.php" style="color:var(--blue);font-weight:600">Sign in instead</a>
        </div>
    </div>

    <!-- Theme toggle -->
    <div style="text-align:center;margin-top:16px">
        <button type="button" onclick="toggleTheme()" class="btn btn-sm btn-ghost">
            <span id="theme-icon">
                <?= $theme === 'dark' ? '&#9728; Light Mode' : '&#9790; Dark Mode' ?>
            </span>
        </button>
    </div>

</div>

<script>
function toggleTheme() {
    var html = document.documentElement;
    var cur  = html.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.cookie = 'theme=' + next + ';path=/;max-age=31536000;samesite=lax';
    document.getElementById('theme-icon').textContent =
        next === 'dark' ? '\u2600 Light Mode' : '\u263e Dark Mode';
}
</script>
</body>
</html>
