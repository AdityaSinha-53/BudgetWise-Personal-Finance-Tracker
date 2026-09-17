<?php
// ============================================================
//  logout.php  -  End User Session
// ============================================================
//  Clears all session data and sends the user back to login
//  with a confirmation banner.
// ============================================================

$skip_auth = true;
require_once 'includes/db.php';

// Step 1: Empty the $_SESSION array in memory
$_SESSION = [];

// Step 2: Tell the browser to delete the session cookie
// by re-setting it with an expiry in the past.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 3600,
        $params["path"],     $params["domain"],
        $params["secure"],   $params["httponly"]
    );
}

// Step 3: Destroy the session file on the server
session_destroy();

// Step 4: Back to login with a confirmation banner
header("Location: login.php?status=logged_out");
exit();
