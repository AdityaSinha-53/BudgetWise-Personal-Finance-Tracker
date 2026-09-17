<?php
// ============================================================
//  category_report.php  -  Spending by Category
// ============================================================
require_once 'includes/db.php';

$section = 'Reports';
$page    = 'category_report.php';
$title   = 'By Category';
require_once 'includes/header.php';

$uid = current_user_id();

// Audit log
log_report($conn, 'category');

// Toggle between 'income' and 'expense' view
$view = isset($_GET['view']) && $_GET['view'] === 'income' ? 'income' : 'expense';


// ─── Aggregate by category for the current user ────────────
$rows = db_all($conn, "
    SELECT c.id, c.name AS category,
           COUNT(t.id) AS txn_count,
           SUM(t.amount) AS total,
           MIN(t.amount) AS min_amt,
           MAX(t.amount) AS max_amt,
           AVG(t.amount) AS avg_amt
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = ?
    GROUP BY c.id, c.name
    ORDER BY total DESC
", "is", [$uid, $view]);


// ─── Aggregates ────────────────────────────────────────────
$grand_total = array_sum(array_column($rows, 'total')) ?: 1;
$max_value   = $rows ? $rows[0]['total'] : 1;

$chart = [
    'labels' => array_map(fn($r) => $r['category'] ?? 'Uncategorized', $rows),
    'data'   => array_map(fn($r) => (float)$r['total'],                $rows),
];
?>

<!-- Toggle + export -->
<div class="action-bar">
    <a href="?view=expense"
       class="btn <?= $view === 'expense' ? 'btn-primary' : 'btn-ghost' ?>">
        &#9660; Expenses
    </a>
    <a href="?view=income"
       class="btn <?= $view === 'income' ? 'btn-primary' : 'btn-ghost' ?>">
        &#9650; Income
    </a>
    <a href="export_csv.php?report=category&view=<?= $view ?>"
       class="btn btn-success btn-end">&#8595; CSV</a>
    <a href="export_excel.php?report=category&view=<?= $view ?>"
       class="btn btn-success">&#8595; Excel</a>
    <a href="export_pdf.php?report=category&view=<?= $view ?>"
       class="btn btn-success">&#8595; PDF</a>
</div>


<!-- Summary -->
<div class="summary-grid">
    <div class="sum-card <?= $view === 'income' ? 'inc' : 'exp' ?>">
        <div class="sum-label">Total <?= ucfirst($view) ?></div>
        <div class="sum-val"><?= fmt($grand_total) ?></div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">Categories</div>
        <div class="sum-val sum-val-neutral"><?= count($rows) ?></div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">Total Transactions</div>
        <div class="sum-val sum-val-neutral">
            <?= array_sum(array_column($rows, 'txn_count')) ?>
        </div>
    </div>
</div>


<div class="grid-2">
    <!-- LEFT: Chart + horizontal bars -->
    <div class="card">
        <div class="card-title">Breakdown by Category</div>

        <?php if (empty($rows)): ?>
            <div class="empty">No <?= htmlspecialchars($view) ?> records found.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="catChart"></canvas></div>

            <div style="margin-top:18px">
            <?php foreach ($rows as $r):
                $pct   = round(($r['total'] / $max_value)   * 100);
                $share = round(($r['total'] / $grand_total) * 100, 1);
            ?>
            <div class="bar-row">
                <div class="bar-lbl" title="<?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?>">
                    <?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?>
                </div>
                <div class="bar-track">
                    <div class="bar-fill <?= $view === 'income' ? 'inc-bar' : 'exp-bar' ?>"
                         style="width:<?= $pct ?>%"></div>
                </div>
                <div class="bar-val">
                    <?= fmt($r['total']) ?>
                    <span class="bar-share">(<?= $share ?>%)</span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Statistics -->
    <div class="card">
        <div class="card-title">Statistics per Category</div>
        <?php if (empty($rows)): ?>
            <div class="empty">No data.</div>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Total</th>
                    <th>Avg</th>
                    <th>Highest</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $share = round(($r['total'] / $grand_total) * 100, 1);
            ?>
            <tr>
                <td class="td-name"><?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?></td>
                <td class="td-muted"><?= $r['txn_count'] ?></td>
                <td class="<?= $view === 'income' ? 'amt-inc' : 'amt-exp' ?>">
                    <?= fmt($r['total']) ?>
                </td>
                <td class="td-muted td-mono"><?= fmt($r['avg_amt']) ?></td>
                <td class="td-muted td-mono"><?= fmt($r['max_amt']) ?></td>
                <td><span class="badge badge-info"><?= $share ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php if (!empty($rows)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .chart-box { position: relative; height: 220px; margin-bottom: 16px; }
</style>
<script>
(function () {
    function cssVar(n, f) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
        return v || f;
    }
    var colors = [
        '#c9a227', '#e8c96e', '#10b981', '#f43f5e',
        '#a78bfa', '#3b82f6', '#06b6d4', '#f59e0b',
        '#84cc16', '#ec4899'
    ];
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chart['labels']) ?>,
            datasets: [{
                data: <?= json_encode($chart['data']) ?>,
                backgroundColor: colors,
                borderColor: cssVar('--card', '#0a1428'),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { position: 'right',
                          labels: { color: cssVar('--text', '#c8d8f0'),
                                    font: { size: 10 }, boxWidth: 10, padding: 8 }},
                tooltip: { callbacks: {
                    label: function (ctx) {
                        return ctx.label + ': \u20B9' +
                            ctx.parsed.toLocaleString('en-IN', {minimumFractionDigits: 2});
                    }
                }}
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
