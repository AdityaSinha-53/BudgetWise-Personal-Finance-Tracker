<?php
// ============================================================
//  manage_categories.php  -  Category Management
// ============================================================
//  A single page combining:
//     1. Add form     (creates a new custom category)
//     2. Custom list  (user's own categories — editable)
//     3. Defaults     (system categories — read-only)
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle add submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type']      ?? '';

    $is_valid = $name !== ''
             && strlen($name) <= 50
             && in_array($type, ['income', 'expense'], true);

    if ($is_valid) {
        // Reject duplicates: a category with the same name and
        // type already visible to this user (default or own).
        $dup = db_one($conn, "
            SELECT id FROM categories
            WHERE name = ? AND type = ?
              AND (is_default = 1 OR user_id = ?)
        ", "ssi", [$name, $type, $uid]);

        if ($dup) {
            header('Location: manage_categories.php?status=validation');
            exit();
        }

        db_run($conn, "
            INSERT INTO categories (name, type, is_default, user_id)
            VALUES (?, ?, 0, ?)
        ", "ssi", [$name, $type, $uid]);

        header('Location: manage_categories.php?status=cat_added');
        exit();
    }
    header('Location: manage_categories.php?status=validation');
    exit();
}


// ─── Render page ───────────────────────────────────────────
$section = 'Categories';
$page    = 'manage_categories.php';
$title   = 'Manage Categories';
require_once 'includes/header.php';

// User's own categories
$custom = db_all($conn, "
    SELECT id, name, type, created_at
    FROM categories
    WHERE user_id = ?
    ORDER BY type, name
", "i", [$uid]);
$c_count = count($custom);

// System defaults — grouped by type for display
$defaults_inc = db_all($conn,
    "SELECT id, name FROM categories
     WHERE is_default = 1 AND type = 'income' ORDER BY name");
$defaults_exp = db_all($conn,
    "SELECT id, name FROM categories
     WHERE is_default = 1 AND type = 'expense' ORDER BY name");
?>

<div class="grid-form">

    <!-- LEFT: Add form + Defaults -->
    <div class="card">
        <div class="card-title">Add Custom Category</div>
        <form method="POST">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name"
                       placeholder="e.g. Subscription"
                       required maxlength="50">
            </div>
            <div class="form-group">
                <label>Type *</label>
                <div class="type-toggle">
                    <input type="radio" id="t-income"  name="type" value="income"  checked>
                    <label for="t-income">&#9650; Income</label>
                    <input type="radio" id="t-expense" name="type" value="expense">
                    <label for="t-expense">&#9660; Expense</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Add Category</button>
        </form>

        <!-- System defaults (read-only) -->
        <div class="defaults-block">
            <div class="defaults-title">Default Categories</div>
            <p class="form-hint">Built-in &mdash; available to all users, cannot be edited.</p>

            <div class="defaults-group">
                <div class="defaults-label defaults-label-inc">Income</div>
                <?php foreach ($defaults_inc as $d): ?>
                    <span class="tag"><?= htmlspecialchars($d['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="defaults-group">
                <div class="defaults-label defaults-label-exp">Expense</div>
                <?php foreach ($defaults_exp as $d): ?>
                    <span class="tag"><?= htmlspecialchars($d['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Custom categories table -->
    <div class="card">
        <div class="card-title">
            Your Custom Categories
            <span class="card-meta"><?= $c_count ?> added</span>
        </div>
        <?php if ($c_count === 0): ?>
            <div class="empty">No custom categories yet. Add one using the form on the left.</div>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Added On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($custom as $c): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($c['name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $c['type'] === 'income' ? 'inc' : 'exp' ?>">
                            <?= ucfirst($c['type']) ?>
                        </span>
                    </td>
                    <td class="td-muted td-mono">
                        <?= date('d M Y', strtotime($c['created_at'])) ?>
                    </td>
                    <td>
                        <div class="action-row">
                            <a href="edit_category.php?id=<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_category.php?id=<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this category? Transactions using it will keep their data but show no category.')">
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
