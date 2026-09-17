<?php
// ============================================================
//  edit_budget.php  -  Edit a Budget Limit
// ============================================================
require_once 'includes/db.php';

$uid = current_user_id();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_budgets.php');
    exit();
}

if (!user_owns($conn, 'budgets', $id)) {
    header('Location: manage_budgets.php?status=unauthorized');
    exit();
}

$row = db_one($conn, "
    SELECT b.id, b.category_id, b.monthly_limit,
           b.period_month, b.period_year, c.name AS category_name
    FROM budgets b
    LEFT JOIN categories c ON c.id = b.category_id
    WHERE b.id = ?
", "i", [$id]);

if (!$row) {
    header('Location: manage_budgets.php?status=not_found');
    exit();
}

// ─── Handle save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $limit = (float)($_POST['monthly_limit'] ?? 0);

    if ($limit > 0) {
        db_run($conn, "
            UPDATE budgets SET monthly_limit = ? WHERE id = ?
        ", "di", [$limit, $id]);

        header('Location: manage_budgets.php?month=' . (int)$row['period_month']
               . '&year=' . (int)$row['period_year']
               . '&status=budget_saved');
        exit();
    }

    header('Location: edit_budget.php?id=' . $id . '&status=validation');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Budgets';
$page    = 'manage_budgets.php';
$title   = 'Edit Budget';
require_once 'includes/header.php';

// Month-to-date spending in this category for this period
$spent_row = db_one($conn, "
    SELECT COALESCE(SUM(amount), 0) AS spent
    FROM transactions
    WHERE user_id = ?
      AND category_id = ?
      AND type = 'expense'
      AND MONTH(txn_date) = ?
      AND YEAR(txn_date)  = ?
", "iiii", [
    $uid,
    (int)$row['category_id'],
    (int)$row['period_month'],
    (int)$row['period_year'],
]);
$spent = (float) $spent_row['spent'];

$period_label = date('F Y', mktime(0, 0, 0, $row['period_month'], 1, $row['period_year']));
$usage_pct    = $row['monthly_limit'] > 0
              ? round(($spent / $row['monthly_limit']) * 100, 1)
              : 0;
$usage_pct    = min(999, $usage_pct);
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Edit Budget #<?= $id ?></div>
        <form method="POST">

            <div class="form-group">
                <label>Category</label>
                <input type="text" value="<?= htmlspecialchars($row['category_name'] ?? '—') ?>"
                       disabled style="opacity:.7">
                <p class="form-hint">
                    Category and period cannot be changed. To move this
                    budget to a different category, delete it and create
                    a new one.
                </p>
            </div>

            <div class="form-group">
                <label>Period</label>
                <input type="text" value="<?= htmlspecialchars($period_label) ?>"
                       disabled style="opacity:.7">
            </div>

            <div class="form-group">
                <label>Monthly Limit (&#8377;) *</label>
                <input type="number" name="monthly_limit"
                       value="<?= htmlspecialchars($row['monthly_limit']) ?>"
                       min="1" step="0.01" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                <a href="manage_budgets.php?month=<?= (int)$row['period_month'] ?>&year=<?= (int)$row['period_year'] ?>"
                   class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Current Usage</div>
        <dl class="info-list">
            <dt>Current Limit</dt>
            <dd class="info-val info-neutral"><?= fmt($row['monthly_limit']) ?></dd>
            <dt>Spent This Period</dt>
            <dd class="info-val info-<?= $spent > $row['monthly_limit'] ? 'red' : 'neutral' ?>">
                <?= fmt($spent) ?>
            </dd>
            <dt>Usage</dt>
            <dd class="info-val info-<?= $usage_pct >= 100 ? 'red' : ($usage_pct >= 70 ? 'yellow' : 'green') ?>">
                <?= $usage_pct ?>%
            </dd>
        </dl>

        <!-- Progress bar -->
        <div style="margin-top:14px">
            <div class="prog-wrap">
                <div class="prog-fill <?= $usage_pct >= 100 ? 'over' : ($usage_pct >= 70 ? 'warn' : 'ok') ?>"
                     style="width:<?= min(100, $usage_pct) ?>%"></div>
            </div>
        </div>

        <p class="form-hint" style="margin-top:14px">
            Set a limit slightly above your typical month so the
            warnings are useful, not constant.
        </p>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
