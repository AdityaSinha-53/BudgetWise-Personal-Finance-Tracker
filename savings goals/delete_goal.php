<?php
// ============================================================
//  delete_goal.php  -  Delete a Savings Goal
// ============================================================
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_goals.php');
    exit();
}

if (!user_owns($conn, 'savings_goals', $id)) {
    header('Location: manage_goals.php?status=unauthorized');
    exit();
}

db_run($conn, "DELETE FROM savings_goals WHERE id = ?", "i", [$id]);

header('Location: manage_goals.php?status=goal_deleted');
exit();
