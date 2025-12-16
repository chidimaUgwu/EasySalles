<?php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';          // ROOT_PATH defined here
require_once __DIR__ . '/../includes/auth.php';   // Use relative path to auth.php
require_once ROOT_PATH . 'admin/includes/functions.php';

// // require_once __DIR__ . '/../config.php';          // ROOT_PATH defined here
// // require_once __DIR__ . '/../includes/auth.php';   // Use relative path to auth.php
// // require_once ROOT_PATH . 'admin/includes/functions.php';

// // Now your includes
// require_once __DIR__ . '/../config.php';          // ROOT_PATH defined here
// require_once ROOT_PATH . 'admin/includes/functions.php';
// require_once ROOT_PATH . 'includes/auth.php';

// Admin check
require_admin();

$page_title = "Dashboard";

// Get current user info
$current_user = getUserData($_SESSION['user_id']);

require_once ROOT_PATH . 'admin/includes/header.php';

// Dashboard stats
$stats = getDashboardStats();


// Get recent sales (mock data for now)
$recent_sales = [];
$top_products = [];
$low_stock_products = [];

try {
    // Get recent sales
    $stmt = $pdo->query("SELECT s.*, u.username FROM EASYSALLES_SALES s 
                         LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id 
                         ORDER BY s.sale_date DESC LIMIT 5");
    $recent_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    // If tables don't exist yet, show empty state
}

try {
    // Get top products
    $stmt = $pdo->query("SELECT p.product_name, p.current_stock 
                         FROM EASYSALLES_PRODUCTS p 
                         WHERE p.current_stock <= p.min_stock 
                         ORDER BY p.current_stock ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue if tables don't exist
}
// // Recent sales and low stock
// $recent_sales = [];
// $low_stock_products = [];

// try {
//     $stmt = $pdo->query("SELECT s.*, u.username FROM EASYSALLES_SALES s 
//                          LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id 
//                          ORDER BY s.sale_date DESC LIMIT 5");
//     $recent_sales = $stmt->fetchAll();
// } catch (PDOException $e) {}

// try {
//     $stmt = $pdo->query("SELECT p.product_name, p.current_stock 
//                          FROM EASYSALLES_PRODUCTS p 
//                          WHERE p.current_stock <= p.min_stock 
//                          ORDER BY p.current_stock ASC LIMIT 5");
//     $low_stock_products = $stmt->fetchAll();
// } catch (PDOException $e) {}


// // // admin/index.php
// // require_once __DIR__ . '/../config.php';
// // require_once ROOT_PATH . 'includes/auth.php';

// // require_admin();

// // $page_title = "Dashboard";
// // require_once ROOT_PATH . 'admin/includes/header.php';

// // // // admin/index.php
// // // require 'includes/auth.php';
// // // require_login();

// // // $page_title = "Dashboard";  // <-- MOVE THIS LINE HERE
// // // require_once 'includes/header.php';

?>

<div class="page-header">
    <div class="page-title">
        <h2>Dashboard Overview</h2>
        <p>Welcome back, <?php echo htmlspecialchars($current_user['username']); ?>! Here's what's happening today.</p>
    </div>
    <div class="page-actions">
        <a href="sales/create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Sale
        </a>
        <a href="users/add.php" class="btn btn-secondary">
            <i class="fas fa-user-plus"></i> Add Staff
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_staff']; ?></h3>
            <p>Total Staff</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_products']; ?></h3>
            <p>Total Products</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['today_sales']; ?></h3>
            <p>Today's Sales</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--secondary-light); color: var(--secondary);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($stats['today_revenue'], 2); ?></h3>
            <p>Today's Revenue</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Sales</h3>
                <a href="sales/index.php" class="btn btn-outline">View All</a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Staff</th>
                            <th>Amount</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_sales)): ?>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['transaction_code']); ?></td>
                                <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                <td>$<?php echo number_format($sale['final_amount'], 2); ?></td>
                                <td><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-receipt" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                                    <p>No sales yet today</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Low Stock Alerts</h3>
                <a href="inventory/low-stock.php" class="btn btn-outline">View All</a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Min Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($low_stock_products)): ?>
                            <?php foreach ($low_stock_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo $product['current_stock']; ?></td>
                                <td>10</td>
                                <td>
                                    <span class="badge badge-warning">Low Stock</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success); margin-bottom: 1rem;"></i>
                                    <p>All products are well stocked</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-3">
                        <a href="products/add.php" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;">
                            <i class="fas fa-box"></i> Add Product
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="inventory/stock-adjustment.php" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;">
                            <i class="fas fa-exchange-alt"></i> Adjust Stock
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="shifts/assign.php" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;">
                            <i class="fas fa-calendar-plus"></i> Assign Shift
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="reports/index.php" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;">
                            <i class="fas fa-chart-pie"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section (Placeholder for now) -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Revenue Overview (Last 7 Days)</h3>
            </div>
            <div style="padding: 1.5rem; height: 300px; display: flex; align-items: center; justify-content: center;">
                <div style="text-align: center;">
                    <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                    <p>Revenue charts will be available soon</p>
                    <small class="text-muted">Requires more sales data</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
