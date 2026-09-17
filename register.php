<?php
// ============================================================
//  register.php  -  New User Registration
// ============================================================
//  Creates a new account by inserting into three tables in a
//  coordinated sequence:
//      1. users          (credentials + role)
//      2. user_profiles  (display name, etc.)
//      3. settings       (preferences with defaults)
//
//  All three must succeed for the registration to count. If
//  any step fails, the partial user row is rolled back so the
//  database is never left in a half-registered state.
// ============================================================

$skip_auth = true;
require_once 'includes/db.php';

// Already logged in? Send them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error    = '';
$old      = ['username' => '', 'email' => '', 'name' => '']; // sticky values

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username  = trim($_POST['username']         ?? '');
    $email     = trim($_POST['email']            ?? '');
    $name      = trim($_POST['name']             ?? '');
    $password  = $_POST['password']              ?? '';
    $confirm   = $_POST['confirm_password']      ?? '';

    // Repopulate the form if validation fails
    $old = ['username' => $username, 'email' => $email, 'name' => $name];

    // ── Server-side validation ────────────────────────────
    // Username: 3-50 chars, letters/digits/underscore only.
    // Mixed-case allowed but case-sensitive uniqueness applies.
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $error = 'Username must be 3 to 50 characters and contain only letters, digits, and underscore.';
    }
    // Email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $error = 'Please enter a valid email address.';
    }
    // Name: 2-100 chars, allow letters/spaces/dots/hyphens/apostrophes
    elseif (!preg_match("/^[A-Za-z .'\\-]{2,100}$/", $name)) {
        $error = 'Name must be 2 to 100 characters and contain only letters, spaces, dots, hyphens, or apostrophes.';
    }
    // Password length
    elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    }
    // Password confirmation
    elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    }
    else {
        // ── Uniqueness checks ─────────────────────────────
        $u_taken = db_one($conn,
            "SELECT id FROM users WHERE username = ? LIMIT 1",
            "s", [$username]
        );
        if ($u_taken) {
            header('Location: register.php?status=username_taken');
            exit();
        }
        $e_taken = db_one($conn,
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            "s", [$email]
        );
        if ($e_taken) {
            header('Location: register.php?status=email_taken');
            exit();
        }

        // ── Coordinated multi-table insert ────────────────
        // All new accounts get role='user' — only the admin
        // seeded by setup.sql is an admin. To promote, an
        // existing admin must use the manage_users page.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ok = db_run($conn,
            "INSERT INTO users (username, email, password, role, is_active)
             VALUES (?, ?, ?, 'user', 1)",
            "sss", [$username, $email, $hash]
        );

        if (!$ok) {
            $error = 'Could not create account. Please try again.';
        } else {
            $new_id = db_last_id($conn);

            // Profile row
            $p_ok = db_run($conn,
                "INSERT INTO user_profiles (user_id, name, monthly_goal)
                 VALUES (?, ?, 10000.00)",
                "is", [$new_id, $name]
            );

            // Settings row (defaults)
            $s_ok = db_run($conn,
                "INSERT INTO settings (user_id) VALUES (?)",
                "i", [$new_id]
            );

            if ($p_ok && $s_ok) {
                // Success — back to login with a success banner
                header('Location: login.php?status=account_created');
                exit();
            } else {
                // Rollback by deleting the user row.
                // Foreign-key CASCADE removes any partial rows
                // that did get created in user_profiles or settings.
                db_run($conn, "DELETE FROM users WHERE id = ?", "i", [$new_id]);
                $error = 'Could not initialise account. Please try again.';
            }
        }
    }
}

// If a status redirect brought us here, translate the code
// into a sticky error so the user sees it on the form.
if (isset($_GET['status'])) {
    $code = $_GET['status'];
    if ($code === 'username_taken') $error = 'That username is already taken.';
    elseif ($code === 'email_taken') $error = 'That email is already registered.';
}

$theme = get_theme();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account &mdash; BudgetWise</title>
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

    <!-- Registration card -->
    <div class="login-card">
        <h2 class="login-title">Create Account</h2>
        <p class="login-sub">Fill in your details to get started</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:18px">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text"
                       id="username"
                       name="username"
                       placeholder="e.g. aditya23"
                       value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>"
                       required
                       minlength="3"
                       maxlength="50"
                       pattern="[A-Za-z0-9_]+"
                       autocomplete="username">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email"
                       id="email"
                       name="email"
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>"
                       required
                       maxlength="100"
                       autocomplete="email">
            </div>

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text"
                       id="name"
                       name="name"
                       placeholder="e.g. Aditya Kumar"
                       value="<?= htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8') ?>"
                       required
                       maxlength="100"
                       autocomplete="name">
            </div>

            <div class="form-group">
                <label for="password">Password * (minimum 6 characters)</label>
                <input type="password"
                       id="password"
                       name="password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       required
                       minlength="6"
                       autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password"
                       id="confirm_password"
                       name="confirm_password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       required
                       minlength="6"
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
                Create Account &rarr;
            </button>
        </form>

        <div class="login-hint">
            Already have an account?
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
