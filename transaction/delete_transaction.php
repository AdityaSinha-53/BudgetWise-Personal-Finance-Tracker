<?php
// ============================================================
//  delete_transaction.php  -  Delete a Transaction
// ============================================================
//  No UI here. A delete link in view_transactions.php sends
//  the user here with ?id=N, this file removes any attached
//  receipt files, deletes the row, then redirects back to
//  the list.
// ============================================================

require_once 'includes/db.php';
require_once 'includes/uploads.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: view_transactions.php');
    exit();
}

// IDOR defence — confirm the record belongs to the user.
// Admin users bypass this check (they can delete anything).
if (!user_owns($conn, 'transactions', $id)) {
    header('Location: view_transactions.php?status=unauthorized');
    exit();
}

// Remove the physical files BEFORE deleting the transaction.
// The foreign-key cascade will remove the attachments rows,
// but disk files would otherwise become orphaned.
remove_receipt_files($conn, $id);

// Now delete the transaction. ON DELETE CASCADE in the
// attachments table cleans up the metadata rows.
db_run($conn, "DELETE FROM transactions WHERE id = ?", "i", [$id]);

header('Location: view_transactions.php?status=deleted');
exit();
