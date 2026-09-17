<?php
// ============================================================
//  header.php  -  Shared Sidebar + Page Top
// ============================================================
//  Every authenticated page (after db.php) does:
//      $section = 'Dashboard';
//      $page    = 'dashboard.php';
//      $title   = 'Overview';
//      require_once 'includes/header.php';
//
//  Then the page writes its own content, then includes
//  footer.php to close the HTML.
// ============================================================


// ─── Build the navigation array ─────────────────────────────
// To add a new page, just add it here. Sidebar updates itself.
// Each section may optionally include a 'badge' key whose value
// is shown as a small count badge next to the section label.

$nav = [
    'Dashboard'     => ['icon' => '&#9672;', 'links' => [
        'Overview' => 'dashboard.php',
    ]],
    'Transactions'  => ['icon' => '&#8645;', 'links' => [
        'All Transactions' => 'view_transactions.php',
        'Add New'          => 'add_transaction.php',
    ]],
    'Categories'    => ['icon' => '&#9871;', 'links' => [
        'Manage Categories' => 'manage_categories.php',
    ]],
    'Budgets'       => ['icon' => '&#9678;', 'links' => [
        'Manage Budgets'    => 'manage_budgets.php',
        'Budget vs Actual'  => 'budget_vs_actual.php',
    ]],
    'Savings'       => ['icon' => '&#9733;', 'links' => [
        'Manage Goals'  => 'manage_goals.php',
        'Add New Goal'  => 'add_goal.php',
    ]],
    'Reports'       => ['icon' => '&#9636;', 'links' => [
        'Monthly Report'    => 'monthly_report.php',
        'Category Report'   => 'category_report.php',
        'Date Range Report' => 'date_range_report.php',
    ]],
    'Notifications' => ['icon' => '&#9873;', 'links' => [
        'View All' => 'view_notifications.php',
    ]],
    'Profile'       => ['icon' => '&#10022;', 'links' => [
        'My Profile'      => 'profile.php',
        'Settings'        => 'settings.php',
        'Payment Methods' => 'payment_methods.php',
    ]],
];

// Admin section is appended only when an admin is signed in.
if (is_admin()) {
    $nav['Admin'] = ['icon' => '&#9881;', 'links' => [
        'Admin Dashboard' => 'admin_dashboard.php',
        'Manage Users'    => 'manage_users.php',
        'System Logs'     => 'system_logs.php',
    ]];
}

// Compute the unread-notifications count for the badge.
// Stored in the nav array so the rendering loop is uniform.
$unread = unread_notification_count($conn);
if ($unread > 0) {
    $nav['Notifications']['badge'] = $unread;
}

// ─── Current user info for the sidebar footer ───────────────
$theme = get_theme();
$me    = current_user($conn);
$prof  = current_profile($conn);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'BudgetWise', ENT_QUOTES, 'UTF-8') ?> &mdash; BudgetWise</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">

<!-- ─────────────────  SIDEBAR  ───────────────── -->
<aside class="sidebar">

    <!-- Logo / App Name -->
    <div class="sidebar-logo">
        <span class="logo-icon">&#9672;</span>
        <div>
            <div class="logo-name">BudgetWise</div>
            <div class="logo-sub">Finance Tracker</div>
        </div>
    </div>

    <!-- Main navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($nav as $sec_name => $sec): ?>
            <?php $active_sec = ($section ?? '') === $sec_name; ?>
            <div class="nav-section <?= $active_sec ? 'active' : '' ?>">
                <div class="nav-section-label">
                    <span class="nav-icon"><?= $sec['icon'] ?></span>
                    <span class="nav-section-name"><?= htmlspecialchars($sec_name, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($sec['badge'])): ?>
                        <span class="nav-badge"><?= (int)$sec['badge'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-links">
                    <?php foreach ($sec['links'] as $lname => $lfile): ?>
                        <a href="<?= htmlspecialchars($lfile, ENT_QUOTES, 'UTF-8') ?>"
                           class="nav-link <?= ($page ?? '') === $lfile ? 'active' : '' ?>">
                            <?= htmlspecialchars($lname, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar bottom: user card + theme toggle + logout -->
    <div class="sidebar-footer">
        <div class="sf-user">
            <div class="sf-avatar">
                <?= htmlspecialchars(strtoupper(substr($prof['name'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="sf-meta">
                <div class="sf-name">
                    <?= htmlspecialchars($prof['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                    <?php if (is_admin()): ?>
                        <span class="sf-role">ADMIN</span>
                    <?php endif; ?>
                </div>
                <div class="sf-email"><?= htmlspecialchars($me['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="sf-actions">
            <button type="button" class="sf-btn" onclick="toggleTheme()" title="Switch theme">
                <span id="theme-icon"><?= $theme === 'dark' ? '&#9728;' : '&#9790;' ?></span>
                <span id="theme-lbl"><?= $theme === 'dark' ? 'Light'   : 'Dark'    ?></span>
            </button>
            <a href="logout.php" class="sf-btn logout" title="Sign out">
                &#9099; Logout
            </a>
        </div>
    </div>

</aside>

<!-- ─────────────────  MAIN CONTENT  ───────────────── -->
<main class="main">

    <!-- Page header: breadcrumb + title -->
    <div class="page-header">
        <div class="breadcrumb">
            <?= htmlspecialchars($section ?? '', ENT_QUOTES, 'UTF-8') ?> &rsaquo;
            <?= htmlspecialchars($title   ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <h1 class="page-title"><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <!-- Status alert banner (driven by ?status=... in URL) -->
    <?php if (isset($_GET['status'], $status_msgs[$_GET['status']])): ?>
        <?php
        [$type, $msg] = $status_msgs[$_GET['status']];
        $cls = ['s' => 'success', 'i' => 'info', 'e' => 'error'][$type] ?? 'info';
        ?>
        <div class="alert alert-<?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

<!-- Each page's own content goes here.
     Pages close with: require_once 'includes/footer.php'; -->
