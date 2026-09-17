<?php
// ============================================================
//  system_logs.php  -  Audit Log Viewer
// ============================================================
//  Read-only display of the three audit-style tables:
//      - admin_logs    (administrative actions)
//      - report_logs   (report generation events)
//      - notifications (system-issued alerts)
//
//  Logs are immutable from the UI. To purge old logs, an
//  administrator would use phpMyAdmin directly; this is by
//  design — accidental deletion of audit data through the
//  UI would defeat the point of having an audit trail.
// ============================================================

require_once 'includes/db.php';
require_admin();

$section = 'Admin';
$page    = 'system_logs.php';
$title   = 'System Logs';
require_once 'includes/header.php';


// ─── Read the requested tab ─────────────────────────────────
$tab = $_GET['tab'] ?? 'admin';
if (!in_array($tab, ['admin', 'reports', 'notifications'], true)) {
    $tab = 'admin';
}


// ─── Fetch the data for the active tab ─────────────────────
$admin_rows = [];
$report_rows = [];
$notif_rows = [];

if ($tab === 'admin') {
    $admin_rows = db_all($conn, "
        SELECT a.id, a.action, a.description, a.ip_address, a.created_at,
               u.username AS admin_username,
               tu.username AS target_username
        FROM admin_logs a
        LEFT JOIN users u  ON u.id  = a.admin_id
        LEFT JOIN users tu ON tu.id = a.target_user_id
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
} elseif ($tab === 'reports') {
    $report_rows = db_all($conn, "
        SELECT r.id, r.report_type, r.period_from, r.period_to, r.generated_at,
               u.username
        FROM report_logs r
        LEFT JOIN users u ON u.id = r.user_id
        ORDER BY r.generated_at DESC
        LIMIT 50
    ");
} else {
    $notif_rows = db_all($conn, "
        SELECT n.id, n.type, n.title, n.is_read, n.created_at,
               u.username
        FROM notifications n
        LEFT JOIN users u ON u.id = n.user_id
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
}


// ─── Summary counts ─────────────────────────────────────────
$counts = db_one($conn, "
    SELECT
        (SELECT COUNT(*) FROM admin_logs)    AS admin_count,
        (SELECT COUNT(*) FROM report_logs)   AS report_count,
        (SELECT COUNT(*) FROM notifications) AS notif_count
");
?>

<!-- Tab navigation -->
<div class="action-bar">
    <a href="?tab=admin"
       class="btn <?= $tab === 'admin' ? 'btn-primary' : 'btn-ghost' ?>">
        Admin Actions
        <span class="badge badge-info" style="margin-left:6px"><?= (int)$counts['admin_count'] ?></span>
    </a>
    <a href="?tab=reports"
       class="btn <?= $tab === 'reports' ? 'btn-primary' : 'btn-ghost' ?>">
        Report Generations
        <span class="badge badge-info" style="margin-left:6px"><?= (int)$counts['report_count'] ?></span>
    </a>
    <a href="?tab=notifications"
       class="btn <?= $tab === 'notifications' ? 'btn-primary' : 'btn-ghost' ?>">
        Notifications
        <span class="badge badge-info" style="margin-left:6px"><?= (int)$counts['notif_count'] ?></span>
    </a>
</div>


<!-- Active tab content -->
<div class="card">
    <div class="card-title">
        <?= $tab === 'admin' ? 'Administrative Actions'
            : ($tab === 'reports' ? 'Report Generation Events' : 'Notifications Issued') ?>
        <span class="card-meta">Showing most recent 50</span>
    </div>


    <?php if ($tab === 'admin'): ?>
        <?php if (empty($admin_rows)): ?>
            <div class="empty">No administrative actions logged yet.</div>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target User</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admin_rows as $r): ?>
                <tr>
                    <td class="td-muted td-mono">
                        <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
                    </td>
                    <td class="td-name">
                        <?= htmlspecialchars($r['admin_username'] ?? '—') ?>
                    </td>
                    <td>
                        <span class="badge badge-warn">
                            <?= htmlspecialchars($r['action']) ?>
                        </span>
                    </td>
                    <td class="td-muted">
                        <?= htmlspecialchars($r['target_username'] ?? '—') ?>
                    </td>
                    <td class="td-muted"><?= htmlspecialchars($r['description'] ?? '—') ?></td>
                    <td class="td-muted td-mono"><?= htmlspecialchars($r['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>


    <?php elseif ($tab === 'reports'): ?>
        <?php if (empty($report_rows)): ?>
            <div class="empty">No report generations logged yet.</div>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Report Type</th>
                    <th>Period From</th>
                    <th>Period To</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report_rows as $r): ?>
                <tr>
                    <td class="td-muted td-mono">
                        <?= date('d M Y H:i', strtotime($r['generated_at'])) ?>
                    </td>
                    <td class="td-name">
                        <?= htmlspecialchars($r['username'] ?? '—') ?>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= htmlspecialchars($r['report_type']) ?>
                        </span>
                    </td>
                    <td class="td-muted td-mono">
                        <?= $r['period_from'] ? date('d M Y', strtotime($r['period_from'])) : '—' ?>
                    </td>
                    <td class="td-muted td-mono">
                        <?= $r['period_to']   ? date('d M Y', strtotime($r['period_to']))   : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>


    <?php else: /* notifications */ ?>
        <?php if (empty($notif_rows)): ?>
            <div class="empty">No notifications have been issued yet.</div>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Read</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($notif_rows as $r): ?>
                <tr>
                    <td class="td-muted td-mono">
                        <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
                    </td>
                    <td class="td-name">
                        <?= htmlspecialchars($r['username'] ?? '—') ?>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= htmlspecialchars($r['type']) ?>
                        </span>
                    </td>
                    <td class="td-muted"><?= htmlspecialchars($r['title']) ?></td>
                    <td>
                        <span class="badge <?= (int)$r['is_read'] === 1 ? 'badge-inc' : 'badge-warn' ?>">
                            <?= (int)$r['is_read'] === 1 ? 'Read' : 'Unread' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>


<!-- Note about log management -->
<div class="card" style="margin-top:18px">
    <div class="card-title">About These Logs</div>
    <p style="font-size:.85rem;color:var(--text);margin-bottom:14px">
        Logs are immutable from this interface. They cannot be edited or
        deleted through the application UI, which preserves the integrity
        of the audit trail. To purge old logs &mdash; for example, after
        a year of accumulated data &mdash; an administrator should use
        phpMyAdmin directly.
    </p>
    <p style="font-size:.85rem;color:var(--muted)">
        The three tables shown here serve different purposes.
        <strong>admin_logs</strong> records administrative actions for accountability.
        <strong>report_logs</strong> records analytics activity for usage measurement.
        <strong>notifications</strong> records system alerts and user interaction with them.
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>
