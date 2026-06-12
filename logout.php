<?php
/**
 * Logout Handler
 * Clears all session data and redirects to the login page.
 */

// 1. Initialize the session
session_start();

// 2. Unset all session variables
$_SESSION = array();

// 3. Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Final destruction of the session
session_destroy();

// 5. Redirect to the login page (index.php)
header("Location: index.php");
exit();