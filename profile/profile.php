<?php
// ============================================================
//  profile.php  -  Read-Only Profile Summary
// ============================================================
//  Shows the user's account information and computed
//  statistics. Edit operations live in edit_profile.php and
//  change_password.php for clear separation of concerns.
// ============================================================

require_once 'includes/db.php';

$section = 'Profile';
$page    = 'profile.php';
$title   = 'My Profile';
require_once 'includes/header.php';

$uid     = current_user_id();
$user    = current_user($conn);
$prof    = current_profile($conn);


// ─── Compute account statistics ────────────────────────────
$stats = db_one($conn, "
    SELECT
        COUNT(*) AS total_txns,
        COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS total_inc,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS total_exp,
        MIN(txn_date) AS first_txn,
        MAX(txn_date) AS last_txn
    FROM transactions
    WHERE user_id = ?
", "i", [$uid]);

$goal_count = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM savings_goals WHERE user_id = ?",
    "i", [$uid])['c'];

$budget_count = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM budgets WHERE user_id = ?",
    "i", [$uid])['c'];

$net = (float)$stats['total_inc'] - (float)$stats['total_exp'];
?>

<!-- Action bar at the top -->
<div class="action-bar">
    <a href="edit_profile.php"     class="btn btn-primary">Edit Profile</a>
    <a href="change_password.php"  class="btn btn-warning">Change Password</a>
    <a href="settings.php"         class="btn btn-ghost">Preferences</a>
    <a href="payment_methods.php"  class="btn btn-ghost btn-end">Payment Methods</a>
</div>


<div class="grid-2">

    <!-- LEFT: identity card -->
    <div class="card">
        <div class="card-title">Account</div>

        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
            <div class="sf-avatar" style="width:56px;height:56px;font-size:1.4rem">
                <?= htmlspecialchars(strtoupper(substr($prof['name'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div>
                <div style="font-weight:700;color:var(--text-strong);font-size:1.05rem">
                    <?= htmlspecialchars($prof['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="color:var(--muted);font-size:.82rem">
                    <?= htmlspecialchars($user['username'] ?? '—') ?>
                    <?php if (is_admin()): ?>
                        <span class="sf-role">ADMIN</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <dl class="info-list">
            <dt>Email</dt>
            <dd class="info-val info-muted">
                <?= htmlspecialchars($user['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </dd>

            <dt>Phone</dt>
            <dd class="info-val info-muted">
                <?= htmlspecialchars($prof['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
            </dd>

            <dt>Monthly Savings Goal</dt>
            <dd class="info-val info-yellow"><?= fmt($prof['monthly_goal'] ?? 0) ?></dd>

            <dt>Account Created</dt>
            <dd class="info-val info-muted">
                <?= isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '—' ?>
            </dd>

            <dt>Account Status</dt>
            <dd>
                <span class="badge badge-inc">Active</span>
            </dd>
        </dl>
    </div>

    <!-- RIGHT: statistics -->
    <div class="card">
        <div class="card-title">Account Statistics</div>
        <dl class="info-list">
            <dt>Total Transactions</dt>
            <dd class="info-val info-neutral"><?= (int)$stats['total_txns'] ?></dd>

            <dt>Total Income</dt>
            <dd class="info-val info-green"><?= fmt($stats['total_inc']) ?></dd>

            <dt>Total Expenses</dt>
            <dd class="info-val info-red"><?= fmt($stats['total_exp']) ?></dd>

            <dt>Net Balance</dt>
            <dd class="info-val info-<?= $net >= 0 ? 'green' : 'red' ?>">
                <?= fmt($net) ?>
            </dd>

            <dt>Savings Goals</dt>
            <dd class="info-val info-neutral"><?= $goal_count ?></dd>

            <dt>Budgets Configured</dt>
            <dd class="info-val info-neutral"><?= $budget_count ?></dd>

            <dt>First Transaction</dt>
            <dd class="info-val info-muted">
                <?= $stats['first_txn'] ? date('d M Y', strtotime($stats['first_txn'])) : '—' ?>
            </dd>

            <dt>Last Transaction</dt>
            <dd class="info-val info-muted">
                <?= $stats['last_txn']  ? date('d M Y', strtotime($stats['last_txn']))  : '—' ?>
            </dd>
        </dl>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
