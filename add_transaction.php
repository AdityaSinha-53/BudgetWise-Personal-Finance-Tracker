<?php
// ============================================================
//  add_transaction.php  -  Add a New Transaction
// ============================================================
require_once 'includes/db.php';
require_once 'includes/uploads.php';

$uid = current_user_id();


// ─── If the form was submitted, save it and redirect ───────
// (Post/Redirect/Get — prevents form resubmission on refresh)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title']                    ?? '');
    $amount      = (float)($_POST['amount']                ?? 0);
    $type        = $_POST['type']                          ?? '';
    $category_id = (int)($_POST['category_id']             ?? 0);
    $pm_id       = (int)($_POST['payment_method_id']       ?? 0);
    $txn_date    = $_POST['txn_date']                      ?? '';
    $notes       = trim($_POST['notes']                    ?? '');

    // ── Server-side validation ────────────────────────────
    // Verify category and payment method exist AND belong to
    // this user (or are system defaults). Without this check,
    // a crafted POST could reference another user's records.
    $valid_cat = false;
    if ($category_id > 0) {
        $row = db_one($conn, "
            SELECT id FROM categories
            WHERE id = ?
              AND (is_default = 1 OR user_id = ?)
              AND type = ?
        ", "iis", [$category_id, $uid, $type]);
        $valid_cat = (bool)$row;
    }

    $valid_pm = true; // payment method is optional
    if ($pm_id > 0) {
        $row = db_one($conn, "
            SELECT id FROM payment_methods
            WHERE id = ? AND (is_default = 1 OR user_id = ?)
        ", "ii", [$pm_id, $uid]);
        $valid_pm = (bool)$row;
    }

    // Date format check (allow date input to vary by locale)
    $valid_date = $txn_date !== '' &&
                  preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date);

    $is_valid = $title !== ''
             && strlen($title) <= 100
             && $amount > 0
             && in_array($type, ['income', 'expense'], true)
             && $valid_cat
             && $valid_pm
             && $valid_date
             && strlen($notes) <= 1000;

    if ($is_valid) {
        // Insert the transaction
        $ok = db_run($conn, "
            INSERT INTO transactions
                (user_id, category_id, payment_method_id, title, amount, type, txn_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", "iiisdsss", [
            $uid,
            $category_id,
            $pm_id > 0 ? $pm_id : null,
            $title, $amount, $type, $txn_date,
            $notes !== '' ? $notes : null,
        ]);

        if ($ok) {
            $new_id = db_last_id($conn);

            // Save receipt if one was uploaded.
            // Failure here doesn't roll back the transaction —
            // we keep the financial record and just report the
            // upload error to the user.
            $upload = save_receipt_upload($conn, $uid, $new_id);
            if (!$upload['ok']) {
                header('Location: edit_transaction.php?id=' . $new_id
                       . '&status=validation&upload_err='
                       . urlencode($upload['error']));
                exit();
            }

            header('Location: view_transactions.php?status=added');
            exit();
        }
    }

    header('Location: add_transaction.php?status=validation');
    exit();
}


// ─── Otherwise show the form ───────────────────────────────
$section = 'Transactions';
$page    = 'add_transaction.php';
$title   = 'Add New Transaction';
require_once 'includes/header.php';

$cats = get_categories($conn);
$pms  = get_payment_methods($conn);
?>

<div class="grid-form">
    <!-- LEFT: The form -->
    <div class="card">
        <div class="card-title">Transaction Details</div>
        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title"
                       placeholder="e.g. Monthly Salary"
                       required maxlength="100">
            </div>

            <div class="form-group">
                <label>Amount (&#8377;) *</label>
                <input type="number" name="amount"
                       placeholder="e.g. 5000"
                       required min="0.01" step="0.01">
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

            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <option value="" disabled selected>Select category</option>
                    <optgroup label="── Income">
                        <?php foreach ($cats['income'] as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="── Expenses">
                        <?php foreach ($cats['expense'] as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method_id">
                    <option value="0">— None —</option>
                    <?php foreach ($pms as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="txn_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" maxlength="1000"
                          placeholder="Anything you want to remember about this transaction..."></textarea>
            </div>

            <div class="form-group">
                <label>Receipt (optional, JPG/PNG/WebP, max 2 MB)</label>
                <input type="file" name="receipt"
                       accept="image/jpeg,image/png,image/webp">
                <p class="form-hint">Attach a photo of the receipt to keep with this record.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">&plus; Add Transaction</button>
                <a href="view_transactions.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <!-- RIGHT: Tips for users -->
    <div class="card">
        <div class="card-title">Tips for Good Records</div>
        <ul class="tip-list">
            <li>
                <div class="tip-head">Descriptive titles</div>
                <div class="tip-body">
                    Use "Swiggy Order — Dinner" not just "Food".
                    Makes searching easier later.
                </div>
            </li>
            <li>
                <div class="tip-head">Correct date</div>
                <div class="tip-body">
                    Use the actual transaction date.
                    Monthly reports group by date.
                </div>
            </li>
            <li>
                <div class="tip-head">Tag the payment method</div>
                <div class="tip-body">
                    Knowing whether you paid by UPI, cash, or card
                    helps reports break down spending by channel.
                </div>
            </li>
            <li>
                <div class="tip-head">Attach receipts for big spends</div>
                <div class="tip-body">
                    For anything over a few hundred rupees, attach
                    a photo of the receipt. You'll thank yourself
                    months later.
                </div>
            </li>
            <li>
                <div class="tip-head">Custom categories</div>
                <div class="tip-body">
                    Go to Categories &rsaquo; Manage to add your own
                    tags like "Subscription" or "Gym".
                </div>
            </li>
        </ul>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
