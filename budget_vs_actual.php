<?php
// ============================================================
//  budget_vs_actual.php  -  Compare Budgets to Actual Spending
// ============================================================
//  This page is where budget notifications are generated.
//  When the user views the comparison for a given period,
//  the page detects categories that have crossed the 70%
//  warning or 100% exceeded threshold and inserts a
//  notification — but only the first time for each category
//  and period, so the user does not see repeated alerts.
// ============================================================

require_once 'includes/db.php';

$section = 'Budgets';
$page    = 'budget_vs_actual.php';
$title   = 'Budget vs Actual';
require_once 'includes/header.php';

$uid = current_user_id();


// ─── Period selection ──────────────────────────────────────
$m = (int)($_GET['month'] ?? date('n'));
$y = (int)($_GET['year']  ?? date('Y'));

if ($m < 1 || $m > 12)                       $m = (int)date('n');
if ($y < 2000 || $y > (int)date('Y') + 3)    $y = (int)date('Y');

$label = date('F Y', mktime(0, 0, 0, $m, 1, $y));


// ─── The comparison query ──────────────────────────────────
// LEFT JOIN ensures every budget appears even if no spending
// has happened yet. The transactions side of the join is
// also filtered by user_id and the period, otherwise we'd
// sum across years.
$rows = db_all($conn, "
    SELECT
        b.id,
        b.category_id,
        b.monthly_limit,
        c.name AS category_name,
        COALESCE(SUM(t.amount), 0) AS actual_spent
    FROM budgets b
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN transactions t
        ON t.category_id = b.category_id
       AND t.user_id     = b.user_id
       AND t.type        = 'expense'
       AND MONTH(t.txn_date) = ?
       AND YEAR(t.txn_date)  = ?
    WHERE b.user_id = ?
      AND b.period_month = ?
      AND b.period_year  = ?
    GROUP BY b.id, b.category_id, b.monthly_limit, c.name
    ORDER BY c.name
", "iiiii", [$m, $y, $uid, $m, $y]);


// ─── Summary numbers ───────────────────────────────────────
$total_budget = array_sum(array_column($rows, 'monthly_limit'));
$total_spent  = array_sum(array_column($rows, 'actual_spent'));
$remaining    = $total_budget - $total_spent;

$over_count = 0;
foreach ($rows as $r) {
    if ($r['actual_spent'] > $r['monthly_limit']) $over_count++;
}


// ─── Notification generation ───────────────────────────────
// For each budget row, if the user has crossed 70% or 100%
// AND we have not already sent that notification for that
// category and period, insert one.
//
// "Same period" is detected by checking the notifications
// table for a matching title — keeps the logic simple and
// avoids adding period columns to the notifications table.
foreach ($rows as $r) {
    if ((float)$r['monthly_limit'] <= 0) continue;

    $pct = ($r['actual_spent'] / $r['monthly_limit']) * 100;

    if ($pct >= 100) {
        notify_once($conn, $uid, 'budget_exceeded',
            "Budget exceeded — {$r['category_name']} for {$label}",
            "You have spent " . strip_rupee(fmt($r['actual_spent'])) .
            " of " . strip_rupee(fmt($r['monthly_limit'])) .
            " budgeted for {$r['category_name']} this month.");
    } elseif ($pct >= 70) {
        notify_once($conn, $uid, 'budget_warning',
            "Budget warning — {$r['category_name']} for {$label}",
            "You have spent " . strip_rupee(fmt($r['actual_spent'])) .
            " of " . strip_rupee(fmt($r['monthly_limit'])) .
            " budgeted for {$r['category_name']} this month (" .
            round($pct) . "% used).");
    }
}


// ─── Helper: insert a notification only if not already sent ─
// Defined inline here because it is page-specific. The check
// uses the title which is unique per category and period.
function notify_once($conn, $user_id, $type, $title, $message)
{
    $existing = db_one($conn,
        "SELECT id FROM notifications
         WHERE user_id = ? AND title = ? LIMIT 1",
        "is", [$user_id, $title]
    );
    if (!$existing) {
        notify($conn, $user_id, $type, $title, $message);
    }
}

// Strip the rupee character so titles don't contain raw bytes
// that would be awkward inside notification text.
function strip_rupee(string $s): string
{
    return str_replace("\xe2\x82\xb9", '₹', $s);
}
?>

<!-- ─── Period selector ─── -->
<form method="GET" class="period-form">
    <select name="month">
        <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= $i === $m ? 'selected' : '' ?>>
                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
            </option>
        <?php endfor; ?>
    </select>
    <select name="year">
        <?php for ($yi = (int)date('Y') + 1; $yi >= (int)date('Y') - 3; $yi--): ?>
            <option value="<?= $yi ?>" <?= $yi === $y ? 'selected' : '' ?>><?= $yi ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary">View</button>
    <a href="manage_budgets.php?month=<?= $m ?>&year=<?= $y ?>"
       class="btn btn-ghost btn-end">&larr; Manage Budgets</a>
</form>

<!-- ─── Three summary cards ─── -->
<div class="summary-grid">
    <div class="sum-card bal">
        <div class="sum-label">Budgeted &mdash; <?= htmlspecialchars($label) ?></div>
        <div class="sum-val"><?= fmt($total_budget) ?></div>
        <div class="sum-sub"><?= count($rows) ?> categories</div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Total Spent</div>
        <div class="sum-val"><?= fmt($total_spent) ?></div>
        <div class="sum-sub">In this period</div>
    </div>
    <div class="sum-card <?= $remaining >= 0 ? 'inc' : 'exp' ?>">
        <div class="sum-label">Remaining</div>
        <div class="sum-val"><?= fmt(abs($remaining)) ?></div>
        <div class="sum-sub"><?= $remaining >= 0 ? 'Available' : 'Over budget' ?></div>
    </div>
</div>

<!-- ─── Comparison table ─── -->
<div class="card">
    <div class="card-title">
        Budget vs Actual &mdash; <?= htmlspecialchars($label) ?>
        <?php if ($over_count > 0): ?>
            <span class="badge badge-exp"><?= $over_count ?> over budget</span>
        <?php elseif (count($rows) > 0): ?>
            <span class="badge badge-inc">All within budget &check;</span>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">
            No budgets configured for this period.
            <a href="manage_budgets.php?month=<?= $m ?>&year=<?= $y ?>">Set some budgets</a> first.
        </div>
    <?php else: ?>
    <div class="tbl-wrap">
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Budget</th>
                <th>Spent</th>
                <th>Remaining</th>
                <th class="th-wide">Usage</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pct = $r['monthly_limit'] > 0
                 ? min(100, round(($r['actual_spent'] / $r['monthly_limit']) * 100, 1))
                 : 0;
            $rem  = $r['monthly_limit'] - $r['actual_spent'];
            $over = $r['actual_spent'] > $r['monthly_limit'];
            $fill = $pct < 70 ? 'ok' : ($pct < 100 ? 'warn' : 'over');
        ?>
        <tr>
            <td class="td-name"><?= htmlspecialchars($r['category_name'] ?? '—') ?></td>
            <td class="amt-neu"><?= fmt($r['monthly_limit']) ?></td>
            <td class="<?= $over ? 'amt-exp' : 'amt-neu' ?>"><?= fmt($r['actual_spent']) ?></td>
            <td class="<?= $rem >= 0 ? 'amt-inc' : 'amt-exp' ?>">
                <?= $rem >= 0 ? '' : '-' ?><?= fmt(abs($rem)) ?>
            </td>
            <td>
                <div class="usage-cell">
                    <div class="prog-wrap">
                        <div class="prog-fill <?= $fill ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="usage-pct"><?= $pct ?>%</span>
                </div>
            </td>
            <td>
                <?php if ($over): ?>
                    <span class="badge badge-exp">Over</span>
                <?php elseif ($pct >= 70): ?>
                    <span class="badge badge-warn">Warning</span>
                <?php else: ?>
                    <span class="badge badge-inc">OK</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
