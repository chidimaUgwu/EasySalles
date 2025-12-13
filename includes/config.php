<?php
// includes/config.php  Database Configuration
// Database Configuration - UPDATED WITH CORRECT CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_NAME', 'webtech_2025A_chidima_ugwu');
define('DB_USER', 'webtech_2025A_chidima_ugwu');
define('DB_PASS', 'chidima.ugwu');

// Application Settings
define('APP_NAME', 'EasySalles');
define('APP_URL', 'http://169.239.251.102:341/~chidima.ugwu/EasySalles');
define('TIMEZONE', 'Africa/Lagos');

// Color Theme
define('PRIMARY_COLOR', '#7C3AED');
define('SECONDARY_COLOR', '#EC4899');
define('ACCENT_COLOR', '#06B6D4');
define('BG_COLOR', '#F8FAFC');
define('TEXT_COLOR', '#1E293B');

// Security
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SHIFT_ENFORCEMENT', true);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set Timezone
date_default_timezone_set(TIMEZONE);

// Start Session If Not Already Started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is salesperson
function isSalesperson() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'salesperson';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Redirect if not salesperson
function requireSalesperson() {
    requireLogin();
    if (!isSalesperson()) {
        header('Location: ../admin/index.php');
        exit();
    }
}


?>
