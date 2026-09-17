<?php
// ============================================================
//  notification_mark_read.php  -  Mark One Notification Read
// ============================================================
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: view_notifications.php');
    exit();
}

if (!user_owns($conn, 'notifications', $id)) {
    header('Location: view_notifications.php?status=unauthorized');
    exit();
}

db_run($conn,
    "UPDATE notifications SET is_read = 1 WHERE id = ?",
    "i", [$id]
);

header('Location: view_notifications.php?status=notif_marked');
exit();
