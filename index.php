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
    <!-- Navigation -->
    <nav class="landing-nav">
        <div class="logo-container">
            <img src="assets/images/easysallesLogo.png" alt="EasySalles" class="logo-img">
            <span class="logo-text">EasySalles</span>
        </div>
        <a href="login.php" class="login-btn">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </a>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Streamline Your Sales Process</h1>
                <p class="hero-subtitle">Intuitive tools for managing sales, tracking performance, and boosting revenue.</p>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: rgba(124, 58, 237, 0.1);">
                            <i class="fas fa-chart-line" style="color: #7C3AED;"></i>
                        </div>
                        <h3>Real-Time Analytics</h3>
                        <p>Track sales performance with live dashboards</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon" style="background: rgba(236, 72, 153, 0.1);">
                            <i class="fas fa-shopping-cart" style="color: #EC4899;"></i>
                        </div>
                        <h3>Easy Sales Recording</h3>
                        <p>Quick and accurate transaction management</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon" style="background: rgba(6, 182, 212, 0.1);">
                            <i class="fas fa-users" style="color: #06B6D4;"></i>
                        </div>
                        <h3>Team Management</h3>
                        <p>Role-based access and performance tracking</p>
                    </div>
                </div>
                
                <div class="cta-section">
                    <a href="login.php" class="cta-button">
                        <i class="fas fa-rocket"></i> Launch Dashboard
                    </a>
                    <p class="cta-subtext">No credit card required • Start in minutes</p>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="dashboard-preview">
                    <div class="preview-header">
                        <div class="preview-dots">
                            <span style="background: #FF5F57;"></span>
                            <span style="background: #FFBD2E;"></span>
                            <span style="background: #28CA42;"></span>
                        </div>
                    </div>
                    <div class="preview-content">
                        <div class="preview-card" style="background: #7C3AED;"></div>
                        <div class="preview-card" style="background: #EC4899;"></div>
                        <div class="preview-card" style="background: #06B6D4;"></div>
                        <div class="chart-preview"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Wave -->
    <div class="wave-container">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path fill="#7C3AED" fill-opacity="0.05" d="M0,64L80,58.7C160,53,320,43,480,48C640,53,800,75,960,74.7C1120,75,1280,53,1360,42.7L1440,32L1440,120L1360,120C1280,120,1120,120,960,120C800,120,640,120,480,120C320,120,160,120,80,120L0,120Z"></path>
        </svg>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
