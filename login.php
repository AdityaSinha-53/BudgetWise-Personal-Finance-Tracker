<?php
// ============================================================
//  login.php  -  User Login Page
// ============================================================
//  $skip_auth tells db.php NOT to redirect here (infinite loop)
$skip_auth = true;
require_once 'includes/db.php';

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if ($username && $password) {

        // Prepared statement — immune to SQL injection.
        // Now also fetches role and is_active so we can populate
        // the session correctly and block disabled accounts.
        $user = db_one($conn,
            'SELECT id, username, password, role, is_active
             FROM users WHERE username = ? LIMIT 1',
            's', [$username]
        );

        if ($user && password_verify($password, $user['password'])) {

            // Block deactivated accounts. We still verify the
            // password first to avoid leaking which usernames
            // exist via timing.
            if ((int)$user['is_active'] !== 1) {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } else {
                // Regenerate session ID immediately after successful
                // login to prevent session fixation attacks.
                session_regenerate_id(true);

                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                // Send user back to the page they were trying to reach,
                // or default to dashboard. Re-validate path defensively.
                $dest = $_SESSION['login_redirect'] ?? 'dashboard.php';
                unset($_SESSION['login_redirect']);
                if (!preg_match('#^[a-z_/]+\.php(\?[^<>"\']*)?$#i', $dest)) {
                    $dest = 'dashboard.php';
                }

                header('Location: ' . $dest);
                exit();
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

// Translate status redirect codes into a success/info banner
// shown ABOVE the form. Errors come through $error directly.
$status_banner = '';
if (isset($_GET['status'])) {
    $code = $_GET['status'];
    if ($code === 'account_created') {
        $status_banner = ['success', 'Account created successfully. Please sign in.'];
    } elseif ($code === 'account_disabled') {
        $status_banner = ['error', 'Your account has been deactivated. Please contact the administrator.'];
    } elseif ($code === 'reset_done') {
        $status_banner = ['success', 'Password reset successfully. You can now sign in.'];
    } elseif ($code === 'logged_out') {
        $status_banner = ['info', 'You have been signed out.'];
    }
}

$theme = get_theme();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; BudgetWise</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">

<div class="login-wrap">

    <!-- App logo -->
    <div class="login-logo">
        <span class="login-logo-icon">&#9672;</span>
        <div>
            <div class="login-logo-name">BudgetWise</div>
            <div class="login-logo-sub">Finance Tracker</div>
        </div>
    </div>

    <!-- Login form card -->
    <div class="login-card">
        <h2 class="login-title">Sign In</h2>
        <p class="login-sub">Enter your credentials to continue</p>

        <?php if ($status_banner): ?>
            <div class="alert alert-<?= $status_banner[0] ?>" style="margin-bottom:18px">
                <?= htmlspecialchars($status_banner[1], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:18px">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text"
                       id="username"
                       name="username"
                       placeholder="admin"
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required
                       autofocus
                       autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password"
                       id="password"
                       name="password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       required
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
                Sign In &rarr;
            </button>
        </form>

        <!-- Secondary actions -->
        <div style="text-align:center;margin-top:16px;font-size:.78rem">
            <a href="register.php"
               style="color:var(--blue);font-weight:600">Create Account</a>
            <span style="color:var(--dim);margin:0 8px">|</span>
            <a href="forgot_password.php"
               style="color:var(--muted)">Forgot Password?</a>
        </div>

        <!-- Default credentials hint -->
        <div class="login-hint">
            Demo logins: <strong>admin / admin123</strong> &nbsp;&middot;&nbsp; <strong>demo / admin123</strong><br>
            <small>Change your password after logging in via Settings &rsaquo; Profile</small>
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
