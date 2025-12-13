<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'));
    exit();
}
$page_title = 'Welcome';
include 'includes/header.php';
?>

<div class="landing-container">
    <!-- Hero Section - Clean Single Column Design -->
    <main class="main-hero">
        <div class="hero-wrapper">
            <!-- Logo - Centered & Prominent -->
            <div class="brand-section">
                <img src="assets/images/easysallesLogo.png" alt="EasySalles" class="brand-logo">
                <h1 class="brand-name">EasySalles</h1>
                <p class="brand-tagline">Simple. Beautiful. Powerful Sales Management</p>
            </div>

            <!-- Main Content -->
            <div class="content-section">
                <h2 class="content-title">Streamline Your Sales Process</h2>
                <p class="content-subtitle">Intuitive tools for managing sales, tracking performance, and boosting revenue.</p>

                <!-- Feature Grid -->
                <div class="feature-section">
                    <div class="feature-item">
                        <div class="feature-icon-box" style="background: rgba(124, 58, 237, 0.1);">
                            <i class="fas fa-chart-line" style="color: #7C3AED;"></i>
                        </div>
                        <h3>Real-Time Analytics</h3>
                        <p>Track sales performance with live dashboards</p>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon-box" style="background: rgba(236, 72, 153, 0.1);">
                            <i class="fas fa-shopping-cart" style="color: #EC4899;"></i>
                        </div>
                        <h3>Easy Sales Recording</h3>
                        <p>Quick and accurate transaction management</p>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon-box" style="background: rgba(6, 182, 212, 0.1);">
                            <i class="fas fa-users" style="color: #06B6D4;"></i>
                        </div>
                        <h3>Team Management</h3>
                        <p>Role-based access and performance tracking</p>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="cta-section">
                    <a href="login.php" class="cta-button-primary">
                        <i class="fas fa-rocket"></i> Launch Dashboard
                    </a>
                    <p class="cta-note">No credit card required • Start in minutes</p>
                    
                    <div class="secondary-cta">
                        <span>Already have an account?</span>
                        <a href="login.php" class="login-link">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </a>
                    </div>
                </div>

                <!-- Simplified Footer -->
                <footer class="landing-footer">
                    <div class="footer-content">
                        <div class="footer-section">
                            <h4>Products → Sales → Revenue</h4>
                            <p>Transform your sales workflow into measurable success</p>
                        </div>
                        <div class="copyright">
                            © 2025 EasySalles - Simple Sales Management System
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
