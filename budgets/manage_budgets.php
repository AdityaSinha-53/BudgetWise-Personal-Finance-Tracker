<?php
// ============================================================
//  manage_budgets.php  -  Set Monthly Budgets per Category
// ============================================================
//  A single page combining:
//      1. Add / update form  (composite upsert by user+cat+month)
//      2. List of all budgets for the current user
//
//  Budgets are scoped to a specific month + year so the same
//  category can carry different limits for different months.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle add or update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_id = (int)($_POST['category_id']   ?? 0);
    $limit       = (float)($_POST['monthly_limit'] ?? 0);
    $month       = (int)($_POST['period_month']  ?? date('n'));
    $year        = (int)($_POST['period_year']   ?? date('Y'));

    // Defensive bounds — month in [1, 12], year within +/-3
    if ($month < 1 || $month > 12)                        $month = (int)date('n');
    if ($year  < 2000 || $year > (int)date('Y') + 3)      $year  = (int)date('Y');

    // Validate that the chosen category is an expense category
    // visible to the user (default or own). Budgets only apply
    // to expenses.
    $valid_cat = false;
    if ($category_id > 0 && $limit > 0) {
        $row = db_one($conn, "
            SELECT id FROM categories
            WHERE id = ?
              AND type = 'expense'
              AND (is_default = 1 OR user_id = ?)
        ", "ii", [$category_id, $uid]);
        $valid_cat = (bool)$row;
    }

    if ($valid_cat) {
        // Composite upsert: on duplicate (user, category, month, year)
        // update the existing limit rather than creating a new row.
        db_run($conn, "
            INSERT INTO budgets
                (user_id, category_id, monthly_limit, period_month, period_year)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)
        ", "iidii", [$uid, $category_id, $limit, $month, $year]);

        header('Location: manage_budgets.php?status=budget_saved');
    } else {
        header('Location: manage_budgets.php?status=validation');
    }
    exit();
}


// ─── Render page ───────────────────────────────────────────
$section = 'Budgets';
$page    = 'manage_budgets.php';
$title   = 'Manage Budgets';
require_once 'includes/header.php';

$cats = get_categories($conn);

// Filter list of budgets — default to current month/year, but
// allow ?month=N&year=Y to view past or future plans.
$view_m = (int)($_GET['month'] ?? date('n'));
$view_y = (int)($_GET['year']  ?? date('Y'));
if ($view_m < 1 || $view_m > 12)                       $view_m = (int)date('n');
if ($view_y < 2000 || $view_y > (int)date('Y') + 3)    $view_y = (int)date('Y');

$budgets = db_all($conn, "
    SELECT b.id, b.category_id, b.monthly_limit,
           b.period_month, b.period_year, c.name AS category_name
    FROM budgets b
    LEFT JOIN categories c ON c.id = b.category_id
    WHERE b.user_id = ?
      AND b.period_month = ? AND b.period_year = ?
    ORDER BY c.name
", "iii", [$uid, $view_m, $view_y]);
$b_count = count($budgets);

$tot = db_one($conn, "
    SELECT COALESCE(SUM(monthly_limit), 0) AS t
    FROM budgets
    WHERE user_id = ? AND period_month = ? AND period_year = ?
", "iii", [$uid, $view_m, $view_y]);
$total_budget = (float) $tot['t'];

$view_label = date('F Y', mktime(0, 0, 0, $view_m, 1, $view_y));
?>

<!-- Month/year selector -->
<form method="GET" class="period-form">
    <select name="month">
        <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= $i === $view_m ? 'selected' : '' ?>>
                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
            </option>
        <?php endfor; ?>
    </select>
    <select name="year">
        <?php for ($yi = (int)date('Y') + 1; $yi >= (int)date('Y') - 3; $yi--): ?>
            <option value="<?= $yi ?>" <?= $yi === $view_y ? 'selected' : '' ?>><?= $yi ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary">View</button>
    <a href="budget_vs_actual.php?month=<?= $view_m ?>&year=<?= $view_y ?>"
       class="btn btn-success btn-end">Budget vs Actual &rarr;</a>
</form>


<div class="grid-form">

    <!-- LEFT: Add / update form -->
    <div class="card">
        <div class="card-title">Add or Update Budget</div>
        <form method="POST">
            <div class="form-group">
                <label>Expense Category *</label>
                <select name="category_id" required>
                    <option value="" disabled selected>Select expense category</option>
                    <?php foreach ($cats['expense'] as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Monthly Limit (&#8377;) *</label>
                <input type="number" name="monthly_limit"
                       placeholder="e.g. 3000"
                       min="1" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Applies to Month *</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <select name="period_month" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === (int)date('n') ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="period_year" required>
                        <?php for ($yi = (int)date('Y') + 1; $yi >= (int)date('Y') - 3; $yi--): ?>
                            <option value="<?= $yi ?>" <?= $yi === (int)date('Y') ? 'selected' : '' ?>><?= $yi ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <p class="form-hint">
                If a budget for this category and period already exists, the limit will be updated.
            </p>
            <button type="submit" class="btn btn-primary btn-full">Save Budget</button>
        </form>

        <!-- Total -->
        <div class="form-stat">
            <div class="sum-label">Total Budget for <?= htmlspecialchars($view_label) ?></div>
            <div class="form-stat-val"><?= fmt($total_budget) ?></div>
            <div class="sum-sub">
                <?= $b_count ?> categor<?= $b_count === 1 ? 'y' : 'ies' ?> tracked
            </div>
        </div>
    </div>

    <!-- RIGHT: List of budgets for the viewed period -->
    <div class="card">
        <div class="card-title">
            Budgets &mdash; <?= htmlspecialchars($view_label) ?>
            <span class="card-meta"><?= $b_count ?> set</span>
        </div>
        <div class="tbl-wrap">
        <?php if ($b_count === 0): ?>
            <div class="empty">No budgets set for <?= htmlspecialchars($view_label) ?>.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Monthly Limit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($budgets as $b): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($b['category_name'] ?? '—') ?></td>
                    <td class="amt-neu"><?= fmt($b['monthly_limit']) ?></td>
                    <td>
                        <div class="action-row">
                            <a href="edit_budget.php?id=<?= (int)$b['id'] ?>"
                               class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_budget.php?id=<?= (int)$b['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this budget?')">Remove</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
