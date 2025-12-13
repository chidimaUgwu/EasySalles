<?php
// config/paths.php

// Prevent direct access
defined('ROOT_PATH') or die('Direct script access denied');

// Define absolute paths
define('ROOT_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('BASE_URL', 'http://169.239.251.102:341/~chidima.ugwu/EasySalles/');

// Define directory paths
define('ADMIN_PATH', ROOT_PATH . 'admin' . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);
define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);

// Define URL paths
define('ADMIN_URL', BASE_URL . 'admin/');
define('LOGIN_URL', BASE_URL . 'login.php');
define('STAFF_DASHBOARD_URL', BASE_URL . 'staff-dashboard.php');

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
