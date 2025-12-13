<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles <?php echo isset($page_title) ? ' | ' . htmlspecialchars($page_title) : ''; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary: #7C3AED;
            --secondary: #EC4899;
            --accent: #06B6D4;
            --bg: #F8FAFC;
            --text: #1E293B;
            --card-bg: #FFFFFF;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --error: #EF4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Header Styles */
        .main-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 80px;
        }
        
        /* Logo Styles */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .logo-container:hover {
            transform: translateY(-2px);
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
            transition: all 0.4s ease;
        }
        
        .logo-icon span {
            color: white;
            font-weight: 800;
            font-size: 1.5rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-main {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--text));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .logo-tagline {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
            margin-top: -3px;
        }
        
        /* Navigation Styles */
        .main-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            font-size: 1rem;
            padding: 0.7rem 1.2rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            position: relative;
            overflow: hidden;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(124, 58, 237, 0.1), transparent);
            transition: left 0.6s ease;
        }
        
        .nav-link:hover::before {
            left: 100%;
        }
        
        .nav-link:hover {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.25);
        }
        
        .nav-link i {
            font-size: 1.1rem;
        }
        
        /* User Info Styles */
        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.25);
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text);
            font-size: 0.95rem;
        }
        
        .user-role {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            color: #EF4444;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-left: 0.5rem;
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25);
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-btn:hover {
            transform: rotate(90deg);
        }
        
        /* Main Content Wrapper */
        .main-wrapper {
            min-height: calc(100vh - 160px); /* Adjust based on header/footer height */
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .header-content {
                padding: 0 1.5rem;
            }
            
            .nav-link {
                padding: 0.6rem 1rem;
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .main-nav {
                position: fixed;
                top: 80px;
                left: 0;
                width: 100%;
                background: white;
                flex-direction: column;
                padding: 1rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                transform: translateY(-100%);
                opacity: 0;
                transition: all 0.3s ease;
                z-index: 999;
            }
            
            .main-nav.active {
                transform: translateY(0);
                opacity: 1;
            }
            
            .nav-link {
                width: 100%;
                justify-content: center;
                padding: 1rem;
            }
            
            .user-section {
                display: none;
            }
            
            .logo-text {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .header-content {
                padding: 0 1rem;
                height: 70px;
            }
            
            .logo-icon {
                width: 45px;
                height: 45px;
            }
            
            .logo-icon span {
                font-size: 1.3rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .main-header {
            animation: fadeInDown 0.5s ease-out;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <!-- Logo -->
            <a href="<?php echo isset($_SESSION['user_id']) ? ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php') : 'index.php'; ?>" 
               class="logo-container">
                <div class="logo-icon">
                    <span>ES</span>
                </div>
                <div class="logo-text">
                    <div class="logo-main">EasySalles</div>
                    <div class="logo-tagline">Sales Management Simplified</div>
                </div>
            </a>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Navigation -->
            <nav class="main-nav" id="mainNav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'; ?>" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <?php if ($_SESSION['role'] != 1): ?>
                        <a href="sale-record.php" 
                           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sale-record.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cash-register"></i>
                            <span>Record Sale</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="sales-list.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales-list.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                        <span>Sales</span>
                    </a>
                    
                    <?php if ($_SESSION['role'] == 1): ?>
                        <a href="staff-manage.php" 
                           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff-manage.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Staff</span>
                        </a>
                        
                        <a href="products.php" 
                           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                        
                        <a href="reports.php" 
                           class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="profile.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile</span>
                    </a>
                <?php else: ?>
                    <a href="index.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    
                    <a href="login.php" 
                       class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                <?php endif; ?>
            </nav>
            
            <!-- User Section (Desktop) -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-section">
                    <img src="<?php echo $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username'] ?? 'User') . '&background=7C3AED&color=fff&size=128'; ?>" 
                         alt="User Avatar" 
                         class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                        <span class="user-role"><?php echo $_SESSION['role'] == 1 ? 'Administrator' : 'Sales Staff'; ?></span>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="desktop-text">Logout</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <div class="main-wrapper">
    
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mainNav = document.getElementById('mainNav');
        
        mobileMenuBtn.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            this.innerHTML = mainNav.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuBtn.contains(event.target) && !mainNav.contains(event.target)) {
                mainNav.classList.remove('active');
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
        
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.main-header');
            if (window.scrollY > 50) {
                header.style.boxShadow = '0 6px 40px rgba(0, 0, 0, 0.08)';
                header.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                header.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.05)';
                header.style.background = 'linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%)';
            }
        });
        
        // Add active class to current page link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const linkHref = link.getAttribute('href');
                if (linkHref === currentPage || 
                    (currentPage === '' && linkHref === 'index.php') ||
                    (currentPage.includes(linkHref.replace('.php', '')) && linkHref !== 'index.php')) {
                    link.classList.add('active');
                }
            });
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
