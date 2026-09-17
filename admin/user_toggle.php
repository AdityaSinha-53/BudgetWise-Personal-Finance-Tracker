<?php
// ============================================================
//  user_toggle.php  -  Activate or Deactivate a User
// ============================================================
//  Admin-only handler. Validates the target ID, blocks self-
//  deactivation, performs the flip, and logs the action to
//  the admin_logs audit table.
// ============================================================

require_once 'includes/db.php';
require_admin();

$target_id = (int)($_GET['id'] ?? 0);
$action    = $_GET['action']    ?? '';

if ($target_id <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
    header('Location: manage_users.php?status=validation');
    exit();
}

// Block self-deactivation defensively. The UI doesn't render
// the button, but a hand-crafted URL would otherwise succeed.
if ($target_id === current_user_id()) {
    header('Location: manage_users.php?status=unauthorized');
    exit();
}

// Verify target user exists
$target = db_one($conn,
    "SELECT id, username, is_active FROM users WHERE id = ?",
    "i", [$target_id]
);
if (!$target) {
    header('Location: manage_users.php?status=not_found');
    exit();
}

// Perform the toggle
$new_active = $action === 'activate' ? 1 : 0;
db_run($conn,
    "UPDATE users SET is_active = ? WHERE id = ?",
    "ii", [$new_active, $target_id]
);

// Log the action
$desc = "{$action}d user '{$target['username']}' (id {$target_id})";
log_admin_action($conn, "user_{$action}d", $target_id, $desc);

header('Location: manage_users.php?status=' .
       ($action === 'activate' ? 'user_activated' : 'user_deactivated'));
exit();
