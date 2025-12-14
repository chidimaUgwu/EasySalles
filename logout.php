<?php
// logout.php - Logout script for both admin and regular users
session_start();

// Store session data before destroying
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 2;

// Destroy all session data
session_unset();    // Unset all session variables
session_destroy();  // Destroy the session

// Also clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page with logout message
$logout_message = "You have been successfully logged out.";
header('Location: login.php?logout=' . urlencode($logout_message));
exit();
?>
