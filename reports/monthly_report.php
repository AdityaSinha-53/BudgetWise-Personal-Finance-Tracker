<?php
// ============================================================
//  monthly_report.php  -  Monthly Income vs Expense Report
// ============================================================
require_once 'includes/db.php';

$section = 'Reports';
$page    = 'monthly_report.php';
$title   = 'Monthly Report';
require_once 'includes/header.php';

$uid = current_user_id();

// Audit log — record that this report was viewed
log_report($conn, 'monthly');


// ─── Aggregate by month for the current user ───────────────
$rows = db_all($conn, "
    SELECT
        DATE_FORMAT(txn_date, '%Y-%m') AS period,
        DATE_FORMAT(txn_date, '%b %Y') AS label,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense,
        COUNT(*) AS txn_count
    FROM transactions
    WHERE user_id = ?
    GROUP BY period, label
    ORDER BY period DESC
", "i", [$uid]);


// ─── Aggregates ────────────────────────────────────────────
$grand_income  = array_sum(array_column($rows, 'income'));
$grand_expense = array_sum(array_column($rows, 'expense'));
$grand_balance = $grand_income - $grand_expense;

$max_value = max(
    max(array_column($rows, 'income')  ?: [1]),
    max(array_column($rows, 'expense') ?: [1]),
    1
);


// ─── Chart payload (reversed so chart reads left-to-right oldest-to-newest) ─
$chart_rows = array_reverse($rows);
$chart = [
    'labels'   => array_map(fn($r) => $r['label'],          $chart_rows),
    'income'   => array_map(fn($r) => (float)$r['income'],  $chart_rows),
    'expense'  => array_map(fn($r) => (float)$r['expense'], $chart_rows),
    'savings'  => array_map(fn($r) => (float)$r['income'] - (float)$r['expense'], $chart_rows),
];
?>

<!-- ─── Summary ─── -->
<div class="summary-grid">
    <div class="sum-card inc">
        <div class="sum-label">All-Time Income</div>
        <div class="sum-val"><?= fmt($grand_income) ?></div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">All-Time Expenses</div>
        <div class="sum-val"><?= fmt($grand_expense) ?></div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">All-Time Balance</div>
        <div class="sum-val sum-val-<?= $grand_balance >= 0 ? 'green' : 'red' ?>">
            <?= fmt($grand_balance) ?>
        </div>
    </div>
</div>

<!-- ─── Action bar ─── -->
<div class="action-bar">
    <a href="export_csv.php?report=monthly"   class="btn btn-success">&#8595; CSV</a>
    <a href="export_excel.php?report=monthly" class="btn btn-success">&#8595; Excel</a>
    <a href="export_pdf.php?report=monthly"   class="btn btn-success">&#8595; PDF</a>
    <a href="category_report.php"     class="btn btn-primary btn-end">By Category &rarr;</a>
    <a href="date_range_report.php"   class="btn btn-ghost">Custom Range &rarr;</a>
</div>


<!-- ─── Trend chart ─── -->
<?php if (!empty($rows)): ?>
<div class="card">
    <div class="card-title">Monthly Trend</div>
    <div class="chart-box"><canvas id="trendChart"></canvas></div>
</div>
<?php endif; ?>


<!-- ─── Monthly table ─── -->
<div class="card">
    <div class="card-title">Month-by-Month</div>
    <?php if (empty($rows)): ?>
        <div class="empty">No transactions recorded yet.</div>
    <?php else: ?>
    <div class="tbl-wrap">
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Transactions</th>
                <th>Income</th>
                <th>Expenses</th>
                <th>Net Savings</th>
                <th>Rate</th>
                <th class="th-mid">Income vs Expense</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $sav     = $r['income'] - $r['expense'];
            $rate    = $r['income'] > 0 ? round(($sav / $r['income']) * 100, 1) : 0;
            $inc_pct = round($r['income']  / $max_value * 100);
            $exp_pct = round($r['expense'] / $max_value * 100);
        ?>
        <tr>
            <td class="td-name"><?= htmlspecialchars($r['label']) ?></td>
            <td class="td-muted"><?= $r['txn_count'] ?></td>
            <td class="amt-inc">+<?= fmt($r['income']) ?></td>
            <td class="amt-exp">-<?= fmt($r['expense']) ?></td>
            <td class="<?= $sav >= 0 ? 'amt-inc' : 'amt-exp' ?>"><?= fmt($sav) ?></td>
            <td>
                <span class="badge <?= $rate >= 30 ? 'badge-inc' : ($rate >= 0 ? 'badge-info' : 'badge-exp') ?>">
                    <?= $rate ?>%
                </span>
            </td>
            <td>
                <div class="mini-bars">
                    <div class="mini-bar-row">
                        <span class="mini-bar-label text-green">I</span>
                        <div class="bar-track bar-track-thin">
                            <div class="bar-fill inc-bar" style="width:<?= $inc_pct ?>%"></div>
                        </div>
                    </div>
                    <div class="mini-bar-row">
                        <span class="mini-bar-label text-red">E</span>
                        <div class="bar-track bar-track-thin">
                            <div class="bar-fill exp-bar" style="width:<?= $exp_pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>


<?php if (!empty($rows)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .chart-box { position: relative; height: 280px; }
</style>
<script>
(function () {
    function cssVar(n, f) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
        return v || f;
    }
    var p = {
        green:  cssVar('--green',  '#10b981'),
        red:    cssVar('--red',    '#f43f5e'),
        blue:   cssVar('--blue',   '#c9a227'),
        muted:  cssVar('--muted',  '#5a7aa0'),
        border: cssVar('--border', '#1a3260'),
        text:   cssVar('--text',   '#c8d8f0')
    };
    var ax = {
        ticks: { color: p.muted, font: { size: 10 } },
        grid:  { color: p.border, drawBorder: false }
    };

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chart['labels']) ?>,
            datasets: [
                { label: 'Income',      data: <?= json_encode($chart['income'])  ?>,
                  borderColor: p.green, backgroundColor: 'rgba(16,185,129,.12)',
                  borderWidth: 2, pointRadius: 3, tension: 0.3 },
                { label: 'Expenses',    data: <?= json_encode($chart['expense']) ?>,
                  borderColor: p.red,   backgroundColor: 'rgba(244,63,94,.12)',
                  borderWidth: 2, pointRadius: 3, tension: 0.3 },
                { label: 'Net Savings', data: <?= json_encode($chart['savings']) ?>,
                  borderColor: p.blue,  backgroundColor: 'rgba(201,162,39,.12)',
                  borderWidth: 2, pointRadius: 3, tension: 0.3, fill: true }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { x: ax, y: ax },
            plugins: {
                legend: { position: 'bottom',
                          labels: { color: p.text, font: { size: 11 }, padding: 14, boxWidth: 12 }}
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
