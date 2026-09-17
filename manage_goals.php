<?php
// ============================================================
//  manage_goals.php  -  Savings Goals List
// ============================================================
//  Displays all goals belonging to the current user, grouped
//  by status. Active goals appear in a prominent card grid;
//  achieved and cancelled goals appear in a smaller table
//  below for reference.
// ============================================================

require_once 'includes/db.php';

$section = 'Savings';
$page    = 'manage_goals.php';
$title   = 'Savings Goals';
require_once 'includes/header.php';

$uid = current_user_id();


// ─── Fetch goals split by status ────────────────────────────
$active = db_all($conn, "
    SELECT id, goal_name, target_amount, saved_amount, target_date, status, created_at
    FROM savings_goals
    WHERE user_id = ? AND status = 'active'
    ORDER BY target_date ASC, created_at DESC
", "i", [$uid]);

$past = db_all($conn, "
    SELECT id, goal_name, target_amount, saved_amount, target_date, status
    FROM savings_goals
    WHERE user_id = ? AND status IN ('achieved', 'cancelled')
    ORDER BY status, target_date DESC
", "i", [$uid]);


// ─── Aggregates for the summary strip ──────────────────────
$tot_target = array_sum(array_column($active, 'target_amount'));
$tot_saved  = array_sum(array_column($active, 'saved_amount'));
$overall_pct = $tot_target > 0
             ? round(($tot_saved / $tot_target) * 100, 1)
             : 0;
?>

<!-- ─── Action bar ─── -->
<div class="action-bar">
    <a href="add_goal.php" class="btn btn-success">&plus; Add New Goal</a>
    <span style="color:var(--muted);font-size:.82rem;margin-left:auto">
        <?= count($active) ?> active &middot;
        <?= count($past) ?> completed
    </span>
</div>


<!-- ─── Summary strip ─── -->
<?php if (!empty($active)): ?>
<div class="summary-grid">
    <div class="sum-card bal">
        <div class="sum-label">Total Targeted</div>
        <div class="sum-val"><?= fmt($tot_target) ?></div>
        <div class="sum-sub">Across <?= count($active) ?> active goal(s)</div>
    </div>
    <div class="sum-card inc">
        <div class="sum-label">Total Saved</div>
        <div class="sum-val"><?= fmt($tot_saved) ?></div>
        <div class="sum-sub">So far</div>
    </div>
    <div class="sum-card sav">
        <div class="sum-label">Overall Progress</div>
        <div class="sum-val"><?= $overall_pct ?>%</div>
        <div class="sum-sub"><?= fmt($tot_target - $tot_saved) ?> to go</div>
    </div>
</div>
<?php endif; ?>


<!-- ─── Active goals as cards ─── -->
<?php if (empty($active)): ?>
    <div class="card">
        <div class="card-title">Active Goals</div>
        <div class="empty">
            No active goals yet.
            <a href="add_goal.php">Create your first goal</a> to start tracking progress.
        </div>
    </div>
<?php else: ?>
    <div class="goals-grid">
        <?php foreach ($active as $g):
            $pct = (float)$g['target_amount'] > 0
                 ? round(((float)$g['saved_amount'] / (float)$g['target_amount']) * 100, 1)
                 : 0;
            $pct_capped = min(100, $pct);
            $fill_class = $pct >= 100 ? 'ok' : ($pct >= 50 ? 'warn' : 'ok');
            // Days until target
            $days = (int) ((strtotime($g['target_date']) - time()) / 86400);
        ?>
        <div class="goal-card">
            <div class="goal-name"><?= htmlspecialchars($g['goal_name']) ?></div>

            <div class="goal-amounts">
                <div>
                    <div class="goal-amt-lbl">Saved</div>
                    <div class="goal-amt-saved"><?= fmt($g['saved_amount']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="goal-amt-lbl">Target</div>
                    <div class="goal-amt-target"><?= fmt($g['target_amount']) ?></div>
                </div>
            </div>

            <div class="prog-wrap" style="margin-top:8px">
                <div class="prog-fill ok" style="width:<?= $pct_capped ?>%"></div>
            </div>
            <div class="goal-pct"><?= $pct ?>% complete</div>

            <div class="goal-meta">
                <span title="Target date">
                    &#128197; <?= date('d M Y', strtotime($g['target_date'])) ?>
                </span>
                <span class="goal-days <?= $days < 0 ? 'overdue' : ($days < 30 ? 'soon' : '') ?>">
                    <?php if ($days < 0): ?>
                        Overdue by <?= abs($days) ?> day(s)
                    <?php elseif ($days === 0): ?>
                        Due today
                    <?php else: ?>
                        <?= $days ?> day(s) left
                    <?php endif; ?>
                </span>
            </div>

            <div class="goal-actions">
                <a href="edit_goal.php?id=<?= (int)$g['id'] ?>"
                   class="btn btn-sm btn-primary">Add Contribution</a>
                <a href="edit_goal.php?id=<?= (int)$g['id'] ?>&mode=edit"
                   class="btn btn-sm btn-warning">Edit</a>
                <a href="delete_goal.php?id=<?= (int)$g['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this goal? All contribution history will be lost.')">Delete</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<!-- ─── Past goals (achieved/cancelled) ─── -->
<?php if (!empty($past)): ?>
<div class="card" style="margin-top:18px">
    <div class="card-title">Past Goals</div>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Goal</th>
                    <th>Status</th>
                    <th>Target</th>
                    <th>Saved</th>
                    <th>Target Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past as $g): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($g['goal_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $g['status'] === 'achieved' ? 'inc' : 'warn' ?>">
                            <?= ucfirst($g['status']) ?>
                        </span>
                    </td>
                    <td class="amt-neu"><?= fmt($g['target_amount']) ?></td>
                    <td class="<?= $g['status'] === 'achieved' ? 'amt-inc' : 'amt-neu' ?>">
                        <?= fmt($g['saved_amount']) ?>
                    </td>
                    <td class="td-muted td-mono">
                        <?= date('d M Y', strtotime($g['target_date'])) ?>
                    </td>
                    <td>
                        <a href="delete_goal.php?id=<?= (int)$g['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this record?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- Goal card styling — local to this module -->
<style>
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
    }
    .goal-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 4px var(--shadow);
        transition: border-color .2s, transform .2s;
    }
    .goal-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 3px; height: 100%;
        background: var(--yellow);
    }
    .goal-card:hover {
        border-color: var(--yellow);
        transform: translateY(-2px);
    }
    .goal-name {
        font-weight: 700; color: var(--text-strong);
        font-size: 1rem; margin-bottom: 14px;
        letter-spacing: -.2px;
    }
    .goal-amounts {
        display: flex; justify-content: space-between;
        margin-bottom: 4px;
    }
    .goal-amt-lbl {
        font-size: .62rem; text-transform: uppercase;
        letter-spacing: 1.2px; color: var(--muted);
        margin-bottom: 2px;
    }
    .goal-amt-saved {
        font-family: 'DM Mono', monospace; font-weight: 500;
        color: var(--green); font-size: 1rem;
    }
    .goal-amt-target {
        font-family: 'DM Mono', monospace; font-weight: 500;
        color: var(--text); font-size: 1rem;
    }
    .goal-pct {
        font-size: .72rem; color: var(--muted);
        margin-top: 6px; text-align: right;
        font-family: 'DM Mono', monospace;
    }
    .goal-meta {
        display: flex; justify-content: space-between;
        font-size: .72rem; color: var(--muted);
        margin: 12px 0;
    }
    .goal-days.soon    { color: var(--yellow); }
    .goal-days.overdue { color: var(--red); }
    .goal-actions {
        display: flex; gap: 6px; flex-wrap: wrap;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }
    .goal-actions .btn { flex: 1; }
</style>

<?php require_once 'includes/footer.php'; ?>
