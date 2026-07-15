<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/functions.php";

safeSessionStart();
enforceAuthenticatedSession($conn, 600, 3);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Basic access check: ensures user is logged in.
 */
function checkAccess() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . redirectPath('index.php'));
        exit();
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $isSetup = strpos($script, '/player/setup.php') !== false;
    $isLogout = strpos($script, '/logout.php') !== false;

    if (
        ($_SESSION['role'] ?? '') === 'player'
        && (int)($_SESSION['is_first_login'] ?? 0) === 1
        && !$isSetup
        && !$isLogout
    ) {
        header("Location: " . redirectPath('player/setup.php'));
        exit();
    }
}

/**
 * Strict access check: ensures user is an admin.
 */
function adminOnly() {
    // First, check if user is even logged in
    checkAccess();
    
    // Then, check if user has the admin role
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                <h2>Access Denied</h2>
                <p>You do not have administrative privileges to view this page.</p>
                <a href='../home.php'>Return to Dashboard</a>
              </div>");
    }
}
?>
