<?php
// ============================================================
//  view_transactions.php  -  All Transactions (with filter)
// ============================================================
require_once 'includes/db.php';

$section = 'Transactions';
$page    = 'view_transactions.php';
$title   = 'All Transactions';
require_once 'includes/header.php';

$uid = current_user_id();


// ─── Read filter values from the URL ───────────────────────
$f_type   = isset($_GET['type']) && in_array($_GET['type'], ['income', 'expense'], true)
            ? $_GET['type'] : '';
$f_cat    = (int)($_GET['cat']    ?? 0);
$f_pm     = (int)($_GET['pm']     ?? 0);
$f_search = trim($_GET['search'] ?? '');
$f_from   = trim($_GET['from']   ?? '');
$f_to     = trim($_GET['to']     ?? '');

// Basic date validation — reject anything that isn't ISO 8601
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = '';


// ─── Build the WHERE clause dynamically ────────────────────
// Always filter by user_id first — multi-user isolation.
$where   = ['t.user_id = ?'];
$types   = 'i';
$params  = [$uid];

if ($f_type !== '') {
    $where[]  = 't.type = ?';
    $types   .= 's';
    $params[] = $f_type;
}
if ($f_cat > 0) {
    $where[]  = 't.category_id = ?';
    $types   .= 'i';
    $params[] = $f_cat;
}
if ($f_pm > 0) {
    $where[]  = 't.payment_method_id = ?';
    $types   .= 'i';
    $params[] = $f_pm;
}
if ($f_search !== '') {
    $safe = addcslashes($f_search, '%_\\');
    $where[]  = 't.title LIKE ?';
    $types   .= 's';
    $params[] = '%' . $safe . '%';
}
if ($f_from !== '') {
    $where[]  = 't.txn_date >= ?';
    $types   .= 's';
    $params[] = $f_from;
}
if ($f_to !== '') {
    $where[]  = 't.txn_date <= ?';
    $types   .= 's';
    $params[] = $f_to;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);


// ─── Fetch matching transactions ───────────────────────────
$rows = db_all($conn, "
    SELECT t.id, t.title, t.amount, t.type, t.txn_date, t.notes,
           c.name AS category_name,
           pm.name AS payment_method_name,
           (SELECT COUNT(*) FROM attachments a WHERE a.transaction_id = t.id) AS has_receipt
    FROM transactions t
    LEFT JOIN categories      c  ON c.id  = t.category_id
    LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
    $where_sql
    ORDER BY t.txn_date DESC, t.created_at DESC
", $types, $params);

$count = count($rows);


// ─── Filtered totals ───────────────────────────────────────
$tot = db_one($conn, "
    SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS inc,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS exp
    FROM transactions t
    $where_sql
", $types, $params);


// ─── Categories and payment methods for the filter dropdowns ───
$cats   = get_categories($conn);
$pms    = get_payment_methods($conn);
?>

<!-- ─── Filter Bar ─── -->
<form method="GET">
<div class="filter-bar">

    <select name="type">
        <option value="">All Types</option>
        <option value="income"  <?= $f_type === 'income'  ? 'selected' : '' ?>>Income</option>
        <option value="expense" <?= $f_type === 'expense' ? 'selected' : '' ?>>Expense</option>
    </select>

    <select name="cat">
        <option value="0">All Categories</option>
        <?php foreach (['income' => '── Income', 'expense' => '── Expenses'] as $key => $lbl): ?>
            <optgroup label="<?= $lbl ?>">
                <?php foreach ($cats[$key] as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"
                            <?= $f_cat === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>

    <select name="pm">
        <option value="0">All Methods</option>
        <?php foreach ($pms as $p): ?>
            <option value="<?= (int)$p['id'] ?>"
                    <?= $f_pm === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" title="From date">
    <input type="date" name="to"   value="<?= htmlspecialchars($f_to)   ?>" title="To date">

    <input type="text" name="search"
           placeholder="Search title..."
           value="<?= htmlspecialchars($f_search) ?>">

    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="view_transactions.php" class="btn btn-ghost">Clear</a>
    <a href="add_transaction.php" class="btn btn-success btn-end">&plus; Add New</a>
</div>
</form>

<!-- ─── Filtered totals ─── -->
<div class="summary-grid">
    <div class="sum-card inc">
        <div class="sum-label">Filtered Income</div>
        <div class="sum-val"><?= fmt($tot['inc']) ?></div>
    </div>
    <div class="sum-card exp">
        <div class="sum-label">Filtered Expenses</div>
        <div class="sum-val"><?= fmt($tot['exp']) ?></div>
    </div>
    <div class="sum-card bal">
        <div class="sum-label">Records Found</div>
        <div class="sum-val sum-val-neutral"><?= $count ?></div>
    </div>
</div>

<!-- ─── Transactions Table ─── -->
<div class="card">
    <div class="card-title">
        Transactions
        <span class="card-meta"><?= $count ?> record(s)</span>
    </div>
    <div class="tbl-wrap">
    <?php if ($count === 0): ?>
        <div class="empty">
            No records match your filter.
            <a href="add_transaction.php">Add one</a>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Category</th>
                <th>Method</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td class="td-muted"><?= $i + 1 ?></td>
                <td class="td-name">
                    <?= htmlspecialchars($r['title']) ?>
                    <?php if ((int)$r['has_receipt'] > 0): ?>
                        <span class="receipt-tag" title="Receipt attached">&#128206;</span>
                    <?php endif; ?>
                </td>
                <td class="td-muted"><?= htmlspecialchars($r['category_name'] ?? '—') ?></td>
                <td class="td-muted"><?= htmlspecialchars($r['payment_method_name'] ?? '—') ?></td>
                <td>
                    <span class="badge badge-<?= $r['type'] === 'income' ? 'inc' : 'exp' ?>">
                        <?= ucfirst($r['type']) ?>
                    </span>
                </td>
                <td class="<?= $r['type'] === 'income' ? 'amt-inc' : 'amt-exp' ?>">
                    <?= $r['type'] === 'income' ? '+' : '-' ?><?= fmt($r['amount']) ?>
                </td>
                <td class="td-muted td-mono">
                    <?= date('d M Y', strtotime($r['txn_date'])) ?>
                </td>
                <td>
                    <div class="action-row">
                        <a href="edit_transaction.php?id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-warning">Edit</a>
                        <a href="delete_transaction.php?id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this transaction?')">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<!-- Inline receipt tag styling — kept here rather than in
     style.css because it's specific to this one page. -->
<style>
    .receipt-tag {
        margin-left: 6px;
        font-size: .85em;
        opacity: .7;
        cursor: help;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
