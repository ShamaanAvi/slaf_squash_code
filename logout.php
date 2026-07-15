<?php
/**
 * Logout Handler
 * Clears all session data and redirects to the login page.
 */

// 1. Initialize the session
require_once "config/db.php";
require_once "config/functions.php";
safeSessionStart();

if (isset($_SESSION['user_id'])) {
    ensureUserSessionsTable($conn);
    $sessionId = session_id();
    $stmt = $conn->prepare('DELETE FROM user_sessions WHERE session_id = ?');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
}

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
