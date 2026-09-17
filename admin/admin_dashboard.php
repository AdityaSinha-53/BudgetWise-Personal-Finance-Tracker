<?php
// ============================================================
//  admin_dashboard.php  -  Admin Overview
// ============================================================
//  Platform-wide aggregate statistics. Only administrators
//  can reach this page — require_admin() at the top of the
//  file redirects non-admins to the regular dashboard.
//
//  Every query here intentionally aggregates across users
//  rather than filtering by user_id, because that is the
//  whole point of administrative oversight. Regular reports
//  remain user-scoped throughout the rest of the app.
// ============================================================

require_once 'includes/db.php';
require_admin();

$section = 'Admin';
$page    = 'admin_dashboard.php';
$title   = 'Admin Dashboard';
require_once 'includes/header.php';


// ─── Platform-wide statistics ──────────────────────────────
$users_total    = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM users")['c'];
$users_active   = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM users WHERE is_active = 1")['c'];
$users_inactive = $users_total - $users_active;
$users_admin    = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")['c'];

$txn_stats = db_one($conn, "
    SELECT COUNT(*) AS cnt,
           COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS inc,
           COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS exp
    FROM transactions
");

$total_budgets = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM budgets")['c'];
$total_goals = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM savings_goals")['c'];

$audit_size = db_one($conn, "
    SELECT
        (SELECT COUNT(*) FROM admin_logs)    AS admin_log_count,
        (SELECT COUNT(*) FROM report_logs)   AS report_log_count,
        (SELECT COUNT(*) FROM notifications) AS notif_count
");


// ─── Activity for last 14 days (chart) ─────────────────────
$activity = db_all($conn, "
    SELECT DATE(txn_date) AS day,
           COUNT(*)       AS cnt
    FROM transactions
    WHERE txn_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day ASC
");


// ─── Top 8 categories across the platform ──────────────────
$top_cats = db_all($conn, "
    SELECT c.name AS category, SUM(t.amount) AS total
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.type = 'expense'
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 8
");


// ─── Recently registered users (last 5) ────────────────────
$recent_users = db_all($conn, "
    SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at,
           p.name
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY u.created_at DESC
    LIMIT 5
");


// ─── Chart payloads ────────────────────────────────────────
$chart_status = [
    'labels' => ['Active', 'Inactive'],
    'data'   => [$users_active, $users_inactive],
];
$chart_activity = [
    'labels' => array_map(fn($r) => date('d M', strtotime($r['day'])), $activity),
    'data'   => array_map(fn($r) => (int)$r['cnt'], $activity),
];
$chart_cats = [
    'labels' => array_map(fn($r) => $r['category'] ?? 'Uncategorized', $top_cats),
    'data'   => array_map(fn($r) => (float)$r['total'], $top_cats),
];
?>

<!-- ─── Summary cards ─── -->
<div class="summary-grid">
    <div class="sum-card bal">
        <div class="sum-label">Total Users</div>
        <div class="sum-val sum-val-blue"><?= $users_total ?></div>
        <div class="sum-sub"><?= $users_active ?> active &middot; <?= $users_admin ?> admin</div>
    </div>
    <div class="sum-card inc">
        <div class="sum-label">Platform Income</div>
        <div class="sum-val"><?= fmt($txn_stats['inc']) ?></div>
        <div class="sum-sub">All-time across all users</div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Platform Expenses</div>
        <div class="sum-val"><?= fmt($txn_stats['exp']) ?></div>
        <div class="sum-sub">All-time across all users</div>
    </div>
    <div class="sum-card sav">
        <div class="sum-label">Total Transactions</div>
        <div class="sum-val sum-val-yellow"><?= (int)$txn_stats['cnt'] ?></div>
        <div class="sum-sub">Across all users</div>
    </div>
    <div class="sum-card inc">
        <div class="sum-label">Budgets</div>
        <div class="sum-val sum-val-green"><?= $total_budgets ?></div>
        <div class="sum-sub">Configured</div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Savings Goals</div>
        <div class="sum-val sum-val-red"><?= $total_goals ?></div>
        <div class="sum-sub">Total across users</div>
    </div>
</div>


<!-- ─── Charts row ─── -->
<div class="dash-charts">

    <div class="card">
        <div class="card-title">User Status</div>
        <?php if ($users_total > 0): ?>
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        <?php else: ?>
            <div class="empty">No users yet.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Activity (last 14 days)</div>
        <?php if (empty($activity)): ?>
            <div class="empty">No activity in the last 14 days.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="activityChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Top Expense Categories</div>
        <?php if (empty($top_cats)): ?>
            <div class="empty">No expenses recorded yet.</div>
        <?php else: ?>
            <div class="chart-box"><canvas id="catChart"></canvas></div>
        <?php endif; ?>
    </div>

</div>


<!-- ─── Recently registered users ─── -->
<div class="card">
    <div class="card-title">
        Recent Registrations
        <a href="manage_users.php" class="btn btn-sm btn-primary">Manage Users &rarr;</a>
    </div>
    <div class="tbl-wrap">
        <?php if (empty($recent_users)): ?>
            <div class="empty">No users yet.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $u): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($u['name'] ?? '—') ?></td>
                    <td class="td-muted"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-warn' : 'badge-info' ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= (int)$u['is_active'] === 1 ? 'badge-inc' : 'badge-exp' ?>">
                            <?= (int)$u['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="td-muted td-mono">
                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>


<!-- ─── Audit table summary ─── -->
<div class="card">
    <div class="card-title">
        Audit Trail
        <a href="system_logs.php" class="btn btn-sm btn-primary">View Logs &rarr;</a>
    </div>
    <dl class="info-list">
        <dt>Admin Actions Logged</dt>
        <dd class="info-val info-neutral"><?= (int)$audit_size['admin_log_count'] ?></dd>
        <dt>Report Generations Logged</dt>
        <dd class="info-val info-neutral"><?= (int)$audit_size['report_log_count'] ?></dd>
        <dt>Notifications Issued</dt>
        <dd class="info-val info-neutral"><?= (int)$audit_size['notif_count'] ?></dd>
    </dl>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .dash-charts {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 18px;
    }
    .chart-box { position: relative; height: 240px; }
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
    var legend = {
        position: 'bottom',
        labels: { color: p.text, font: { size: 11 }, padding: 14, boxWidth: 12 }
    };

    // Status doughnut
    var sEl = document.getElementById('statusChart');
    if (sEl) {
        new Chart(sEl, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_status['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chart_status['data']) ?>,
                    backgroundColor: [p.green, p.red],
                    borderColor: cssVar('--card', '#0a1428'),
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: { legend: legend }
            }
        });
    }

    // Activity bars
    var aEl = document.getElementById('activityChart');
    if (aEl) {
        new Chart(aEl, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_activity['labels']) ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?= json_encode($chart_activity['data']) ?>,
                    backgroundColor: p.blue
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { x: ax, y: ax },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Top categories
    var cEl = document.getElementById('catChart');
    if (cEl) {
        var colors = ['#c9a227','#e8c96e','#10b981','#f43f5e',
                      '#a78bfa','#3b82f6','#06b6d4','#f59e0b'];
        new Chart(cEl, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_cats['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chart_cats['data']) ?>,
                    backgroundColor: colors,
                    borderColor: cssVar('--card', '#0a1428'),
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '55%',
                plugins: {
                    legend: { position: 'right',
                              labels: { color: p.text, font: { size: 10 },
                                        boxWidth: 10, padding: 6 }}
                }
            }
        });
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
