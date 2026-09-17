<?php
// ============================================================
//  export_csv.php  -  Download Reports as CSV
// ============================================================
//  Supported reports (passed via ?report=...):
//      monthly             — month-by-month summary
//      category&view=...   — category breakdown
//      date_range&from=&to= — custom range transactions
// ============================================================

require_once 'includes/db.php';

$uid    = current_user_id();
$report = $_GET['report'] ?? 'monthly';
$view   = isset($_GET['view']) && $_GET['view'] === 'income' ? 'income' : 'expense';


// ─── Build the data set based on report type ───────────────
if ($report === 'category') {

    log_report($conn, 'export_csv');

    $rows_db = db_all($conn, "
        SELECT c.name AS category,
               COUNT(t.id)   AS transactions,
               SUM(t.amount) AS total,
               AVG(t.amount) AS average,
               MIN(t.amount) AS minimum,
               MAX(t.amount) AS maximum
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ? AND t.type = ?
        GROUP BY c.id, c.name
        ORDER BY total DESC
    ", "is", [$uid, $view]);

    $filename = "report_by_category_{$view}_" . date('Ymd') . ".csv";
    $headers  = ['Category', 'Transactions', 'Total (INR)',
                 'Average', 'Minimum', 'Maximum'];

    $rows = [];
    foreach ($rows_db as $r) {
        $rows[] = [
            csv_safe($r['category'] ?? 'Uncategorized'),
            $r['transactions'],
            number_format($r['total'],   2, '.', ''),
            number_format($r['average'], 2, '.', ''),
            number_format($r['minimum'], 2, '.', ''),
            number_format($r['maximum'], 2, '.', ''),
        ];
    }

} elseif ($report === 'date_range') {

    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
    if ($from > $to) [$from, $to] = [$to, $from];

    log_report($conn, 'export_csv', $from, $to);

    $rows_db = db_all($conn, "
        SELECT t.txn_date, t.title, t.amount, t.type,
               c.name AS category, pm.name AS pm
        FROM transactions t
        LEFT JOIN categories      c  ON c.id  = t.category_id
        LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
        WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
        ORDER BY t.txn_date DESC
    ", "iss", [$uid, $from, $to]);

    $filename = "transactions_{$from}_to_{$to}.csv";
    $headers  = ['Date', 'Title', 'Category', 'Payment Method', 'Type', 'Amount (INR)'];

    $rows = [];
    foreach ($rows_db as $r) {
        $rows[] = [
            $r['txn_date'],
            csv_safe($r['title']),
            csv_safe($r['category'] ?? 'Uncategorized'),
            csv_safe($r['pm']       ?? 'Not specified'),
            $r['type'],
            number_format($r['amount'], 2, '.', ''),
        ];
    }

} else {
    // Monthly (default)
    log_report($conn, 'export_csv');

    $rows_db = db_all($conn, "
        SELECT
            DATE_FORMAT(txn_date, '%b %Y') AS month,
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense,
            COUNT(*) AS transactions
        FROM transactions
        WHERE user_id = ?
        GROUP BY DATE_FORMAT(txn_date, '%Y-%m'),
                 DATE_FORMAT(txn_date, '%b %Y')
        ORDER BY DATE_FORMAT(txn_date, '%Y-%m') DESC
    ", "i", [$uid]);

    $filename = "report_monthly_" . date('Ymd') . ".csv";
    $headers  = ['Month', 'Income (INR)', 'Expenses (INR)',
                 'Net Savings', 'Transactions'];

    $rows = [];
    foreach ($rows_db as $r) {
        $sav = $r['income'] - $r['expense'];
        $rows[] = [
            csv_safe($r['month']),
            number_format($r['income'],  2, '.', ''),
            number_format($r['expense'], 2, '.', ''),
            number_format($sav,          2, '.', ''),
            $r['transactions'],
        ];
    }
}


// ─── Send the file ─────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit();
