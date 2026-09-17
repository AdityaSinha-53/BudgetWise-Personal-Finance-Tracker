<?php
// ============================================================
//  edit_transaction.php  -  Edit an Existing Transaction
// ============================================================
require_once 'includes/db.php';
require_once 'includes/uploads.php';

$uid = current_user_id();

// ─── Get the transaction ID and verify ownership ───────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: view_transactions.php');
    exit();
}

// IDOR defence — verify this record belongs to the current
// user (or the user is admin). Admins can edit any record.
if (!user_owns($conn, 'transactions', $id)) {
    header('Location: view_transactions.php?status=unauthorized');
    exit();
}

// Look up the existing record
$row = db_one($conn, "
    SELECT id, user_id, category_id, payment_method_id,
           title, amount, type, txn_date, notes, created_at
    FROM transactions WHERE id = ?
", "i", [$id]);

if (!$row) {
    header('Location: view_transactions.php?status=not_found');
    exit();
}

// ─── Save changes if the form was submitted ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title         = trim($_POST['title']                   ?? '');
    $amount        = (float)($_POST['amount']               ?? 0);
    $type          = $_POST['type']                         ?? '';
    $category_id   = (int)($_POST['category_id']            ?? 0);
    $pm_id         = (int)($_POST['payment_method_id']      ?? 0);
    $txn_date      = $_POST['txn_date']                     ?? '';
    $notes         = trim($_POST['notes']                   ?? '');
    $remove_recpt  = isset($_POST['remove_receipt']);

    // Validate category and payment method ownership
    $valid_cat = false;
    if ($category_id > 0) {
        $r = db_one($conn, "
            SELECT id FROM categories
            WHERE id = ?
              AND (is_default = 1 OR user_id = ?)
              AND type = ?
        ", "iis", [$category_id, $uid, $type]);
        $valid_cat = (bool)$r;
    }

    $valid_pm = true;
    if ($pm_id > 0) {
        $r = db_one($conn, "
            SELECT id FROM payment_methods
            WHERE id = ? AND (is_default = 1 OR user_id = ?)
        ", "ii", [$pm_id, $uid]);
        $valid_pm = (bool)$r;
    }

    $valid_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date);

    $is_valid = $title !== ''
             && strlen($title) <= 100
             && $amount > 0
             && in_array($type, ['income', 'expense'], true)
             && $valid_cat
             && $valid_pm
             && $valid_date
             && strlen($notes) <= 1000;

    if ($is_valid) {
        $ok = db_run($conn, "
            UPDATE transactions
            SET category_id = ?, payment_method_id = ?,
                title = ?, amount = ?, type = ?, txn_date = ?, notes = ?
            WHERE id = ?
        ", "iisdsssi", [
            $category_id,
            $pm_id > 0 ? $pm_id : null,
            $title, $amount, $type, $txn_date,
            $notes !== '' ? $notes : null,
            $id,
        ]);

        if ($ok) {
            // Receipt actions:
            //   1. Remove existing if checkbox ticked
            //   2. Replace with new upload if one was provided
            if ($remove_recpt) {
                purge_attachments_for_transaction($conn, $id);
            }

            // If a new file came in, save it (replacing any
            // existing receipt by purging first)
            if (!empty($_FILES['receipt']) &&
                $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
                purge_attachments_for_transaction($conn, $id);
                $upload = save_receipt_upload($conn, $uid, $id);
                if (!$upload['ok']) {
                    header('Location: edit_transaction.php?id=' . $id
                           . '&status=validation&upload_err='
                           . urlencode($upload['error']));
                    exit();
                }
            }

            header('Location: view_transactions.php?status=updated');
            exit();
        }
    }
    header('Location: edit_transaction.php?id=' . $id . '&status=validation');
    exit();
}

// ─── Otherwise render the edit form ────────────────────────
$section = 'Transactions';
$page    = 'view_transactions.php';
$title   = 'Edit Transaction';
require_once 'includes/header.php';

$cats     = get_categories($conn);
$pms      = get_payment_methods($conn);
$receipt  = get_receipt_for_transaction($conn, $id);

// Resolve names for the side panel
$cat_name = '—';
if ($row['category_id']) {
    $r = db_one($conn, "SELECT name FROM categories WHERE id = ?",
                "i", [(int)$row['category_id']]);
    $cat_name = $r['name'] ?? '—';
}
$pm_name = '—';
if ($row['payment_method_id']) {
    $r = db_one($conn, "SELECT name FROM payment_methods WHERE id = ?",
                "i", [(int)$row['payment_method_id']]);
    $pm_name = $r['name'] ?? '—';
}
?>

<?php if (isset($_GET['upload_err'])): ?>
    <div class="alert alert-error">
        Upload error: <?= htmlspecialchars($_GET['upload_err'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="grid-form">
    <div class="card">
        <div class="card-title">Edit Transaction #<?= $id ?></div>
        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title"
                       value="<?= htmlspecialchars($row['title']) ?>"
                       required maxlength="100">
            </div>

            <div class="form-group">
                <label>Amount (&#8377;) *</label>
                <input type="number" name="amount"
                       value="<?= htmlspecialchars($row['amount']) ?>"
                       required min="0.01" step="0.01">
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

            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <optgroup label="── Income">
                        <?php foreach ($cats['income'] as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)$row['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="── Expenses">
                        <?php foreach ($cats['expense'] as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)$row['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method_id">
                    <option value="0" <?= !$row['payment_method_id'] ? 'selected' : '' ?>>— None —</option>
                    <?php foreach ($pms as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                            <?= (int)$row['payment_method_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="txn_date"
                       value="<?= htmlspecialchars($row['txn_date']) ?>" required>
            </div>

            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" maxlength="1000"><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
            </div>

            <!-- Receipt section -->
            <div class="form-group">
                <label>Receipt</label>
                <?php if ($receipt): ?>
                    <div class="receipt-current">
                        <a href="uploads/receipts/<?= htmlspecialchars($receipt['stored_name']) ?>"
                           target="_blank" rel="noopener">
                            &#128206; View current: <?= htmlspecialchars($receipt['filename']) ?>
                        </a>
                        <label class="receipt-remove">
                            <input type="checkbox" name="remove_receipt" value="1">
                            Remove this receipt
                        </label>
                    </div>
                    <p class="form-hint">Upload a new file below to replace the current receipt.</p>
                <?php endif; ?>
                <input type="file" name="receipt"
                       accept="image/jpeg,image/png,image/webp">
                <p class="form-hint">JPG, PNG, or WebP — maximum 2 MB.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                <a href="view_transactions.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Current values for reference -->
    <div class="card">
        <div class="card-title">Current Values</div>
        <dl class="info-list">
            <?php
            $info = [
                'Title'          => $row['title'],
                'Amount'         => fmt($row['amount']),
                'Type'           => ucfirst($row['type']),
                'Category'       => $cat_name,
                'Payment Method' => $pm_name,
                'Date'           => date('d M Y', strtotime($row['txn_date'])),
                'Created'        => date('d M Y H:i', strtotime($row['created_at'])),
                'Receipt'        => $receipt ? 'Yes' : 'No',
            ];
            foreach ($info as $label => $value): ?>
                <dt><?= htmlspecialchars($label) ?></dt>
                <dd><?= htmlspecialchars($value) ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</div>

<!-- Receipt block styling — local to this page -->
<style>
    .receipt-current {
        display: flex; flex-direction: column; gap: 6px;
        padding: 10px 12px;
        background: var(--input-bg);
        border: 1px solid var(--border2);
        border-radius: var(--radius-sm);
        margin-bottom: 8px;
        font-size: .82rem;
    }
    .receipt-current a {
        color: var(--blue); font-weight: 600;
        text-decoration: none;
    }
    .receipt-current a:hover { text-decoration: underline; }
    .receipt-remove {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .76rem; color: var(--muted); text-transform: none;
        letter-spacing: 0; margin: 0;
    }
    .receipt-remove input { width: auto; margin: 0; }
</style>

<?php require_once 'includes/footer.php'; ?>
