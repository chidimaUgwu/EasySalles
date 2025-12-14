<?php
// admin/includes/header.php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Now your includes
require_once __DIR__ . '/../../config.php';          // ROOT_PATH defined here
require_once ROOT_PATH . 'admin/includes/functions.php';
require_once ROOT_PATH . 'includes/auth.php';

// Admin check
require_admin();

require_once 'functions.php';
$current_user = getUserData($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles Admin | <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #7C3AED;
            --primary-light: rgba(124, 58, 237, 0.1);
            --primary-dark: #6D28D9;
            --secondary: #EC4899;
            --secondary-light: rgba(236, 72, 153, 0.1);
            --accent: #06B6D4;
            --accent-light: rgba(6, 182, 212, 0.1);
            --bg: #F8FAFC;
            --text: #1E293B;
            --text-light: #64748B;
            --card-bg: #FFFFFF;
            --border: #E2E8F0;
            --success: #10B981;
            --success-light: rgba(16, 185, 129, 0.1);
            --warning: #F59E0B;
            --warning-light: rgba(245, 158, 11, 0.1);
            --error: #EF4444;
            --error-light: rgba(239, 68, 68, 0.1);
            --sidebar-width: 260px;
            --header-height: 70px;
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
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .logo-text h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo-text span {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .menu-group {
            margin-bottom: 1.5rem;
        }
        
        .menu-title {
            padding: 0 1.5rem 0.8rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-light);
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.9rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .menu-item.active {
            background: var(--primary-light);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 500;
        }
        
        .menu-item i {
            width: 20px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-profile:hover {
            background: var(--primary-light);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .user-info h4 {
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .user-info span {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .page-title p {
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .page-actions {
            display: flex;
            gap: 1rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text);
        }
        
        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
        }
        
        .btn-secondary {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-error {
            background: var(--error);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: white;
            color: var(--text);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: var(--bg);
        }
        
        .table th {
            padding: 1.2rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        
        .table tbody tr:hover {
            background: var(--primary-light);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: var(--success-light);
            color: var(--success);
        }
        
        .badge-warning {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .badge-error {
            background: var(--error-light);
            color: var(--error);
        }
        
        .badge-primary {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        
        .stat-content p {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.75rem;
        }
        
        .col {
            padding: 0 0.75rem;
            flex: 1;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
        }
        
        .col-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .col-6, .col-4, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
        
        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(150%);
            transition: transform 0.3s ease;
            z-index: 10000;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-success {
            border-left-color: var(--success);
        }
        
        .toast-error {
            border-left-color: var(--error);
        }
        
        .toast-warning {
            border-left-color: var(--warning);
        }
        
        /* Dropdown */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            min-width: 200px;
            display: none;
            z-index: 1000;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 0.8rem 1rem;
            color: var(--text);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-light);
        }
    </style>
    
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                const sidebar = document.querySelector('.sidebar');
                const toggleBtn = document.querySelector('.menu-toggle');
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Confirm before action
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
    </script>
</head>
<body>
    <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="logo-text">
                <h2>EasySalles</h2>
                <span>Admin Panel</span>
            </div>
        </a>
    </div>

<div class="sidebar-menu">
        <div class="menu-group">
            <div class="menu-title">Main</div>
            <a href="index.php" class="menu-item <?php 
                // Improved dashboard active check
                $current_page = basename($_SERVER['PHP_SELF']);
                $current_uri = $_SERVER['REQUEST_URI'];
                
                // Check if we're on index.php directly OR at root path
                if ($current_page == 'index.php' && strpos($current_uri, 'users/') === false && 
                    strpos($current_uri, 'products/') === false && strpos($current_uri, 'inventory/') === false &&
                    strpos($current_uri, 'sales/') === false && strpos($current_uri, 'reports/') === false &&
                    strpos($current_uri, 'shifts/') === false) {
                    echo 'active';
                }
            ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-title">Users & Staff</div>
            <a href="users/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'users/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-users"></i>
                <span>Manage Staff</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/shifts/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'shifts/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Shift Schedule</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-title">Products</div>
            <a href="<?php echo BASE_URL; ?>admin/products/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'products/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-box"></i>
                <span>All Products</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/products/categories.php" class="menu-item <?php 
                echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; 
            ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
            <a href="inventory/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'inventory/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-warehouse"></i>
                <span>Inventory</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-title">Sales</div>
            <a href="sales/create.php" class="menu-item <?php 
                echo basename($_SERVER['PHP_SELF']) == 'create.php' ? 'active' : ''; 
            ?>">
                <i class="fas fa-cash-register"></i>
                <span>New Sale</span>
            </a>
            <a href="sales/index.php" class="menu-item <?php 
                // Check for sales/index.php specifically
                $uri = $_SERVER['REQUEST_URI'];
                if (strpos($uri, 'sales/index.php') !== false || 
                    (strpos($uri, 'sales/') !== false && basename($_SERVER['PHP_SELF']) == 'index.php')) {
                    echo 'active';
                }
            ?>">
                <i class="fas fa-receipt"></i>
                <span>Sales History</span>
            </a>
            <a href="sales/reports.php" class="menu-item <?php 
                echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; 
            ?>">
                <i class="fas fa-chart-line"></i>
                <span>Sales Reports</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-title">Reports</div>
            <a href="reports/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'reports/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="reports/staff.php" class="menu-item <?php 
                echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; 
            ?>">
                <i class="fas fa-user-chart"></i>
                <span>Staff Performance</span>
            </a>
        </div>
        
        <div class="menu-group">
            <div class="menu-title">Settings</div>
            <a href="settings/index.php" class="menu-item <?php 
                echo strpos($_SERVER['REQUEST_URI'], 'settings/') !== false ? 'active' : ''; 
            ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
        </div>
    </div>
</div>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <h1><?php echo $page_title ?? 'Dashboard'; ?></h1>
            </div>
            
            <div class="header-right">
                <button class="btn btn-outline menu-toggle" onclick="toggleSidebar()" style="display: none;">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="dropdown">
                    <div class="user-profile" onclick="this.parentElement.classList.toggle('show')">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <h4><?php echo htmlspecialchars($current_user['username']); ?></h4>
                            <span>Administrator</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    
                    <div class="dropdown-menu">
                        <a href="../profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="../change-password.php" class="dropdown-item">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">    
