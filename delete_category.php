<?php
// ============================================================
//  delete_category.php  -  Delete a Custom Category
// ============================================================
//  Defaults (is_default = 1) cannot be deleted because
//  user_owns() refuses rows with NULL user_id.
//
//  ON DELETE SET NULL on transactions.category_id means
//  affected transactions keep their data and simply lose
//  their category tag.
// ============================================================

require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_categories.php');
    exit();
}

if (!user_owns($conn, 'categories', $id)) {
    header('Location: manage_categories.php?status=unauthorized');
    exit();
}

// Refuse to delete defaults defensively, even if somehow a
// stray default's user_id got attributed to this user.
$row = db_one($conn,
    "SELECT is_default FROM categories WHERE id = ?",
    "i", [$id]
);
if (!$row || (int)$row['is_default'] === 1) {
    header('Location: manage_categories.php?status=not_found');
    exit();
}

db_run($conn, "DELETE FROM categories WHERE id = ?", "i", [$id]);

header('Location: manage_categories.php?status=cat_deleted');
exit();
