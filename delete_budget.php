<?php
// ============================================================
//  delete_budget.php  -  Remove a Budget
// ============================================================
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_budgets.php');
    exit();
}

if (!user_owns($conn, 'budgets', $id)) {
    header('Location: manage_budgets.php?status=unauthorized');
    exit();
}

// Capture the period so we redirect back to the same view
$row = db_one($conn,
    "SELECT period_month, period_year FROM budgets WHERE id = ?",
    "i", [$id]
);

db_run($conn, "DELETE FROM budgets WHERE id = ?", "i", [$id]);

$m = $row ? (int)$row['period_month'] : (int)date('n');
$y = $row ? (int)$row['period_year']  : (int)date('Y');

header("Location: manage_budgets.php?month={$m}&year={$y}&status=budget_deleted");
exit();
