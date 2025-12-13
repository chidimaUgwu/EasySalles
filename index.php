<?php
// index.php - Main entry point
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'));
    exit();
}

$page_title = 'Welcome';
include 'includes/header.php';
?>

<div class="hero text-center">
    <div class="hero-content">
        <h1 class="hero-title">EasySalles</h1>
        <p class="hero-subtitle">Simple. Beautiful. Powerful Sales Management</p>
        <div class="hero-buttons mt-2">
            <a href="login.php" class="btn btn-primary btn-large">
                <i class="fas fa-sign-in-alt"></i> Login to Get Started
            </a>
        </div>
        <div class="hero-wave">
            <svg viewBox="0 0 1440 320" preserveAspectRatio="none">
                <path fill="#7C3AED" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
