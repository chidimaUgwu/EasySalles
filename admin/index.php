<?php
//admin/index.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

// Get statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_month = date('Y-m-01');
$last_month = date('Y-m-01', strtotime('-1 month'));

// Today's stats
$today_stats = Database::query(
    "SELECT 
        COUNT(*) as transactions,
        SUM(final_amount) as revenue,
        COUNT(DISTINCT DATE_FORMAT(sale_time, '%H')) as peak_hours
     FROM easysalles_sales 
     WHERE sale_date = CURDATE() AND sale_status = 'completed'"
)->fetch();

// Yesterday's stats
$yesterday_stats = Database::query(
    "SELECT 
        SUM(final_amount) as revenue
     FROM easysalles_sales 
     WHERE sale_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND sale_status = 'completed'"
)->fetch();

// This month stats
$month_stats = Database::query(
    "SELECT 
        COUNT(*) as transactions,
        SUM(final_amount) as revenue
     FROM easysalles_sales 
     WHERE sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND sale_status = 'completed'"
)->fetch();

// Top selling products (this month)
$top_products = Database::query(
    "SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.subtotal) as revenue
     FROM easysalles_sale_items si
     JOIN easysalles_sales s ON si.sale_id = s.id
     JOIN easysalles_products p ON si.product_id = p.id
     WHERE s.sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND s.sale_status = 'completed'
     GROUP BY p.id
     ORDER BY total_sold DESC
     LIMIT 5"
)->fetchAll();

// Recent transactions
$recent_transactions = Database::query(
    "SELECT s.*, u.full_name as cashier_name
     FROM easysalles_sales s
     LEFT JOIN easysalles_users u ON s.user_id = u.id
     WHERE s.sale_status = 'completed'
     ORDER BY s.created_at DESC
     LIMIT 10"
)->fetchAll();

// Low stock products
$low_stock = Database::query(
    "SELECT p.*, c.name as category_name
     FROM easysalles_products p
     LEFT JOIN easysalles_categories c ON p.category_id = c.id
     WHERE p.current_stock <= p.min_stock_level AND p.is_active = 1
     ORDER BY p.current_stock ASC
     LIMIT 10"
)->fetchAll();

// Salesperson performance (today)
$salesperson_performance = Database::query(
    "SELECT u.full_name, COUNT(s.id) as transactions, SUM(s.final_amount) as revenue
     FROM easysalles_sales s
     JOIN easysalles_users u ON s.user_id = u.id
     WHERE s.sale_date = CURDATE() AND s.sale_status = 'completed'
     GROUP BY u.id
     ORDER BY revenue DESC"
)->fetchAll();

// Get total users
$total_users = Database::query("SELECT COUNT(*) as count FROM easysalles_users")->fetch()['count'];
$active_users = Database::query("SELECT COUNT(*) as count FROM easysalles_users WHERE is_active = 1")->fetch()['count'];

// Get total products
$total_products = Database::query("SELECT COUNT(*) as count FROM easysalles_products")->fetch()['count'];
$active_products = Database::query("SELECT COUNT(*) as count FROM easysalles_products WHERE is_active = 1")->fetch()['count'];

// Calculate revenue growth
$revenue_growth = 0;
if ($yesterday_stats['revenue'] > 0 && $today_stats['revenue'] > 0) {
    $revenue_growth = (($today_stats['revenue'] - $yesterday_stats['revenue']) / $yesterday_stats['revenue']) * 100;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .dashboard-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 1.5rem;
        }
        
        .dashboard-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dashboard-card h3 .icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text);
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
        }
        
        .trend-up { color: var(--success); }
        .trend-down { color: var(--error); }
        
        .product-list, .transaction-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .product-item, .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .product-item:last-child, .transaction-item:last-child {
            border-bottom: none;
        }
        
        .product-info, .transaction-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .product-icon, .user-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .stock-warning {
            color: var(--warning);
            font-weight: 600;
        }
        
        .stock-critical {
            color: var(--error);
            font-weight: 600;
        }
        
        .chart-container {
            height: 200px;
            margin-top: 1rem;
        }
        
        .salesperson-rank {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--bg);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
        }
        
        .rank-number {
            width: 28px;
            height: 28px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .quick-action {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            transition: var(--transition);
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }
        
        .quick-action .icon {
            width: 32px;
            height: 32px;
            margin-bottom: 0.5rem;
            fill: var(--primary);
        }
        
        .dashboard-grid-full {
            grid-column: 1 / -1;
        }
        
        @media (min-width: 1200px) {
            .admin-dashboard {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .dashboard-grid-span2 {
                grid-column: span 2;
            }
            
            .dashboard-grid-span3 {
                grid-column: span 3;
            }
        }
    </style>
</head>
<body class="dashboard-layout">
    <!-- Include Sidebar -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h1 style="margin: 0;">Admin Dashboard</h1>
                <span style="background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">
                    Administrator
                </span>
            </div>
            <?php include '../includes/user_menu.php'; ?>
        </header>
        
        <div class="content-area">
            <!-- Welcome Message -->
            <div style="margin-bottom: 2rem;">
                <h1 style="margin-bottom: 0.5rem;">Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👋</h1>
                <p style="color: var(--text-light);">Here's what's happening with your business today.</p>
            </div>
            
            <!-- Stats Overview -->
            <div class="admin-dashboard">
                <!-- Today's Revenue -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        Today's Revenue
                    </h3>
                    <div class="stat-number">
                        <?php echo formatCurrency($today_stats['revenue'] ?? 0); ?>
                    </div>
                    <div class="stat-trend <?php echo $revenue_growth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path d="<?php echo $revenue_growth >= 0 ? 'M7 14l5-5 5 5z' : 'M7 10l5 5 5-5z'; ?>"/>
                        </svg>
                        <?php echo number_format(abs($revenue_growth), 1); ?>% from yesterday
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-light); margin-top: 0.5rem;">
                        <?php echo $today_stats['transactions'] ?? 0; ?> transactions
                    </div>
                </div>
                
                <!-- This Month Revenue -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        Month to Date
                    </h3>
                    <div class="stat-number">
                        <?php echo formatCurrency($month_stats['revenue'] ?? 0); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-light); margin-top: 0.5rem;">
                        <?php echo $month_stats['transactions'] ?? 0; ?> transactions
                    </div>
                </div>
                
                <!-- Total Users -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Users
                    </h3>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-light); margin-top: 0.5rem;">
                        <?php echo $active_users; ?> active users
                    </div>
                </div>
                
                <!-- Total Products -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                        Products
                    </h3>
                    <div class="stat-number"><?php echo $total_products; ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-light); margin-top: 0.5rem;">
                        <?php echo $active_products; ?> active products
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="dashboard-card dashboard-grid-span2">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        Recent Transactions
                    </h3>
                    <ul class="transaction-list">
                        <?php if (empty($recent_transactions)): ?>
                            <li style="text-align: center; padding: 2rem; color: var(--text-light);">
                                No transactions yet
                            </li>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <li class="transaction-item">
                                <div class="transaction-info">
                                    <div class="user-avatar-small">
                                        <?php echo getInitials($transaction['cashier_name']); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo $transaction['transaction_id']; ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">
                                            <?php echo date('h:i A', strtotime($transaction['sale_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--primary);">
                                    <?php echo formatCurrency($transaction['final_amount']); ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <a href="../sales/sales_list.php" class="btn btn-secondary btn-block" style="margin-top: 1rem;">
                        View All Transactions
                    </a>
                </div>
                
                <!-- Top Products -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                        Top Products
                    </h3>
                    <ul class="product-list">
                        <?php if (empty($top_products)): ?>
                            <li style="text-align: center; padding: 2rem; color: var(--text-light);">
                                No sales data
                            </li>
                        <?php else: ?>
                            <?php foreach ($top_products as $index => $product): ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <div class="rank-number"><?php echo $index + 1; ?></div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">
                                            <?php echo $product['total_sold']; ?> sold
                                        </div>
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--primary);">
                                    <?php echo formatCurrency($product['revenue']); ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Low Stock Alert -->
                <div class="dashboard-card">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                        Low Stock Alert
                    </h3>
                    <ul class="product-list">
                        <?php if (empty($low_stock)): ?>
                            <li style="text-align: center; padding: 2rem; color: var(--text-light);">
                                All products are well stocked! ✅
                            </li>
                        <?php else: ?>
                            <?php foreach ($low_stock as $product): ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <div class="product-icon">
                                        <?php echo getInitials($product['name']); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">
                                            <?php echo $product['category_name'] ?? 'Uncategorized'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="<?php echo $product['current_stock'] == 0 ? 'stock-critical' : 'stock-warning'; ?>">
                                    <?php echo $product['current_stock']; ?> left
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <a href="products.php" class="btn btn-secondary btn-block" style="margin-top: 1rem;">
                        Manage Stock
                    </a>
                </div>
                
                <!-- Salesperson Performance -->
                <div class="dashboard-card dashboard-grid-span2">
                    <h3>
                        <svg class="icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Today's Top Performers
                    </h3>
                    <div>
                        <?php if (empty($salesperson_performance)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                                No sales data for today
                            </div>
                        <?php else: ?>
                            <?php foreach ($salesperson_performance as $index => $salesperson): ?>
                            <div class="salesperson-rank">
                                <div class="rank-number"><?php echo $index + 1; ?></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($salesperson['full_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-light);">
                                        <?php echo $salesperson['transactions']; ?> transactions
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--primary);">
                                    <?php echo formatCurrency($salesperson['revenue']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="users.php" class="quick-action">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Manage Users
                </a>
                <a href="products.php" class="quick-action">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                    Manage Products
                </a>
                <a href="../reports/" class="quick-action">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    View Reports
                </a>
                <a href="settings.php" class="quick-action">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    System Settings
                </a>
                <a href="../sales/new_sale.php" class="quick-action">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    New Sale
                </a>
                <a href="#" class="quick-action" onclick="window.print()">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                    Print Reports
                </a>
            </div>
            
            <!-- System Status -->
            <div class="dashboard-card dashboard-grid-full" style="margin-top: 2rem;">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    System Status
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <div style="font-weight: 500; margin-bottom: 0.25rem;">Database</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success);">●</span>
                            <span>Connected</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight: 500; margin-bottom: 0.25rem;">Session Timeout</div>
                        <div><?php echo SESSION_TIMEOUT / 60; ?> minutes</div>
                    </div>
                    <div>
                        <div style="font-weight: 500; margin-bottom: 0.25rem;">Shift Enforcement</div>
                        <div><?php echo SHIFT_ENFORCEMENT ? 'Enabled' : 'Disabled'; ?></div>
                    </div>
                    <div>
                        <div style="font-weight: 500; margin-bottom: 0.25rem;">Last Backup</div>
                        <div><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // Auto refresh dashboard every 60 seconds
    setInterval(function() {
        // Update time-sensitive stats
        fetch('../api/dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                // Update today's revenue
                if (data.today_revenue) {
                    document.querySelector('.stat-number').innerText = '$' + parseFloat(data.today_revenue).toFixed(2);
                }
            });
    }, 60000);
    
    // Add keyboard shortcuts for admin
    document.addEventListener('keydown', function(e) {
        // Ctrl+U for users
        if (e.ctrlKey && e.key === 'u') {
            e.preventDefault();
            window.location.href = 'users.php';
        }
        
        // Ctrl+P for products
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.location.href = 'products.php';
        }
        
        // Ctrl+R for reports
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            window.location.href = '../reports/';
        }
        
        // F1 for help
        if (e.key === 'F1') {
            e.preventDefault();
            alert('Admin Dashboard Shortcuts:\n\n' +
                  'Ctrl+U: User Management\n' +
                  'Ctrl+P: Product Management\n' +
                  'Ctrl+R: Reports\n' +
                  'F5: Refresh Dashboard\n' +
                  'F2: New Sale');
        }
    });
    </script>
</body>
</html>
