<?php
// ============================================================
//  payment_method_delete.php  -  Remove a Payment Method
// ============================================================
//  Defaults (is_default = 1) cannot be deleted — user_owns()
//  refuses rows with NULL user_id.
//
//  ON DELETE SET NULL on transactions.payment_method_id means
//  affected transactions keep their data and simply lose
//  their method tag.
// ============================================================

require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: payment_methods.php');
    exit();
}

if (!user_owns($conn, 'payment_methods', $id)) {
    header('Location: payment_methods.php?status=unauthorized');
    exit();
}

// Refuse to delete defaults defensively
$row = db_one($conn,
    "SELECT is_default FROM payment_methods WHERE id = ?",
    "i", [$id]
);
if (!$row || (int)$row['is_default'] === 1) {
    header('Location: payment_methods.php?status=not_found');
    exit();
}

db_run($conn, "DELETE FROM payment_methods WHERE id = ?", "i", [$id]);

header('Location: payment_methods.php?status=pm_deleted');
exit();
