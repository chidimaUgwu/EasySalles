<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to require login
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Function to require admin role
function require_admin() {
    require_login();
    if ($_SESSION['role'] != 1) {
        header('Location: staff-dashboard.php'); // or error page
        exit();
    }
}

// Function to check if first login (we'll add a flag later, for now mock)
function is_first_login() {
    return isset($_SESSION['first_login']) && $_SESSION['first_login'] === true;
}
?>
