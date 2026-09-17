<?php
// ============================================================
//  index.php  -  Public Landing Page
// ============================================================
//  The page visitors see before they log in. Authenticated
//  users are redirected directly to the dashboard.
// ============================================================

$skip_auth = true;
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$theme = get_theme();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetWise &mdash; Personal Finance Tracker</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Landing-page-only styles. Kept inline rather than in
           style.css because they apply only to this single page. */
        .land-body {
            background:
                radial-gradient(ellipse at top, rgba(201,162,39,.10) 0%, transparent 60%),
                var(--bg);
            min-height: 100vh;
            padding: 0;
        }
        .land-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 22px 32px;
            border-bottom: 1px solid var(--border);
        }
        .land-logo { display: flex; align-items: center; gap: 10px; }
        .land-logo-icon { font-size: 1.5rem; color: var(--blue); }
        .land-logo-text { font-weight: 700; color: var(--text-strong); letter-spacing: -.3px; }
        .land-nav-actions { display: flex; gap: 10px; align-items: center; }

        .land-hero {
            max-width: 880px; margin: 0 auto;
            padding: 72px 32px 56px;
            text-align: center;
        }
        .land-hero-eyebrow {
            display: inline-block;
            font-size: .68rem; color: var(--blue);
            background: var(--blue-bg);
            padding: 6px 14px; border-radius: var(--radius-pill);
            letter-spacing: 2px; text-transform: uppercase;
            font-family: 'DM Mono', monospace;
            margin-bottom: 24px;
        }
        .land-hero-title {
            font-size: clamp(2rem, 6vw, 3rem);
            font-weight: 700;
            color: var(--text-strong);
            letter-spacing: -1.5px;
            line-height: 1.1;
            margin-bottom: 18px;
        }
        .land-hero-title em {
            font-style: normal; color: var(--blue);
        }
        .land-hero-sub {
            font-size: 1rem;
            color: var(--muted);
            max-width: 560px;
            margin: 0 auto 32px;
        }
        .land-hero-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .land-hero-cta .btn { padding: 12px 24px; }

        .land-features {
            max-width: 1080px; margin: 0 auto;
            padding: 32px 32px 72px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }
        .feat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
            transition: border-color .2s, transform .2s;
        }
        .feat-card:hover { border-color: var(--blue); transform: translateY(-2px); }
        .feat-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            background: var(--blue-bg);
            color: var(--blue);
            border-radius: var(--radius-sm);
            font-size: 1.1rem;
            margin-bottom: 14px;
        }
        .feat-name {
            font-weight: 600; color: var(--text-strong);
            font-size: .95rem; margin-bottom: 6px;
        }
        .feat-desc { font-size: .82rem; color: var(--muted); line-height: 1.55; }

        .land-tips {
            max-width: 1080px; margin: 0 auto;
            padding: 0 32px 72px;
        }
        .land-tips-title {
            text-align: center;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-strong);
            letter-spacing: -.5px;
            margin-bottom: 24px;
        }
        .land-tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }
        .tip-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-left: 3px solid var(--blue);
            border-radius: var(--radius-sm);
            padding: 16px 18px;
        }
        .tip-num {
            font-family: 'DM Mono', monospace;
            font-size: .68rem;
            color: var(--blue);
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 1px;
        }
        .tip-text { font-size: .85rem; color: var(--text); line-height: 1.55; }

        .land-footer {
            text-align: center;
            padding: 28px 32px;
            border-top: 1px solid var(--border);
            color: var(--dim);
            font-size: .75rem;
        }

        @media (max-width: 600px) {
            .land-nav { padding: 16px 18px; flex-wrap: wrap; gap: 10px; }
            .land-hero { padding: 48px 20px 40px; }
            .land-features, .land-tips { padding-left: 18px; padding-right: 18px; }
        }
    </style>
</head>
<body class="land-body">

<!-- Top nav -->
<nav class="land-nav">
    <div class="land-logo">
        <span class="land-logo-icon">&#9672;</span>
        <span class="land-logo-text">BudgetWise</span>
    </div>
    <div class="land-nav-actions">
        <a href="login.php" class="btn btn-ghost btn-sm">Sign In</a>
        <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
    </div>
</nav>

<!-- Hero -->
<section class="land-hero">
    <div class="land-hero-eyebrow">Personal Finance, Simplified</div>
    <h1 class="land-hero-title">
        Take control of <em>your money</em>
    </h1>
    <p class="land-hero-sub">
        Track your income and expenses, set budgets that work, pursue real
        savings goals, and understand exactly where your money goes &mdash;
        all from one clean dashboard.
    </p>
    <div class="land-hero-cta">
        <a href="register.php" class="btn btn-primary">Create Free Account &rarr;</a>
        <a href="login.php" class="btn btn-ghost">Sign In</a>
    </div>
</section>

<!-- Feature grid -->
<section class="land-features">

    <div class="feat-card">
        <div class="feat-icon">&#8645;</div>
        <div class="feat-name">Track Every Rupee</div>
        <div class="feat-desc">
            Record income and expenses in seconds. Filter, search, and
            review your history whenever you need it.
        </div>
    </div>

    <div class="feat-card">
        <div class="feat-icon">&#9678;</div>
        <div class="feat-name">Smart Budgets</div>
        <div class="feat-desc">
            Set monthly spending limits per category. Get warned the
            moment you approach the line.
        </div>
    </div>

    <div class="feat-card">
        <div class="feat-icon">&#9733;</div>
        <div class="feat-name">Savings Goals</div>
        <div class="feat-desc">
            Define what you're saving for. Watch progress bars fill as
            you contribute toward each goal.
        </div>
    </div>

    <div class="feat-card">
        <div class="feat-icon">&#9636;</div>
        <div class="feat-name">Visual Reports</div>
        <div class="feat-desc">
            Monthly summaries, category breakdowns, and trend charts that
            actually show you what's happening.
        </div>
    </div>

    <div class="feat-card">
        <div class="feat-icon">&#9873;</div>
        <div class="feat-name">Real-time Alerts</div>
        <div class="feat-desc">
            Notifications when budgets are exceeded or savings goals
            reach milestones. No surprises at month-end.
        </div>
    </div>

    <div class="feat-card">
        <div class="feat-icon">&#10022;</div>
        <div class="feat-name">Your Data, Yours</div>
        <div class="feat-desc">
            Bcrypt password hashing, role-based access, and prepared
            statements. Your records stay yours.
        </div>
    </div>

</section>

<!-- Financial tips -->
<section class="land-tips">
    <h2 class="land-tips-title">Tips for Better Money Habits</h2>
    <div class="land-tips-grid">
        <div class="tip-box">
            <div class="tip-num">TIP 01</div>
            <div class="tip-text">
                Save at least 20% of your monthly income before spending
                on anything else. Treat savings like a non-negotiable bill.
            </div>
        </div>
        <div class="tip-box">
            <div class="tip-num">TIP 02</div>
            <div class="tip-text">
                Track every expense for one full month. The visibility
                alone will change what you buy in month two.
            </div>
        </div>
        <div class="tip-box">
            <div class="tip-num">TIP 03</div>
            <div class="tip-text">
                Build an emergency fund covering three to six months
                of expenses before chasing investment returns.
            </div>
        </div>
        <div class="tip-box">
            <div class="tip-num">TIP 04</div>
            <div class="tip-text">
                Set specific, time-bound savings goals. "Save more" fails;
                "&#8377;50,000 for a laptop by December" succeeds.
            </div>
        </div>
        <div class="tip-box">
            <div class="tip-num">TIP 05</div>
            <div class="tip-text">
                Review your spending categories monthly. Cancel the
                subscriptions and habits that no longer serve you.
            </div>
        </div>
        <div class="tip-box">
            <div class="tip-num">TIP 06</div>
            <div class="tip-text">
                Avoid lifestyle inflation. When your income rises, send
                the increase to savings before it disappears into "extras."
            </div>
        </div>
    </div>
</section>

<footer class="land-footer">
    BudgetWise &mdash; Personal Finance Tracker
    &nbsp;|&nbsp;
    BCA Major Project
</footer>

</body>
</html>
