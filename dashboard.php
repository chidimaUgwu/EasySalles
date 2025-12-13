<?php
//dashboard.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <div class="sidebar-logo-icon">ES</div>
                <span class="sidebar-logo-text"><?php echo APP_NAME; ?></span>
            </a>
        </div>
        
        <div class="nav-section">
            <h3>Main</h3>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    <span>Dashboard</span>
                </a></li>
                <li><a href="sales/new_sale.php">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                    <span>New Sale</span>
                </a></li>
                <li><a href="sales/sales_list.php">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                    <span>Sales</span>
                </a></li>
                <li><a href="reports/">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    <span>Reports</span>
                </a></li>
            </ul>
        </div>
        
        <div class="nav-section">
            <h3>Management</h3>
            <ul class="nav-links">
                <li><a href="admin/products.php">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                    <span>Products</span>
                </a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/users.php">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    <span>Users</span>
                </a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="nav-section" style="margin-top: auto;">
            <ul class="nav-links">
                <li><a href="profile/">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <span>Profile</span>
                </a></li>
                <li><a href="logout.php">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    <span>Logout</span>
                </a></li>
            </ul>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="search-bar">
                <svg class="icon search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" placeholder="Search products, sales, reports...">
            </div>
            
            <div class="user-menu">
                <button class="notification-btn">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/></svg>
                    <span class="notification-badge"></span>
                </button>
                
                <a href="profile/" class="user-avatar">
                    <div class="avatar-circle <?php echo getAvatarColor($_SESSION['full_name']); ?>">
                        <?php echo getInitials($_SESSION['full_name']); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                    </div>
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="content-area">
            <h1>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👋</h1>
            <p class="text-light" style="margin-bottom: 2rem;">Here's what's happening with your sales today.</p>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <!-- Today's Revenue -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        </div>
                        <span class="stat-trend trend-up">
                            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M7 14l5-5 5 5z"/></svg>
                            12.5%
                        </span>
                    </div>
                    <div class="stat-value">$4,820</div>
                    <div class="stat-label">Today's Revenue</div>
                </div>
                
                <!-- Total Transactions -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon pink">
                            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        </div>
                        <span class="stat-trend trend-up">
                            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M7 14l5-5 5 5z"/></svg>
                            8.3%
                        </span>
                    </div>
                    <div class="stat-value">127</div>
                    <div class="stat-label">Today's Transactions</div>
                </div>
                
                <!-- Customers -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon cyan">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </div>
                        <span class="stat-trend trend-up">
                            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M7 14l5-5 5 5z"/></svg>
                            5.2%
                        </span>
                    </div>
                    <div class="stat-value">89</div>
                    <div class="stat-label">Customers Today</div>
                </div>
                
                <!-- Low Stock -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                        </div>
                        <span class="stat-trend trend-down">
                            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M7 10l5 5 5-5z"/></svg>
                            3 items
                        </span>
                    </div>
                    <div class="stat-value">12</div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>
            
            <!-- Recent Sales -->
            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header">
                    <h2>Recent Sales</h2>
                    <div class="table-actions">
                        <a href="sales/new_sale.php" class="btn btn-primary">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            New Sale
                        </a>
                        <a href="sales/sales_list.php" class="btn btn-secondary">View All</a>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Time</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>TXN-<?php echo date('Ymd'); ?>-127</td>
                            <td><?php echo date('h:i A'); ?></td>
                            <td>3 items</td>
                            <td>$148.50</td>
                            <td><span class="status-badge status-active">Completed</span></td>
                            <td class="table-actions-cell">
                                <button class="btn btn-secondary btn-sm">View</button>
                                <button class="btn btn-danger btn-sm">Void</button>
                            </td>
                        </tr>
                        <tr>
                            <td>TXN-<?php echo date('Ymd'); ?>-126</td>
                            <td><?php echo date('h:i A', strtotime('-15 minutes')); ?></td>
                            <td>1 item</td>
                            <td>$45.00</td>
                            <td><span class="status-badge status-active">Completed</span></td>
                            <td class="table-actions-cell">
                                <button class="btn btn-secondary btn-sm">View</button>
                                <button class="btn btn-danger btn-sm">Void</button>
                            </td>
                        </tr>
                        <tr>
                            <td>TXN-<?php echo date('Ymd'); ?>-125</td>
                            <td><?php echo date('h:i A', strtotime('-30 minutes')); ?></td>
                            <td>5 items</td>
                            <td>$312.75</td>
                            <td><span class="status-badge status-active">Completed</span></td>
                            <td class="table-actions-cell">
                                <button class="btn btn-secondary btn-sm">View</button>
                                <button class="btn btn-danger btn-sm">Void</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="sales/new_sale.php" class="btn btn-primary" style="justify-content: center;">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Quick Sale
                </a>
                <a href="admin/products.php" class="btn btn-secondary" style="justify-content: center;">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                    Add Product
                </a>
                <a href="reports/" class="btn btn-secondary" style="justify-content: center;">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    View Reports
                </a>
                <a href="profile/" class="btn btn-secondary" style="justify-content: center;">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    My Profile
                </a>
            </div>
        </div>
    </main>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
