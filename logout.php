<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Clear all session data first
$_SESSION = array();

// Update session logout time if user was logged in
if (isset($_SESSION['user_id'])) {
    $session = Database::query(
        "SELECT id FROM easysalles_sessions 
         WHERE user_id = ? AND logout_time IS NULL 
         ORDER BY login_time DESC LIMIT 1",
        [$_SESSION['user_id']]
    )->fetch();
    
    if ($session) {
        Database::query(
            "UPDATE easysalles_sessions 
             SET logout_time = NOW(), 
                 session_duration = TIMESTAMPDIFF(MINUTE, login_time, NOW()) 
             WHERE id = ?",
            [$session['id']]
        );
    }
}

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Clear any output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Redirect to login
header('Location: index.php');
exit();

// require_once 'includes/config.php';
// require_once 'includes/db.php';

// // Update session logout time
// if (isset($_SESSION['user_id'])) {
//     $session = Database::query(
//         "SELECT id FROM easysalles_sessions 
//          WHERE user_id = ? AND logout_time IS NULL 
//          ORDER BY login_time DESC LIMIT 1",
//         [$_SESSION['user_id']]
//     )->fetch();
    
//     if ($session) {
//         Database::query(
//             "UPDATE easysalles_sessions 
//              SET logout_time = NOW(), 
//                  session_duration = TIMESTAMPDIFF(MINUTE, login_time, NOW()) 
//              WHERE id = ?",
//             [$session['id']]
//         );
//     }
// }

// // Destroy session
// session_destroy();

// // Redirect to login
// header('Location: index.php');
// exit();
// ?>
