<?php
// ============================================================
//  settings.php  -  User Preferences
// ============================================================
//  Writes to the settings table. Theme is also persisted as
//  a cookie by the in-page JS so the toggle continues to work
//  per-browser; the database value is the user's "home"
//  preference applied on first login from a new device.
// ============================================================

require_once 'includes/db.php';

$uid = current_user_id();


// ─── Handle save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $theme    = $_POST['theme']       ?? 'dark';
    $currency = $_POST['currency']    ?? 'INR';
    $language = $_POST['language']    ?? 'en';
    $df       = $_POST['date_format'] ?? 'd M Y';

    if (!in_array($theme, ['light', 'dark'], true)) $theme = 'dark';
    if (!in_array($language, ['en', 'hi'], true))   $language = 'en';
    if (!in_array($df, ['d M Y', 'Y-m-d', 'd/m/Y'], true)) $df = 'd M Y';
    $currency = preg_match('/^[A-Z]{3,5}$/', strtoupper($currency))
              ? strtoupper($currency) : 'INR';

    // Upsert in case the row doesn't exist for an older account
    db_run($conn, "
        INSERT INTO settings (user_id, theme, currency, language, date_format)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            currency = VALUES(currency),
            language = VALUES(language),
            date_format = VALUES(date_format)
    ", "issss", [$uid, $theme, $currency, $language, $df]);

    // Also push the theme to the cookie so the change applies
    // immediately without forcing the user to click the toggle.
    setcookie('theme', $theme, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => false,  // toggleTheme() in JS needs to read it
    ]);

    header('Location: settings.php?status=settings_saved');
    exit();
}


// ─── Render the form ───────────────────────────────────────
$section = 'Profile';
$page    = 'settings.php';
$title   = 'Preferences';
require_once 'includes/header.php';

$row = db_one($conn,
    "SELECT theme, currency, language, date_format FROM settings WHERE user_id = ?",
    "i", [$uid]
);
// Defaults if the row is somehow missing
$row = $row ?? ['theme' => 'dark', 'currency' => 'INR',
                'language' => 'en', 'date_format' => 'd M Y'];
?>

<div class="grid-form">

    <div class="card">
        <div class="card-title">Application Preferences</div>
        <form method="POST">

            <div class="form-group">
                <label>Theme *</label>
                <select name="theme" required>
                    <option value="dark"  <?= $row['theme'] === 'dark'  ? 'selected' : '' ?>>Dark (default)</option>
                    <option value="light" <?= $row['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                </select>
                <p class="form-hint">
                    Saved to your account so the theme follows you to other browsers.
                </p>
            </div>

            <div class="form-group">
                <label>Currency Display *</label>
                <select name="currency" required>
                    <option value="INR" <?= $row['currency'] === 'INR' ? 'selected' : '' ?>>Indian Rupee (&#8377;)</option>
                    <option value="USD" <?= $row['currency'] === 'USD' ? 'selected' : '' ?>>US Dollar ($)</option>
                    <option value="EUR" <?= $row['currency'] === 'EUR' ? 'selected' : '' ?>>Euro (€)</option>
                    <option value="GBP" <?= $row['currency'] === 'GBP' ? 'selected' : '' ?>>British Pound (£)</option>
                </select>
                <p class="form-hint">
                    Currency selection is recorded for future use. The current
                    build displays all amounts as &#8377; regardless of this setting.
                </p>
            </div>

            <div class="form-group">
                <label>Language *</label>
                <select name="language" required>
                    <option value="en" <?= $row['language'] === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="hi" <?= $row['language'] === 'hi' ? 'selected' : '' ?>>हिन्दी (Hindi)</option>
                </select>
                <p class="form-hint">
                    Language selection is recorded for future translation work.
                </p>
            </div>

            <div class="form-group">
                <label>Date Format *</label>
                <select name="date_format" required>
                    <option value="d M Y" <?= $row['date_format'] === 'd M Y' ? 'selected' : '' ?>>
                        <?= date('d M Y') ?> (default)
                    </option>
                    <option value="Y-m-d" <?= $row['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>
                        <?= date('Y-m-d') ?> (ISO)
                    </option>
                    <option value="d/m/Y" <?= $row['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>
                        <?= date('d/m/Y') ?>
                    </option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-full">Save Preferences</button>
                <a href="profile.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title">About Preferences</div>
        <p style="font-size:.85rem;color:var(--text);margin-bottom:14px">
            Preferences are stored in your account so they follow you
            from device to device. The header's quick-toggle continues
            to set the theme on a per-browser basis through a cookie.
        </p>
        <p style="font-size:.85rem;color:var(--muted)">
            Some preferences (currency, language) are recorded but not
            yet acted upon throughout the application. They are part of
            the schema so that future internationalisation work has a
            place to plug in without further database changes.
        </p>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
