<?php
// ============================================================
//  export_pdf.php  -  Print-to-PDF Report Generation
// ============================================================
//  Generates a self-contained, print-optimised HTML page and
//  immediately triggers the browser's print dialog. The user
//  selects "Save as PDF" from the destination dropdown to
//  produce the file.
//
//  This approach avoids the TCPDF / DOMPDF dependency while
//  producing a clean, professionally formatted document. The
//  trade-off is one extra user action (the Save dialog) in
//  exchange for zero library setup, which is the right
//  trade-off for a XAMPP-portable college project.
// ============================================================

require_once 'includes/db.php';

$uid    = current_user_id();
$report = $_GET['report'] ?? 'monthly';
$view   = isset($_GET['view']) && $_GET['view'] === 'income' ? 'income' : 'expense';

$user_info = current_user($conn);
$profile   = current_profile($conn);
$gen_at    = date('d M Y, H:i');

// Build the title and dataset based on report type
$page_title = '';
$rows       = [];
$totals     = [];

if ($report === 'category') {

    log_report($conn, 'export_pdf');

    $page_title = "Category Report — " . ucfirst($view);
    $rows = db_all($conn, "
        SELECT c.name AS category,
               COUNT(t.id)   AS cnt,
               SUM(t.amount) AS total,
               AVG(t.amount) AS avg_amt,
               MAX(t.amount) AS max_amt
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ? AND t.type = ?
        GROUP BY c.id, c.name
        ORDER BY total DESC
    ", "is", [$uid, $view]);

    $totals['Grand Total'] = array_sum(array_column($rows, 'total'));
    $totals['Categories']  = count($rows);
    $totals['Transactions'] = array_sum(array_column($rows, 'cnt'));

} elseif ($report === 'date_range') {

    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
    if ($from > $to) [$from, $to] = [$to, $from];

    log_report($conn, 'export_pdf', $from, $to);

    $page_title = "Transactions — " . date('d M Y', strtotime($from))
                . " to " . date('d M Y', strtotime($to));

    $rows = db_all($conn, "
        SELECT t.txn_date, t.title, t.amount, t.type,
               c.name AS category, pm.name AS pm
        FROM transactions t
        LEFT JOIN categories      c  ON c.id  = t.category_id
        LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
        WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
        ORDER BY t.txn_date DESC
    ", "iss", [$uid, $from, $to]);

    $sum = db_one($conn, "
        SELECT COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS inc,
               COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS exp
        FROM transactions
        WHERE user_id = ? AND txn_date BETWEEN ? AND ?
    ", "iss", [$uid, $from, $to]);
    $totals['Income']   = (float)$sum['inc'];
    $totals['Expenses'] = (float)$sum['exp'];
    $totals['Net']      = (float)$sum['inc'] - (float)$sum['exp'];

} else {

    log_report($conn, 'export_pdf');

    $page_title = "Monthly Report";
    $rows = db_all($conn, "
        SELECT
            DATE_FORMAT(txn_date, '%b %Y') AS month,
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense,
            COUNT(*) AS cnt
        FROM transactions
        WHERE user_id = ?
        GROUP BY DATE_FORMAT(txn_date, '%Y-%m'),
                 DATE_FORMAT(txn_date, '%b %Y')
        ORDER BY DATE_FORMAT(txn_date, '%Y-%m') DESC
    ", "i", [$uid]);

    $totals['Total Income']   = array_sum(array_column($rows, 'income'));
    $totals['Total Expenses'] = array_sum(array_column($rows, 'expense'));
    $totals['Net Savings']    = $totals['Total Income'] - $totals['Total Expenses'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> — BudgetWise</title>
<style>
    /* Page setup for print */
    @page { size: A4 portrait; margin: 1.6cm 1.4cm; }

    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        color: #1a1a1a;
        font-size: 11pt;
        line-height: 1.5;
        background: white;
    }

    /* Document header */
    .doc-head {
        display: flex; justify-content: space-between; align-items: flex-end;
        padding-bottom: 14px;
        border-bottom: 2px solid #1a1a1a;
        margin-bottom: 22px;
    }
    .doc-brand {
        font-size: 18pt; font-weight: 700;
        letter-spacing: -.3px;
    }
    .doc-brand small {
        font-size: 9pt; font-weight: 400; color: #666;
        display: block; margin-top: 2px;
    }
    .doc-meta {
        text-align: right; font-size: 9pt; color: #555;
    }

    .doc-title {
        font-size: 16pt; font-weight: 600; margin-bottom: 4px;
    }
    .doc-sub { color: #555; font-size: 10pt; margin-bottom: 18px; }

    /* Totals strip */
    .totals {
        display: flex; gap: 20px;
        margin: 18px 0 22px;
        padding: 14px 16px;
        background: #f5f5f5;
        border-left: 3px solid #1a1a1a;
    }
    .totals-item { flex: 1; }
    .totals-lbl {
        font-size: 8pt; color: #555;
        text-transform: uppercase; letter-spacing: 1.2px;
        margin-bottom: 4px;
    }
    .totals-val { font-size: 13pt; font-weight: 600; }
    .totals-val.green { color: #047857; }
    .totals-val.red   { color: #be123c; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th {
        text-align: left; padding: 8px 10px;
        background: #f5f5f5; color: #333;
        font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px;
        border-bottom: 1px solid #ccc;
    }
    td {
        padding: 7px 10px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }
    tr:nth-child(even) td { background: #fafafa; }

    .num    { font-family: 'Courier New', monospace; text-align: right; }
    .pos    { color: #047857; font-weight: 600; }
    .neg    { color: #be123c; font-weight: 600; }
    .mono   { font-family: 'Courier New', monospace; }

    /* Document footer */
    .doc-foot {
        margin-top: 32px;
        padding-top: 12px;
        border-top: 1px solid #ccc;
        font-size: 8.5pt; color: #777;
        display: flex; justify-content: space-between;
    }

    /* On-screen helpers visible only before print */
    .screen-only {
        position: fixed;
        top: 14px; right: 14px;
        z-index: 999;
    }
    @media print { .screen-only { display: none; } }

    .print-btn {
        background: #1a1a1a; color: white;
        padding: 10px 18px;
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 11pt; border-radius: 6px;
        text-decoration: none; cursor: pointer;
        border: none;
    }
    .print-btn:hover { opacity: .88; }

    .empty-note { color: #777; text-align: center; padding: 40px 0; }
</style>
</head>
<body>

<!-- Screen-only print trigger -->
<div class="screen-only">
    <button class="print-btn" onclick="window.print()">&#x2913; Save as PDF / Print</button>
</div>

<!-- Document header -->
<div class="doc-head">
    <div class="doc-brand">
        BudgetWise
        <small>Personal Finance Tracker</small>
    </div>
    <div class="doc-meta">
        <strong><?= htmlspecialchars($profile['name'] ?? 'User') ?></strong><br>
        <?= htmlspecialchars($user_info['email'] ?? '') ?><br>
        Generated <?= htmlspecialchars($gen_at) ?>
    </div>
</div>

<!-- Title -->
<div class="doc-title"><?= htmlspecialchars($page_title) ?></div>
<div class="doc-sub">Account: <?= htmlspecialchars($user_info['username'] ?? '—') ?></div>

<!-- Totals strip -->
<?php if (!empty($totals)): ?>
<div class="totals">
    <?php foreach ($totals as $lbl => $val):
        $is_money = is_numeric($val) && (in_array($lbl, ['Income','Expenses','Net','Total Income','Total Expenses','Net Savings','Grand Total']));
        $cls = '';
        if ($lbl === 'Income' || $lbl === 'Total Income') $cls = 'green';
        elseif ($lbl === 'Expenses' || $lbl === 'Total Expenses') $cls = 'red';
        elseif (in_array($lbl, ['Net', 'Net Savings'])) $cls = $val >= 0 ? 'green' : 'red';
    ?>
        <div class="totals-item">
            <div class="totals-lbl"><?= htmlspecialchars($lbl) ?></div>
            <div class="totals-val <?= $cls ?>">
                <?= $is_money ? fmt($val) : htmlspecialchars($val) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- Report body -->
<?php if (empty($rows)): ?>
    <div class="empty-note">No data available for this report.</div>

<?php elseif ($report === 'category'): ?>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Count</th>
                <th class="num">Total</th>
                <th class="num">Average</th>
                <th class="num">Highest</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['category'] ?? 'Uncategorized') ?></td>
                <td class="num"><?= (int)$r['cnt'] ?></td>
                <td class="num <?= $view === 'income' ? 'pos' : 'neg' ?>"><?= fmt($r['total']) ?></td>
                <td class="num mono"><?= fmt($r['avg_amt']) ?></td>
                <td class="num mono"><?= fmt($r['max_amt']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($report === 'date_range'): ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Category</th>
                <th>Method</th>
                <th>Type</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono"><?= date('d M Y', strtotime($r['txn_date'])) ?></td>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['pm']       ?? '—') ?></td>
                <td><?= ucfirst($r['type']) ?></td>
                <td class="num <?= $r['type'] === 'income' ? 'pos' : 'neg' ?>">
                    <?= $r['type'] === 'income' ? '+' : '-' ?><?= fmt($r['amount']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php else: /* monthly */ ?>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Transactions</th>
                <th class="num">Income</th>
                <th class="num">Expenses</th>
                <th class="num">Net Savings</th>
                <th class="num">Rate</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $sav  = $r['income'] - $r['expense'];
            $rate = $r['income'] > 0 ? round(($sav / $r['income']) * 100, 1) : 0;
        ?>
            <tr>
                <td><?= htmlspecialchars($r['month']) ?></td>
                <td class="num"><?= (int)$r['cnt'] ?></td>
                <td class="num pos">+<?= fmt($r['income']) ?></td>
                <td class="num neg">-<?= fmt($r['expense']) ?></td>
                <td class="num <?= $sav >= 0 ? 'pos' : 'neg' ?>"><?= fmt($sav) ?></td>
                <td class="num"><?= $rate ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


<!-- Document footer -->
<div class="doc-foot">
    <div>BudgetWise &mdash; Personal Finance Tracker</div>
    <div>Confidential &mdash; for the named account holder only</div>
</div>

<script>
    // Auto-open the print dialog as soon as the page renders.
    // The user picks "Save as PDF" from the destination list.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 250);
    });
</script>

</body>
</html>
