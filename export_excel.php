<?php
// ============================================================
//  export_excel.php  -  Download Reports as Excel-Compatible
// ============================================================
//  Excel and LibreOffice Calc both open CSV files natively
//  when served with the application/vnd.ms-excel content
//  type and an .xls extension. This keeps the implementation
//  dependency-free while satisfying the Excel-export
//  requirement.
//
//  The data assembly logic mirrors export_csv.php so any
//  future format change is a single-file update on each side.
// ============================================================

require_once 'includes/db.php';

$uid    = current_user_id();
$report = $_GET['report'] ?? 'monthly';
$view   = isset($_GET['view']) && $_GET['view'] === 'income' ? 'income' : 'expense';


// ─── Build the data set ────────────────────────────────────
if ($report === 'category') {

    log_report($conn, 'export_excel');

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

    $filename = "report_by_category_{$view}_" . date('Ymd') . ".xls";
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

    log_report($conn, 'export_excel', $from, $to);

    $rows_db = db_all($conn, "
        SELECT t.txn_date, t.title, t.amount, t.type,
               c.name AS category, pm.name AS pm
        FROM transactions t
        LEFT JOIN categories      c  ON c.id  = t.category_id
        LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id
        WHERE t.user_id = ? AND t.txn_date BETWEEN ? AND ?
        ORDER BY t.txn_date DESC
    ", "iss", [$uid, $from, $to]);

    $filename = "transactions_{$from}_to_{$to}.xls";
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
    log_report($conn, 'export_excel');

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

    $filename = "report_monthly_" . date('Ymd') . ".xls";
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
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, $headers, "\t");           // tab-delimited
foreach ($rows as $row) {
    fputcsv($out, $row, "\t");
}
fclose($out);
exit();
