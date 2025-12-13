<?php
// config/paths.php

// Define absolute paths
define('ROOT_PATH', '/home/chidima.ugwu/public_html/EasySalles/');
define('BASE_URL', 'http://169.239.251.102:341/~chidima.ugwu/EasySalles/');

// Define directory paths
define('ADMIN_PATH', ROOT_PATH . 'admin/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');

// Define URL paths
define('ADMIN_URL', BASE_URL . 'admin/');
define('LOGIN_URL', BASE_URL . 'login.php');
define('STAFF_DASHBOARD_URL', BASE_URL . 'staff-dashboard.php');

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
