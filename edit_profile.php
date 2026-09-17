<?php
// ============================================================
//  edit_profile.php  -  Update Personal Details
// ============================================================
//  Updates fields across two tables in a coordinated pair:
//      users         — email
//      user_profiles — name, phone, monthly_goal
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name']         ?? '');
    $email = trim($_POST['email']        ?? '');
    $phone = trim($_POST['phone']        ?? '');
    $goal  = (float)($_POST['monthly_goal'] ?? 0);

    $is_valid = $name !== ''
             && strlen($name) <= 100
             && filter_var($email, FILTER_VALIDATE_EMAIL)
             && strlen($email) <= 100
             && strlen($phone) <= 20
             && $goal > 0;

    if ($is_valid) {
        // Check that the email is not in use by a different account
        $taken = db_one($conn,
            "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1",
            "si", [$email, $uid]
        );
        if ($taken) {
            header('Location: edit_profile.php?status=email_taken');
            exit();
        }

        // Update both tables. If the user_profiles row doesn't
        // exist (shouldn't happen but defensive), insert it.
        db_run($conn, "UPDATE users SET email = ? WHERE id = ?",
               "si", [$email, $uid]);

        db_run($conn, "
            INSERT INTO user_profiles (user_id, name, phone, monthly_goal)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = VALUES(phone),
                monthly_goal = VALUES(monthly_goal)
        ", "issd", [$uid, $name, $phone, $goal]);

        header('Location: profile.php?status=profile_saved');
        exit();
    }
    header('Location: edit_profile.php?status=validation');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Profile';
$page    = 'profile.php';
$title   = 'Edit Profile';
require_once 'includes/header.php';

$user = current_user($conn);
$prof = current_profile($conn);
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Edit Personal Details</div>
        <form method="POST">

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name"
                       value="<?= htmlspecialchars($prof['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required maxlength="100">
            </div>

            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required maxlength="100">
                <p class="form-hint">Used for sign-in and password recovery.</p>
            </div>

            <div class="form-group">
                <label>Phone (optional)</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($prof['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       maxlength="20"
                       placeholder="+91-9876543210">
            </div>

            <div class="form-group">
                <label>Monthly Savings Goal (&#8377;) *</label>
                <input type="number" name="monthly_goal"
                       value="<?= htmlspecialchars($prof['monthly_goal'] ?? 10000, ENT_QUOTES, 'UTF-8') ?>"
                       min="1" step="0.01" required>
                <p class="form-hint">
                    Used in dashboard summaries to track your savings progress.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                <a href="profile.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Account Details (Read-Only)</div>
        <dl class="info-list">
            <dt>Username</dt>
            <dd class="info-val info-muted">
                <?= htmlspecialchars($user['username'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </dd>
            <dt>Role</dt>
            <dd class="info-val info-muted"><?= htmlspecialchars(ucfirst($user['role'] ?? '—')) ?></dd>
            <dt>Account Created</dt>
            <dd class="info-val info-muted">
                <?= isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '—' ?>
            </dd>
        </dl>
        <p class="form-hint" style="margin-top:14px">
            Username and role cannot be changed from this page.
            Contact an administrator if either needs updating.
        </p>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
