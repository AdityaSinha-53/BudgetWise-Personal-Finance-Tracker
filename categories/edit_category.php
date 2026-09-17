<?php
// ============================================================
//  edit_category.php  -  Rename or Re-type a Custom Category
// ============================================================
//  System defaults (is_default = 1) cannot be edited and are
//  not reachable from manage_categories.php. user_owns() also
//  refuses defaults because they have user_id NULL.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: manage_categories.php');
    exit();
}

// Ownership defence — block editing other users' categories
// and block editing system defaults (which have user_id NULL).
if (!user_owns($conn, 'categories', $id)) {
    header('Location: manage_categories.php?status=unauthorized');
    exit();
}

$row = db_one($conn,
    "SELECT id, name, type, is_default, user_id FROM categories WHERE id = ?",
    "i", [$id]
);

if (!$row || (int)$row['is_default'] === 1) {
    header('Location: manage_categories.php?status=not_found');
    exit();
}


// ─── Handle save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type']      ?? '';

    $is_valid = $name !== ''
             && strlen($name) <= 50
             && in_array($type, ['income', 'expense'], true);

    if ($is_valid) {
        // Reject duplicates: another category with the same
        // name and type visible to this user.
        $dup = db_one($conn, "
            SELECT id FROM categories
            WHERE id != ?
              AND name = ? AND type = ?
              AND (is_default = 1 OR user_id = ?)
        ", "issi", [$id, $name, $type, $uid]);

        if (!$dup) {
            db_run($conn, "
                UPDATE categories
                SET name = ?, type = ?
                WHERE id = ?
            ", "ssi", [$name, $type, $id]);

            header('Location: manage_categories.php?status=cat_added');
            exit();
        }
    }
    header('Location: edit_category.php?id=' . $id . '&status=validation');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Categories';
$page    = 'manage_categories.php';
$title   = 'Edit Category';
require_once 'includes/header.php';

// Count how many transactions use this category — show the
// user the impact of changing its type.
$txn_count = (int) db_one($conn,
    "SELECT COUNT(*) AS c FROM transactions WHERE category_id = ?",
    "i", [$id])['c'];
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Edit Category #<?= $id ?></div>
        <form method="POST">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name"
                       value="<?= htmlspecialchars($row['name']) ?>"
                       required maxlength="50">
            </div>

            <div class="form-group">
                <label>Type *</label>
                <div class="type-toggle">
                    <input type="radio" id="t-income"  name="type" value="income"
                           <?= $row['type'] === 'income'  ? 'checked' : '' ?>>
                    <label for="t-income">&#9650; Income</label>
                    <input type="radio" id="t-expense" name="type" value="expense"
                           <?= $row['type'] === 'expense' ? 'checked' : '' ?>>
                    <label for="t-expense">&#9660; Expense</label>
                </div>
            </div>

            <?php if ($txn_count > 0): ?>
                <p class="form-hint" style="color:var(--yellow)">
                    Note: <?= $txn_count ?> transaction(s) currently use this category.
                    Renaming is safe; changing the type will affect how those
                    transactions are reported.
                </p>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                <a href="manage_categories.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Usage</div>
        <dl class="info-list">
            <dt>Transactions using this</dt>
            <dd class="info-val info-neutral"><?= $txn_count ?></dd>
            <dt>Current type</dt>
            <dd class="info-val info-<?= $row['type'] === 'income' ? 'green' : 'red' ?>">
                <?= ucfirst($row['type']) ?>
            </dd>
        </dl>
        <p class="form-hint" style="margin-top:14px">
            If you delete this category, any transactions tagged with it
            will keep their data but show no category until you re-tag them.
        </p>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
