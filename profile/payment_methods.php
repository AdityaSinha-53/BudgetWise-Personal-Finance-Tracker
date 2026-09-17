<?php
// ============================================================
//  payment_methods.php  -  Manage Payment Methods
// ============================================================
//  Same shape as manage_categories.php — add form on the left,
//  custom-methods table on the right, defaults block listed
//  below the form for reference.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle add ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type']       ?? 'cash';

    $valid_types = ['cash','upi','debit_card','credit_card','net_banking','wallet','other'];
    if (!in_array($type, $valid_types, true)) $type = 'other';

    $is_valid = $name !== '' && strlen($name) <= 50;

    if ($is_valid) {
        // Reject duplicates within the user's visible set
        $dup = db_one($conn, "
            SELECT id FROM payment_methods
            WHERE name = ? AND (is_default = 1 OR user_id = ?)
        ", "si", [$name, $uid]);

        if ($dup) {
            header('Location: payment_methods.php?status=validation');
            exit();
        }

        db_run($conn, "
            INSERT INTO payment_methods (user_id, name, type, is_default)
            VALUES (?, ?, ?, 0)
        ", "iss", [$uid, $name, $type]);

        header('Location: payment_methods.php?status=pm_added');
        exit();
    }
    header('Location: payment_methods.php?status=validation');
    exit();
}


// ─── Render the page ───────────────────────────────────────
$section = 'Profile';
$page    = 'payment_methods.php';
$title   = 'Payment Methods';
require_once 'includes/header.php';

$custom = db_all($conn, "
    SELECT id, name, type, created_at
    FROM payment_methods
    WHERE user_id = ?
    ORDER BY name
", "i", [$uid]);
$c_count = count($custom);

$defaults = db_all($conn, "
    SELECT id, name, type FROM payment_methods
    WHERE is_default = 1
    ORDER BY name
");

// Human-readable type labels for display
$type_labels = [
    'cash'        => 'Cash',
    'upi'         => 'UPI',
    'debit_card'  => 'Debit Card',
    'credit_card' => 'Credit Card',
    'net_banking' => 'Net Banking',
    'wallet'      => 'Wallet',
    'other'       => 'Other',
];
?>

<div class="grid-form">

    <!-- LEFT: Add form + Defaults -->
    <div class="card">
        <div class="card-title">Add Payment Method</div>
        <form method="POST">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name"
                       placeholder="e.g. SBI Credit Card, GPay"
                       required maxlength="50">
            </div>

            <div class="form-group">
                <label>Type *</label>
                <select name="type" required>
                    <?php foreach ($type_labels as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Add Method</button>
        </form>

        <!-- Defaults block -->
        <div class="defaults-block">
            <div class="defaults-title">Default Payment Methods</div>
            <p class="form-hint">Built-in &mdash; available to all users, cannot be removed.</p>
            <div class="defaults-group">
                <?php foreach ($defaults as $d): ?>
                    <span class="tag"><?= htmlspecialchars($d['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Custom payment methods -->
    <div class="card">
        <div class="card-title">
            Your Payment Methods
            <span class="card-meta"><?= $c_count ?> added</span>
        </div>
        <?php if ($c_count === 0): ?>
            <div class="empty">
                No custom payment methods yet.
                The five defaults on the left are already available for tagging transactions.
            </div>
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
            <?php foreach ($custom as $p): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="td-muted">
                        <?= htmlspecialchars($type_labels[$p['type']] ?? ucfirst($p['type'])) ?>
                    </td>
                    <td class="td-muted td-mono">
                        <?= date('d M Y', strtotime($p['created_at'])) ?>
                    </td>
                    <td>
                        <a href="payment_method_delete.php?id=<?= (int)$p['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Remove this payment method? Transactions using it will keep their data but show no method.')">
                            Delete
                        </a>
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
