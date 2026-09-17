<?php
// ============================================================
//  view_notifications.php  -  Notification Inbox
// ============================================================
//  Lists every notification belonging to the current user.
//  Unread alerts appear at the top in a prominent block;
//  read alerts appear below in a more compact form.
//
//  Each notification can be individually marked as read or
//  deleted. A "Mark all as read" button bulk-clears the
//  unread count without removing any data.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle bulk "mark all as read" ────────────────────────
// Done at the top of the file so it runs before any rendering.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    db_run($conn, "
        UPDATE notifications SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ", "i", [$uid]);
    header('Location: view_notifications.php?status=notif_marked');
    exit();
}


$section = 'Notifications';
$page    = 'view_notifications.php';
$title   = 'Notifications';
require_once 'includes/header.php';


// ─── Fetch notifications, split by read state ──────────────
$unread = db_all($conn, "
    SELECT id, type, title, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
", "i", [$uid]);

$read = db_all($conn, "
    SELECT id, type, title, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 1
    ORDER BY created_at DESC
    LIMIT 50
", "i", [$uid]);


// ─── Helper: pick a colour class based on notification type ─
// Defined inline because it is only used on this page.
function notif_class(string $type): string
{
    return [
        'budget_warning'  => 'warn',
        'budget_exceeded' => 'exp',
        'goal_milestone'  => 'inc',
        'goal_achieved'   => 'inc',
        'general'         => 'info',
    ][$type] ?? 'info';
}
?>

<!-- ─── Action bar ─── -->
<div class="action-bar">
    <span style="color:var(--muted);font-size:.82rem">
        <strong style="color:var(--text-strong)"><?= count($unread) ?></strong> unread,
        <strong style="color:var(--text-strong)"><?= count($read) ?></strong> read
    </span>
    <?php if (!empty($unread)): ?>
        <form method="POST" style="display:inline;margin-left:auto">
            <button type="submit" name="mark_all" value="1"
                    class="btn btn-primary">
                Mark all as read
            </button>
        </form>
    <?php endif; ?>
</div>


<!-- ─── Unread section ─── -->
<div class="card">
    <div class="card-title">
        Unread
        <?php if (!empty($unread)): ?>
            <span class="badge badge-exp"><?= count($unread) ?></span>
        <?php endif; ?>
    </div>

    <?php if (empty($unread)): ?>
        <div class="empty">All caught up. No new notifications.</div>
    <?php else: ?>
        <div class="notif-list">
        <?php foreach ($unread as $n):
            $cls = notif_class($n['type']);
        ?>
            <div class="notif-item notif-<?= $cls ?> unread">
                <div class="notif-body">
                    <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-time">
                        <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <a href="notification_mark_read.php?id=<?= (int)$n['id'] ?>"
                       class="btn btn-sm btn-ghost" title="Mark as read">
                        &check; Read
                    </a>
                    <a href="notification_delete.php?id=<?= (int)$n['id'] ?>"
                       class="btn btn-sm btn-danger" title="Delete"
                       onclick="return confirm('Delete this notification?')">
                        Delete
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>


<!-- ─── Read section ─── -->
<?php if (!empty($read)): ?>
<div class="card" style="margin-top:18px">
    <div class="card-title">Previously Read</div>
    <div class="notif-list">
    <?php foreach ($read as $n):
        $cls = notif_class($n['type']);
    ?>
        <div class="notif-item notif-<?= $cls ?>">
            <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-time">
                    <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                </div>
            </div>
            <div class="notif-actions">
                <a href="notification_delete.php?id=<?= (int)$n['id'] ?>"
                   class="btn btn-sm btn-danger" title="Delete"
                   onclick="return confirm('Delete this notification?')">
                    Delete
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>


<!-- Notification styling — local to this module -->
<style>
    .notif-list { display: flex; flex-direction: column; gap: 8px; }

    .notif-item {
        display: flex; align-items: flex-start;
        gap: 12px; padding: 14px 16px;
        background: var(--input-bg);
        border: 1px solid var(--border);
        border-left: 3px solid var(--muted);
        border-radius: var(--radius-sm);
        transition: border-color .2s, background .2s;
    }
    .notif-item.unread {
        background: var(--card);
        border-color: var(--border2);
    }
    .notif-item.notif-warn { border-left-color: var(--yellow); }
    .notif-item.notif-exp  { border-left-color: var(--red);    }
    .notif-item.notif-inc  { border-left-color: var(--green);  }
    .notif-item.notif-info { border-left-color: var(--blue);   }

    .notif-body { flex: 1; min-width: 0; }
    .notif-title {
        font-weight: 600; color: var(--text-strong);
        font-size: .88rem; margin-bottom: 4px;
        letter-spacing: -.2px;
    }
    .notif-msg {
        color: var(--text); font-size: .82rem;
        line-height: 1.5; margin-bottom: 4px;
    }
    .notif-time {
        font-family: 'DM Mono', monospace;
        font-size: .68rem; color: var(--dim);
    }
    .notif-actions {
        display: flex; flex-direction: column;
        gap: 6px; flex-shrink: 0;
    }

    @media (max-width: 600px) {
        .notif-item     { flex-direction: column; }
        .notif-actions  { flex-direction: row; width: 100%; }
        .notif-actions .btn { flex: 1; }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
