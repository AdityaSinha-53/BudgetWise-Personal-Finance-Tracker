<?php
// ============================================================
//  edit_goal.php  -  Goal Edit + Contribution Page
// ============================================================
//  Two modes on a single page, switched by ?mode=...
//
//      Default (contribute mode):
//          - Adds money toward the existing goal
//          - Triggers milestone notifications at 25/50/75/100%
//          - Auto-marks goal as 'achieved' on 100%
//
//      ?mode=edit (edit mode):
//          - Change name, target, date, or status
//          - Does NOT modify saved_amount
// ============================================================

require_once 'includes/db.php';

$uid  = current_user_id();
$id   = (int)($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'contribute';
if (!in_array($mode, ['contribute', 'edit'], true)) $mode = 'contribute';

if ($id <= 0) {
    header('Location: manage_goals.php');
    exit();
}

if (!user_owns($conn, 'savings_goals', $id)) {
    header('Location: manage_goals.php?status=unauthorized');
    exit();
}

$row = db_one($conn,
    "SELECT id, user_id, goal_name, target_amount, saved_amount,
            target_date, status, created_at
     FROM savings_goals WHERE id = ?",
    "i", [$id]
);

if (!$row) {
    header('Location: manage_goals.php?status=not_found');
    exit();
}


// ─── POST handler ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Contribute mode ────────────────────────────────────
    if ($mode === 'contribute') {

        $amount = (float)($_POST['amount'] ?? 0);

        if ($amount <= 0) {
            header('Location: edit_goal.php?id=' . $id . '&status=validation');
            exit();
        }

        $old_saved = (float)$row['saved_amount'];
        $new_saved = $old_saved + $amount;
        $target    = (float)$row['target_amount'];

        // Determine the new status — if this contribution
        // pushes us over the target, mark the goal achieved.
        $new_status = $row['status'];
        if ($new_saved >= $target && $new_status === 'active') {
            $new_status = 'achieved';
        }

        db_run($conn, "
            UPDATE savings_goals
            SET saved_amount = ?, status = ?
            WHERE id = ?
        ", "dsi", [$new_saved, $new_status, $id]);

        // ── Milestone notifications ────────────────────────
        // Calculate old and new percentages, then fire a
        // notification for each threshold crossed in between.
        if ($target > 0) {
            $old_pct = ($old_saved / $target) * 100;
            $new_pct = ($new_saved / $target) * 100;

            foreach ([25, 50, 75, 100] as $threshold) {
                if ($old_pct < $threshold && $new_pct >= $threshold) {
                    $type  = $threshold === 100 ? 'goal_achieved' : 'goal_milestone';
                    $title = $threshold === 100
                           ? "Goal achieved — {$row['goal_name']}"
                           : "{$threshold}% reached — {$row['goal_name']}";
                    $msg   = $threshold === 100
                           ? "You have fully funded your '{$row['goal_name']}' goal. Time to celebrate!"
                           : "You have reached {$threshold}% of your '{$row['goal_name']}' goal.";
                    // Deduplicate by title — only fire once
                    $existing = db_one($conn,
                        "SELECT id FROM notifications WHERE user_id=? AND title=? LIMIT 1",
                        "is", [$uid, $title]
                    );
                    if (!$existing) {
                        notify($conn, $uid, $type, $title, $msg);
                    }
                }
            }
        }

        header('Location: manage_goals.php?status=contribution_added');
        exit();
    }

    // ── Edit mode ──────────────────────────────────────────
    if ($mode === 'edit') {

        $goal_name     = trim($_POST['goal_name']        ?? '');
        $target_amount = (float)($_POST['target_amount'] ?? 0);
        $target_date   = $_POST['target_date']           ?? '';
        $status        = $_POST['status']                ?? 'active';

        $is_valid = $goal_name !== ''
                 && strlen($goal_name) <= 100
                 && $target_amount > 0
                 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)
                 && in_array($status, ['active', 'achieved', 'cancelled'], true);

        if ($is_valid) {
            db_run($conn, "
                UPDATE savings_goals
                SET goal_name = ?, target_amount = ?,
                    target_date = ?, status = ?
                WHERE id = ?
            ", "sdssi", [$goal_name, $target_amount, $target_date, $status, $id]);

            header('Location: manage_goals.php?status=goal_updated');
            exit();
        }
        header('Location: edit_goal.php?id=' . $id . '&mode=edit&status=validation');
        exit();
    }
}


// ─── Render the page ───────────────────────────────────────
$section = 'Savings';
$page    = 'manage_goals.php';
$title   = $mode === 'edit' ? 'Edit Goal' : 'Add Contribution';
require_once 'includes/header.php';

$pct = (float)$row['target_amount'] > 0
     ? round(((float)$row['saved_amount'] / (float)$row['target_amount']) * 100, 1)
     : 0;
$pct_capped = min(100, $pct);
$remaining  = max(0, (float)$row['target_amount'] - (float)$row['saved_amount']);
?>

<div class="grid-form">

    <div class="card">
        <?php if ($mode === 'contribute'): ?>
            <!-- ── Contribute form ── -->
            <div class="card-title">Contribute to: <?= htmlspecialchars($row['goal_name']) ?></div>

            <?php if ($row['status'] !== 'active'): ?>
                <div class="alert alert-info" style="margin-bottom:16px">
                    This goal is <?= htmlspecialchars($row['status']) ?> and cannot accept further contributions.
                    <a href="edit_goal.php?id=<?= $id ?>&mode=edit" style="color:var(--blue)">Edit goal</a>
                    to reactivate it.
                </div>
            <?php endif; ?>

            <form method="POST" <?= $row['status'] !== 'active' ? 'style="opacity:.5;pointer-events:none"' : '' ?>>
                <div class="form-group">
                    <label>Contribution Amount (&#8377;) *</label>
                    <input type="number" name="amount"
                           placeholder="e.g. 5000"
                           min="0.01" step="0.01"
                           max="<?= htmlspecialchars($remaining > 0 ? $remaining : $row['target_amount']) ?>"
                           required autofocus>
                    <p class="form-hint">
                        Remaining to reach the target: <?= fmt($remaining) ?>
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-full">Record Contribution</button>
                    <a href="manage_goals.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>

            <div style="margin-top:18px;text-align:center">
                <a href="edit_goal.php?id=<?= $id ?>&mode=edit"
                   class="btn btn-sm btn-ghost">Edit goal details instead</a>
            </div>

        <?php else: ?>
            <!-- ── Edit form ── -->
            <div class="card-title">Edit Goal: <?= htmlspecialchars($row['goal_name']) ?></div>

            <form method="POST">
                <div class="form-group">
                    <label>Goal Name *</label>
                    <input type="text" name="goal_name"
                           value="<?= htmlspecialchars($row['goal_name']) ?>"
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label>Target Amount (&#8377;) *</label>
                    <input type="number" name="target_amount"
                           value="<?= htmlspecialchars($row['target_amount']) ?>"
                           min="1" step="0.01" required>
                    <p class="form-hint">
                        Already saved: <?= fmt($row['saved_amount']) ?>
                        (cannot be edited here — only through contributions)
                    </p>
                </div>

                <div class="form-group">
                    <label>Target Date *</label>
                    <input type="date" name="target_date"
                           value="<?= htmlspecialchars($row['target_date']) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="active"    <?= $row['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="achieved"  <?= $row['status'] === 'achieved'  ? 'selected' : '' ?>>Achieved</option>
                        <option value="cancelled" <?= $row['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                    <a href="manage_goals.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>

            <div style="margin-top:18px;text-align:center">
                <a href="edit_goal.php?id=<?= $id ?>"
                   class="btn btn-sm btn-ghost">Add a contribution instead</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Progress panel ── -->
    <div class="card">
        <div class="card-title">Progress</div>

        <div style="text-align:center;margin-bottom:18px">
            <div style="font-family:'Fraunces',serif;font-size:2.5rem;font-weight:800;color:var(--yellow);letter-spacing:-1.5px">
                <?= $pct ?>%
            </div>
            <div style="font-size:.72rem;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase">
                complete
            </div>
        </div>

        <div class="prog-wrap" style="margin-bottom:18px">
            <div class="prog-fill ok" style="width:<?= $pct_capped ?>%"></div>
        </div>

        <dl class="info-list">
            <dt>Target Amount</dt>
            <dd class="info-val info-neutral"><?= fmt($row['target_amount']) ?></dd>

            <dt>Saved So Far</dt>
            <dd class="info-val info-green"><?= fmt($row['saved_amount']) ?></dd>

            <dt>Remaining</dt>
            <dd class="info-val info-<?= $remaining > 0 ? 'yellow' : 'green' ?>">
                <?= fmt($remaining) ?>
            </dd>

            <dt>Target Date</dt>
            <dd class="info-val info-muted">
                <?= date('d M Y', strtotime($row['target_date'])) ?>
            </dd>

            <dt>Status</dt>
            <dd>
                <span class="badge badge-<?= $row['status'] === 'active' ? 'info' : ($row['status'] === 'achieved' ? 'inc' : 'warn') ?>">
                    <?= ucfirst($row['status']) ?>
                </span>
            </dd>
        </dl>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
