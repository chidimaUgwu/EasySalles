<?php
// config.php   Root configuration

// Absolute filesystem path to project root
define('ROOT_PATH', __DIR__ . '/');

// Base URL (for redirects)
define('BASE_URL', 'http://169.239.251.102:341/~chidima.ugwu/EasySalles/');

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
