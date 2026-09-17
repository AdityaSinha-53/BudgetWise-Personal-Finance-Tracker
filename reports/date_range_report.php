<?php
// ============================================================
//  date_range_report.php  -  Custom Date Range Report
// ============================================================
//  Accepts ?from=YYYY-MM-DD&to=YYYY-MM-DD and produces a
//  comprehensive report covering that range:
//      - Summary totals
//      - Breakdown by category
//      - Breakdown by payment method
//      - Full transaction listing
//
//  Default range when no parameters are given: this month so
//  far. Defensive bounds reject malformed dates.
// ============================================================

require_once 'includes/db.php';

$section = 'Reports';
$page    = 'date_range_report.php';
$title   = 'Date Range Report';
require_once 'includes/header.php';

$uid = current_user_id();


// ─── Parse and validate the date range ─────────────────────
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Swap if user gave them backwards
if ($from > $to) [$from, $to] = [$to, $from];

$range_label = date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to));
$range_days  = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);

// Audit log
log_report($conn, 'date_range', $from, $to);


// ─── Summary ───────────────────────────────────────────────
$summary = db_one($conn, "
    SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS inc,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS exp,
        COUNT(*) AS cnt
    FROM transactions
    WHERE user_id = ? AND txn_date BETWEEN ? AND ?
", "iss", [$uid, $from, $to]);

$inc = (float)$summary['inc'];
$exp = (float)$summary['exp'];
$net = $inc - $exp;
$cnt = (int)$summary['cnt'];


// ─── Category breakdown ────────────────────────────────────
$by_cat = db_all($conn, "
    SELECT c.name AS category, t.type, SUM(t.amount) AS total, COUNT(*) AS cnt
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
    GROUP BY c.id, c.name, t.type
    ORDER BY total DESC
", "iss", [$uid, $from, $to]);


// ─── Payment-method breakdown ──────────────────────────────
$by_pm = db_all($conn, "
    SELECT pm.name AS method,
           COALESCE(SUM(CASE WHEN t.type='income'  THEN t.amount END), 0) AS inc,
           COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount END), 0) AS exp,
           COUNT(*) AS cnt
    FROM transactions t
    LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
    WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
    GROUP BY pm.id, pm.name
    ORDER BY (COALESCE(SUM(CASE WHEN t.type='income'  THEN t.amount END), 0) +
              COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount END), 0)) DESC
", "iss", [$uid, $from, $to]);


// ─── Transaction list ──────────────────────────────────────
$txns = db_all($conn, "
    SELECT t.id, t.title, t.amount, t.type, t.txn_date,
           c.name  AS category_name,
           pm.name AS pm_name
    FROM transactions t
    LEFT JOIN categories      c  ON c.id  = t.category_id
    LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
    WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
    ORDER BY t.txn_date DESC, t.created_at DESC
", "iss", [$uid, $from, $to]);
?>

<!-- ─── Range selector ─── -->
<form method="GET" class="period-form">
    <label style="font-size:.78rem;color:var(--muted);align-self:center">From</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
           max="<?= date('Y-m-d') ?>" required>
    <label style="font-size:.78rem;color:var(--muted);align-self:center">To</label>
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>"
           max="<?= date('Y-m-d') ?>" required>
    <button type="submit" class="btn btn-primary">View</button>
    <a href="export_pdf.php?report=date_range&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
       class="btn btn-success btn-end">&#8595; PDF</a>
</form>


<!-- ─── Summary ─── -->
<div class="summary-grid">
    <div class="sum-card inc">
        <div class="sum-label">Income</div>
        <div class="sum-val"><?= fmt($inc) ?></div>
        <div class="sum-sub"><?= $range_label ?></div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Expenses</div>
        <div class="sum-val"><?= fmt($exp) ?></div>
        <div class="sum-sub"><?= $range_days ?> day(s)</div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">Net</div>
        <div class="sum-val sum-val-<?= $net >= 0 ? 'green' : 'red' ?>"><?= fmt($net) ?></div>
        <div class="sum-sub"><?= $cnt ?> transactions</div>
    </div>
</div>


<?php if ($cnt === 0): ?>
    <div class="card">
        <div class="empty">No transactions found in this date range.</div>
    </div>
<?php else: ?>

<div class="grid-2">

    <!-- Breakdown by category -->
    <div class="card">
        <div class="card-title">By Category</div>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr><th>Category</th><th>Type</th><th>Count</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($by_cat as $r): ?>
            <tr>
                <td class="td-name"><?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?></td>
                <td>
                    <span class="badge badge-<?= $r['type'] === 'income' ? 'inc' : 'exp' ?>">
                        <?= ucfirst($r['type']) ?>
                    </span>
                </td>
                <td class="td-muted"><?= $r['cnt'] ?></td>
                <td class="<?= $r['type'] === 'income' ? 'amt-inc' : 'amt-exp' ?>">
                    <?= fmt($r['total']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Breakdown by payment method -->
    <div class="card">
        <div class="card-title">By Payment Method</div>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr><th>Method</th><th>Income</th><th>Expense</th><th>Count</th></tr>
            </thead>
            <tbody>
            <?php foreach ($by_pm as $r): ?>
            <tr>
                <td class="td-name"><?= htmlspecialchars($r['method'] ?? 'Not specified') ?></td>
                <td class="amt-inc"><?= (float)$r['inc'] > 0 ? '+' . fmt($r['inc']) : '—' ?></td>
                <td class="amt-exp"><?= (float)$r['exp'] > 0 ? '-' . fmt($r['exp']) : '—' ?></td>
                <td class="td-muted"><?= $r['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>


<!-- Transactions in range -->
<div class="card" style="margin-top:18px">
    <div class="card-title">
        Transactions in Range
        <span class="card-meta"><?= $cnt ?> record(s)</span>
    </div>
    <div class="tbl-wrap">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Category</th>
                <th>Method</th>
                <th>Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($txns as $t): ?>
        <tr>
            <td class="td-muted td-mono"><?= date('d M Y', strtotime($t['txn_date'])) ?></td>
            <td class="td-name"><?= htmlspecialchars($t['title']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($t['category_name'] ?? '—') ?></td>
            <td class="td-muted"><?= htmlspecialchars($t['pm_name'] ?? '—') ?></td>
            <td>
                <span class="badge badge-<?= $t['type'] === 'income' ? 'inc' : 'exp' ?>">
                    <?= ucfirst($t['type']) ?>
                </span>
            </td>
            <td class="<?= $t['type'] === 'income' ? 'amt-inc' : 'amt-exp' ?>">
                <?= $t['type'] === 'income' ? '+' : '-' ?><?= fmt($t['amount']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
