<?php
// ============================================================
//  add_goal.php  -  Create a New Savings Goal
// ============================================================
require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle submission ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $goal_name     = trim($_POST['goal_name']            ?? '');
    $target_amount = (float)($_POST['target_amount']     ?? 0);
    $target_date   = $_POST['target_date']               ?? '';
    $initial       = max(0, (float)($_POST['initial_amount'] ?? 0));

    // ── Validate ──────────────────────────────────────────
    $is_valid = $goal_name !== ''
             && strlen($goal_name) <= 100
             && $target_amount > 0
             && preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)
             && $initial >= 0
             && $initial <= $target_amount;

    if ($is_valid) {
        db_run($conn, "
            INSERT INTO savings_goals
                (user_id, goal_name, target_amount, saved_amount, target_date, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ", "isdds", [$uid, $goal_name, $target_amount, $initial, $target_date]);

        header('Location: manage_goals.php?status=goal_added');
        exit();
    }
    header('Location: add_goal.php?status=validation');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Savings';
$page    = 'add_goal.php';
$title   = 'Add New Savings Goal';
require_once 'includes/header.php';

// Default target date — 6 months from now
$default_date = date('Y-m-d', strtotime('+6 months'));
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Goal Details</div>
        <form method="POST">

            <div class="form-group">
                <label>Goal Name *</label>
                <input type="text" name="goal_name"
                       placeholder="e.g. New Laptop, Emergency Fund, Bike Trip"
                       required maxlength="100">
            </div>

            <div class="form-group">
                <label>Target Amount (&#8377;) *</label>
                <input type="number" name="target_amount"
                       placeholder="e.g. 60000"
                       min="1" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Target Date *</label>
                <input type="date" name="target_date"
                       value="<?= $default_date ?>"
                       min="<?= date('Y-m-d') ?>"
                       required>
                <p class="form-hint">When do you want to reach this goal?</p>
            </div>

            <div class="form-group">
                <label>Already Saved (optional)</label>
                <input type="number" name="initial_amount"
                       placeholder="0"
                       min="0" step="0.01" value="0">
                <p class="form-hint">
                    If you've already saved some money toward this goal,
                    enter it here so the progress bar starts where you actually are.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">&plus; Create Goal</button>
                <a href="manage_goals.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Tips for Effective Goals</div>
        <ul class="tip-list">
            <li>
                <div class="tip-head">Be specific</div>
                <div class="tip-body">
                    "Emergency Fund of &#8377;50,000" beats "Save more."
                    Specificity makes goals trackable.
                </div>
            </li>
            <li>
                <div class="tip-head">Set realistic deadlines</div>
                <div class="tip-body">
                    Three to twelve months works for most goals.
                    Too short and you'll give up; too long and you'll forget.
                </div>
            </li>
            <li>
                <div class="tip-head">Contribute regularly</div>
                <div class="tip-body">
                    Small weekly or monthly amounts compound faster
                    than waiting for a big windfall.
                </div>
            </li>
            <li>
                <div class="tip-head">Celebrate milestones</div>
                <div class="tip-body">
                    Notifications will fire at 25%, 50%, 75%, and 100%.
                    Acknowledge each one as a win.
                </div>
            </li>
        </ul>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
