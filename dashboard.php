<?php
// ============================================================
//  dashboard.php  -  Authenticated Dashboard (Overview)
// ============================================================
//  Replaces the role of the previous index.php. The new
//  index.php is the public landing page; this page is what
//  signed-in users see first.
//
//  The page shows six summary cards, three Chart.js charts,
//  and a recent transactions table. Every database query is
//  filtered by the current user's ID.
// ============================================================

require_once 'includes/db.php';

$section = 'Dashboard';
$page    = 'dashboard.php';
$title   = 'Overview';
require_once 'includes/header.php';

$uid = current_user_id();


// ─── Summary metrics ───────────────────────────────────────
$total_income  = db_one($conn,
    "SELECT COALESCE(SUM(amount),0) AS t
     FROM transactions
     WHERE user_id = ? AND type = 'income'",
    "i", [$uid])['t'];

$total_expense = db_one($conn,
    "SELECT COALESCE(SUM(amount),0) AS t
     FROM transactions
     WHERE user_id = ? AND type = 'expense'",
    "i", [$uid])['t'];

$balance = $total_income - $total_expense;

$savings_rate = $total_income > 0
    ? round(($balance / $total_income) * 100, 1)
    : 0;

$expense_ratio = $total_income > 0
    ? round(($total_expense / $total_income) * 100, 1)
    : 0;

$txn_this_month = (int) db_one($conn, "
    SELECT COUNT(*) AS c
    FROM transactions
    WHERE user_id = ?
      AND MONTH(txn_date) = MONTH(CURDATE())
      AND YEAR(txn_date)  = YEAR(CURDATE())
", "i", [$uid])['c'];


// ─── Chart data: spending by category (current month) ──────
$cat_rows = db_all($conn, "
    SELECT c.name AS category,
           SUM(t.amount) AS total
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ?
      AND t.type = 'expense'
      AND MONTH(t.txn_date) = MONTH(CURDATE())
      AND YEAR(t.txn_date)  = YEAR(CURDATE())
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 8
", "i", [$uid]);


// ─── Chart data: monthly income vs expense (last 6 months) ─
$month_rows = db_all($conn, "
    SELECT
        DATE_FORMAT(txn_date, '%Y-%m') AS period,
        DATE_FORMAT(txn_date, '%b %Y') AS label,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
    FROM transactions
    WHERE user_id = ?
      AND txn_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY period, label
    ORDER BY period ASC
", "i", [$uid]);


// ─── Recent transactions (last 6) ──────────────────────────
$recent = db_all($conn, "
    SELECT t.id, t.title, t.amount, t.type, t.txn_date,
           c.name AS category_name
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ?
    ORDER BY t.txn_date DESC, t.created_at DESC
    LIMIT 6
", "i", [$uid]);


// ─── Build JSON payloads for the chart scripts ─────────────
// json_encode handles all escaping for safe inline use.
$chart_cat = [
    'labels' => array_map(fn($r) => $r['category'] ?? 'Uncategorized', $cat_rows),
    'data'   => array_map(fn($r) => (float)$r['total'],                $cat_rows),
];
$chart_month = [
    'labels'   => array_map(fn($r) => $r['label'],          $month_rows),
    'income'   => array_map(fn($r) => (float)$r['income'],  $month_rows),
    'expense'  => array_map(fn($r) => (float)$r['expense'], $month_rows),
    'savings'  => array_map(fn($r) => (float)$r['income'] - (float)$r['expense'], $month_rows),
];
?>

<!-- ─── Summary cards ─── -->
<div class="summary-grid">
    <div class="sum-card inc">
        <div class="sum-label">Total Income</div>
        <div class="sum-val"><?= fmt($total_income) ?></div>
        <div class="sum-sub">All time</div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Total Expenses</div>
        <div class="sum-val"><?= fmt($total_expense) ?></div>
        <div class="sum-sub">All time</div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">Net Balance</div>
        <div class="sum-val sum-val-<?= $balance >= 0 ? 'green' : 'red' ?>">
            <?= fmt($balance) ?>
        </div>
        <div class="sum-sub">Income &minus; Expenses</div>
    </div>
    <div class="sum-card sav">
        <div class="sum-label">Savings Rate</div>
        <div class="sum-val"><?= $savings_rate ?>%</div>
        <div class="sum-sub">Of total income</div>
    </div>
    <div class="sum-card inc">
        <div class="sum-label">This Month's Transactions</div>
        <div class="sum-val sum-val-blue"><?= $txn_this_month ?></div>
        <div class="sum-sub"><?= date('F Y') ?></div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Expense Ratio</div>
        <div class="sum-val sum-val-yellow"><?= $expense_ratio ?>%</div>
        <div class="sum-sub">Expenses &divide; Income</div>
    </div>
</div>


<!-- ─── Charts grid ─── -->
<div class="dash-charts">

    <div class="card">
        <div class="card-title">Spending by Category (This Month)</div>
        <?php if (empty($cat_rows)): ?>
            <div class="empty">No expense data yet for this month.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="catChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Monthly Income vs Expense</div>
        <?php if (empty($month_rows)): ?>
            <div class="empty">No transactions in the last 6 months.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="monthChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Savings Trend</div>
        <?php if (empty($month_rows)): ?>
            <div class="empty">No data to plot a trend yet.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="trendChart"></canvas></div>
        <?php endif; ?>
    </div>

</div>


<!-- ─── Recent transactions ─── -->
<div class="card">
    <div class="card-title">
        Recent Transactions
        <a href="view_transactions.php" class="btn btn-sm btn-primary">View All &rarr;</a>
    </div>
    <div class="tbl-wrap">
        <?php if (empty($recent)): ?>
            <div class="empty">
                No transactions yet.
                <a href="add_transaction.php">Add your first one</a>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($r['title']) ?></td>
                    <td class="td-muted"><?= htmlspecialchars($r['category_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-<?= $r['type'] === 'income' ? 'inc' : 'exp' ?>">
                            <?= ucfirst($r['type']) ?>
                        </span>
                    </td>
                    <td class="<?= $r['type'] === 'income' ? 'amt-inc' : 'amt-exp' ?>">
                        <?= $r['type'] === 'income' ? '+' : '-' ?><?= fmt($r['amount']) ?>
                    </td>
                    <td class="td-muted td-mono">
                        <?= date('d M Y', strtotime($r['txn_date'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>


<!-- ─── Chart.js + chart initialisation ─── -->
<!-- Chart.js loaded from CDN. The script is small and the
     CDN is reliable; for offline XAMPP use you can download
     chart.umd.min.js and reference it locally instead. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    /* Dashboard-only chart grid + box sizing.
       Kept here rather than in style.css so the dashboard
       can evolve independently of the global stylesheet. */
    .dash-charts {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 18px;
    }
    .chart-box {
        position: relative;
        height: 260px;
    }
</style>
<script>
(function () {
    // Theme-aware color palette pulled from the actual CSS
    // variables in use, so charts match the active theme.
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }
    var palette = {
        green:  cssVar('--green',  '#10b981'),
        red:    cssVar('--red',    '#f43f5e'),
        blue:   cssVar('--blue',   '#c9a227'),
        yellow: cssVar('--yellow', '#e8c96e'),
        muted:  cssVar('--muted',  '#5a7aa0'),
        border: cssVar('--border', '#1a3260'),
        text:   cssVar('--text',   '#c8d8f0')
    };

    // Distinct slice colors for the doughnut.
    // Gold-blue lux palette consistent with the theme.
    var donutColors = [
        '#c9a227', '#e8c96e', '#10b981', '#f43f5e',
        '#a78bfa', '#3b82f6', '#06b6d4', '#f59e0b'
    ];

    // Common options
    var commonAxis = {
        ticks: { color: palette.muted, font: { size: 10 } },
        grid:  { color: palette.border, drawBorder: false }
    };
    var commonLegend = {
        position: 'bottom',
        labels: { color: palette.text, font: { size: 11 }, padding: 14, boxWidth: 12 }
    };

    // ── Chart 1: Spending by Category (doughnut) ─────────
    var catEl = document.getElementById('catChart');
    if (catEl) {
        new Chart(catEl, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_cat['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chart_cat['data']) ?>,
                    backgroundColor: donutColors,
                    borderColor: cssVar('--card', '#0a1428'),
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: commonLegend,
                    tooltip: { callbacks: {
                        label: function (ctx) {
                            return ctx.label + ': \u20B9' +
                                ctx.parsed.toLocaleString('en-IN', {minimumFractionDigits: 2});
                        }
                    }}
                }
            }
        });
    }

    // ── Chart 2: Monthly Income vs Expense (bar) ─────────
    var monEl = document.getElementById('monthChart');
    if (monEl) {
        new Chart(monEl, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_month['labels']) ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?= json_encode($chart_month['income']) ?>,
                        backgroundColor: palette.green
                    },
                    {
                        label: 'Expense',
                        data: <?= json_encode($chart_month['expense']) ?>,
                        backgroundColor: palette.red
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: commonAxis, y: commonAxis },
                plugins: { legend: commonLegend }
            }
        });
    }

    // ── Chart 3: Savings Trend (line) ────────────────────
    var trEl = document.getElementById('trendChart');
    if (trEl) {
        new Chart(trEl, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_month['labels']) ?>,
                datasets: [{
                    label: 'Net Savings',
                    data: <?= json_encode($chart_month['savings']) ?>,
                    borderColor: palette.blue,
                    backgroundColor: 'rgba(201,162,39,.12)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: palette.blue,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: commonAxis, y: commonAxis },
                plugins: { legend: commonLegend }
            }
        });
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
