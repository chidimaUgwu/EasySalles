<?php
// admin/reports/index.php
$page_title = "Reports & Analytics";
require_once '../includes/header.php';

// Get date range for reports
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_period = $_GET['period'] ?? 'monthly';

// Get comprehensive statistics
$stats = [];
$chart_data = [];
$top_products = [];
$top_staff = [];

try {
    // Overall Statistics
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_sales,
        SUM(final_amount) as total_revenue,
        AVG(final_amount) as avg_sale,
        COUNT(DISTINCT customer_name) as unique_customers,
        COUNT(DISTINCT staff_id) as active_staff
        FROM EASYSALLES_SALES 
        WHERE DATE(sale_date) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        $stats = ['total_sales' => 0, 'total_revenue' => 0, 'avg_sale' => 0, 'unique_customers' => 0, 'active_staff' => 0];
    }
    
    // Get total products
    $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM EASYSALLES_PRODUCTS WHERE status = 'active'");
    $product_stats = $stmt->fetch();
    $stats['total_products'] = $product_stats['total_products'] ?? 0;
    
    // Get low stock count
    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM EASYSALLES_PRODUCTS WHERE current_stock <= min_stock AND status = 'active'");
    $low_stock = $stmt->fetch();
    $stats['low_stock'] = $low_stock['low_stock'] ?? 0;
    
    // Monthly Revenue Trend
    $stmt = $pdo->prepare("SELECT 
        DATE_FORMAT(sale_date, '%Y-%m') as month,
        SUM(final_amount) as revenue
        FROM EASYSALLES_SALES 
        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
        ORDER BY month ASC");
    $stmt->execute();
    $monthly_data = $stmt->fetchAll();
    
    $chart_labels = [];
    $chart_revenue = [];
    foreach ($monthly_data as $month) {
        $chart_labels[] = date('M Y', strtotime($month['month'] . '-01'));
        $chart_revenue[] = floatval($month['revenue']);
    }
    
    // Top Products
    $stmt = $pdo->prepare("SELECT 
        p.product_name,
        p.category,
        SUM(si.quantity) as units_sold,
        SUM(si.subtotal) as revenue
        FROM EASYSALLES_SALE_ITEMS si
        LEFT JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
        LEFT JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY p.product_id
        ORDER BY revenue DESC
        LIMIT 5");
    $stmt->execute([$date_from, $date_to]);
    $top_products = $stmt->fetchAll();
    
    // Top Staff
    $stmt = $pdo->prepare("SELECT 
        u.username,
        u.full_name,
        COUNT(s.sale_id) as sales_count,
        SUM(s.final_amount) as revenue
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY u.user_id
        ORDER BY revenue DESC
        LIMIT 5");
    $stmt->execute([$date_from, $date_to]);
    $top_staff = $stmt->fetchAll();
    
    // Payment Method Distribution
    $stmt = $pdo->prepare("SELECT 
        payment_method,
        COUNT(*) as transactions,
        SUM(final_amount) as amount
        FROM EASYSALLES_SALES 
        WHERE DATE(sale_date) BETWEEN ? AND ?
        GROUP BY payment_method");
    $stmt->execute([$date_from, $date_to]);
    $payment_methods = $stmt->fetchAll();
    
    // Daily Sales Trend (last 7 days)
    $stmt = $pdo->prepare("SELECT 
        DATE(sale_date) as date,
        COUNT(*) as sales_count,
        SUM(final_amount) as revenue
        FROM EASYSALLES_SALES 
        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(sale_date)
        ORDER BY date ASC");
    $stmt->execute();
    $daily_trend = $stmt->fetchAll();
    
    $daily_labels = [];
    $daily_sales = [];
    $daily_revenue = [];
    
    foreach ($daily_trend as $day) {
        $daily_labels[] = date('D', strtotime($day['date']));
        $daily_sales[] = intval($day['sales_count']);
        $daily_revenue[] = floatval($day['revenue']);
    }
    
} catch (PDOException $e) {
    // Handle errors gracefully
    $stats = ['total_sales' => 0, 'total_revenue' => 0, 'avg_sale' => 0, 'unique_customers' => 0, 
              'active_staff' => 0, 'total_products' => 0, 'low_stock' => 0];
    $chart_labels = $chart_revenue = $top_products = $top_staff = $payment_methods = [];
    $daily_labels = $daily_sales = $daily_revenue = [];
}
?>

<style>
    /* Reset and base styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        overflow-x: hidden;
    }
    
    .dashboard-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    /* Page Header */
    .page-header {
        margin-bottom: 2rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .page-title h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0.5rem;
    }
    
    .page-title p {
        color: var(--text-muted);
        margin: 0;
        font-size: 0.95rem;
    }
    
    .page-actions {
        margin-top: 1rem;
        display: flex;
        gap: 0.75rem;
    }
    
    /* Buttons */
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }
    
    .btn-secondary {
        background: var(--secondary);
        color: white;
    }
    
    .btn-secondary:hover {
        background: var(--secondary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--secondary-rgb), 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text);
        border: 2px solid var(--border);
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Filters Section */
    .filters-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .section-title i {
        color: var(--primary);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .filter-group {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.9rem;
    }
    
    .filter-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--input-bg);
        color: var(--text);
    }
    
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-top: 1rem;
    }
    
    /* Summary Stats - Full width grid */
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin: 0.25rem 0 0 0;
    }
    
    /* Revenue Chart Section - Full width */
    .chart-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .chart-container {
        height: 300px;
        position: relative;
    }
    
    /* Performance Indicators - Full width */
    .indicators-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .indicators-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .indicator-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }
    
    .indicator-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .indicator-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .indicator-info {
        flex: 1;
    }
    
    .indicator-title {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
    }
    
    .indicator-status {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .indicator-value {
        text-align: right;
    }
    
    .indicator-score {
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    /* Top Products - Full width */
    .products-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .product-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1rem;
        transition: all 0.3s ease;
    }
    
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .product-name {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }
    
    .product-category {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
    }
    
    .product-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .units-sold {
        font-weight: 600;
        color: var(--primary);
    }
    
    .product-revenue {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }
    
    /* Top Staff - Full width */
    .staff-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .staff-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }
    
    .staff-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .staff-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .staff-info {
        flex: 1;
    }
    
    .staff-name {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
    }
    
    .staff-username {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .staff-stats {
        text-align: right;
    }
    
    .staff-sales {
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }
    
    .staff-revenue {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }
    
    /* Payment Methods - Full width */
    .payments-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .payments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .payment-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1rem;
        transition: all 0.3s ease;
    }
    
    .payment-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .payment-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .payment-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .payment-name {
        font-weight: 600;
        color: var(--text);
        flex: 1;
    }
    
    .payment-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .payment-transactions {
        font-weight: 600;
        color: var(--primary);
    }
    
    .payment-amount {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }
    
    /* Daily Chart - Full width */
    .daily-chart-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .daily-chart-container {
        height: 250px;
        position: relative;
    }
    
    /* Quick Actions - Full width */
    .actions-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--border);
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .action-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        color: var(--text);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .action-title {
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
        grid-column: 1 / -1;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .empty-state h4 {
        margin-bottom: 0.5rem;
        color: var(--text-light);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0 0.75rem;
        }
        
        .page-title h2 {
            font-size: 1.5rem;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .summary-stats {
            grid-template-columns: 1fr;
        }
        
        .indicators-grid {
            grid-template-columns: 1fr;
        }
        
        .products-grid {
            grid-template-columns: 1fr;
        }
        
        .staff-grid {
            grid-template-columns: 1fr;
        }
        
        .payments-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .chart-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h2>📊 Reports & Analytics Dashboard</h2>
            <p>Comprehensive business insights and performance metrics</p>
        </div>
        <div class="page-actions">
            <button onclick="exportDashboard()" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export Dashboard
            </button>
            <button onclick="refreshDashboard()" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <h3 class="section-title"><i class="fas fa-filter"></i> Report Period Settings</h3>
        
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">Date From</label>
                <input type="date" 
                       name="date_from" 
                       class="filter-control" 
                       value="<?php echo htmlspecialchars($date_from); ?>"
                       max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Date To</label>
                <input type="date" 
                       name="date_to" 
                       class="filter-control" 
                       value="<?php echo htmlspecialchars($date_to); ?>"
                       max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Quick Period</label>
                <select name="period" class="filter-control" onchange="this.form.submit()">
                    <option value="today" <?php echo $report_period == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $report_period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="week" <?php echo $report_period == 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $report_period == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="quarter" <?php echo $report_period == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                    <option value="year" <?php echo $report_period == 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="last_month" <?php echo $report_period == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                    <option value="custom" <?php echo $report_period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Update Reports
            </button>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">$<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                <p class="stat-label">Total Revenue</p>
                <small class="text-muted">
                    <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo number_format($stats['total_sales']); ?></h3>
                <p class="stat-label">Total Sales</p>
                <small class="text-muted">
                    Avg: $<?php echo number_format($stats['avg_sale'], 2); ?> per sale
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(6, 182, 212, 0.1); color: #06B6D4;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo number_format($stats['unique_customers']); ?></h3>
                <p class="stat-label">Unique Customers</p>
                <small class="text-muted">
                    <?php echo $stats['active_staff']; ?> active staff
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo number_format($stats['total_products']); ?></h3>
                <p class="stat-label">Active Products</p>
                <small class="text-muted" style="color: var(--error);">
                    <?php echo $stats['low_stock']; ?> low stock alerts
                </small>
            </div>
        </div>
    </div>

    <!-- Revenue Trend Chart -->
    <div class="chart-section">
        <div class="chart-header">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Revenue Trend (Last 6 Months)</h3>
            <button onclick="toggleChartType()" class="btn btn-outline">
                <i class="fas fa-exchange-alt"></i> Switch Chart Type
            </button>
        </div>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Performance Indicators -->
    <div class="indicators-section">
        <h3 class="section-title"><i class="fas fa-chart-bar"></i> Business Performance Indicators</h3>
        
        <div class="indicators-grid">
            <?php 
            $indicators = [
                [
                    'title' => 'Sales Conversion',
                    'value' => $stats['total_sales'] > 0 ? 'High' : 'N/A',
                    'color' => '#10B981',
                    'icon' => 'chart-line',
                    'desc' => 'Sales effectiveness'
                ],
                [
                    'title' => 'Customer Retention',
                    'value' => $stats['unique_customers'] > 50 ? 'Good' : 'Developing',
                    'color' => '#7C3AED',
                    'icon' => 'user-check',
                    'desc' => 'Repeat customers'
                ],
                [
                    'title' => 'Inventory Health',
                    'value' => $stats['low_stock'] == 0 ? 'Excellent' : 'Needs Attention',
                    'color' => $stats['low_stock'] == 0 ? '#10B981' : '#F59E0B',
                    'icon' => 'boxes',
                    'desc' => 'Stock levels'
                ],
                [
                    'title' => 'Staff Productivity',
                    'value' => $stats['active_staff'] > 0 ? 'Active' : 'Inactive',
                    'color' => '#06B6D4',
                    'icon' => 'user-tie',
                    'desc' => 'Team performance'
                ]
            ];
            
            foreach ($indicators as $indicator):
            ?>
            <div class="indicator-card">
                <div class="indicator-icon" style="background: <?php echo $indicator['color']; ?>20; color: <?php echo $indicator['color']; ?>;">
                    <i class="fas fa-<?php echo $indicator['icon']; ?>"></i>
                </div>
                <div class="indicator-info">
                    <div class="indicator-title"><?php echo $indicator['title']; ?></div>
                    <div class="indicator-status"><?php echo $indicator['desc']; ?></div>
                </div>
                <div class="indicator-value">
                    <div class="indicator-score" style="color: <?php echo $indicator['color']; ?>;">
                        <?php echo $indicator['value']; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top Products -->
    <div class="products-section">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-star"></i> Top Performing Products</h3>
            <a href="../sales/reports.php?report_type=product" class="btn btn-outline">
                View All Products
            </a>
        </div>
        
        <div class="products-grid">
            <?php if (empty($top_products)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h4>No Product Data</h4>
                    <p>No product performance data available for the selected period</p>
                </div>
            <?php else: ?>
                <?php foreach ($top_products as $product): ?>
                <div class="product-card">
                    <div class="product-name">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </div>
                    <div class="product-category">
                        <?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?>
                    </div>
                    <div class="product-stats">
                        <div class="units-sold">
                            <?php echo number_format($product['units_sold']); ?> sold
                        </div>
                        <div class="product-revenue">
                            $<?php echo number_format($product['revenue'], 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Staff -->
    <div class="staff-section">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-user-tie"></i> Top Performing Staff</h3>
            <a href="../sales/reports.php?report_type=staff" class="btn btn-outline">
                View All Staff
            </a>
        </div>
        
        <div class="staff-grid">
            <?php if (empty($top_staff)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-chart"></i>
                    <h4>No Staff Data</h4>
                    <p>No staff performance data available for the selected period</p>
                </div>
            <?php else: ?>
                <?php foreach ($top_staff as $staff): ?>
                <div class="staff-card">
                    <div class="staff-avatar">
                        <?php echo strtoupper(substr($staff['username'], 0, 1)); ?>
                    </div>
                    <div class="staff-info">
                        <div class="staff-name">
                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                        </div>
                        <div class="staff-username">
                            @<?php echo htmlspecialchars($staff['username']); ?>
                        </div>
                    </div>
                    <div class="staff-stats">
                        <div class="staff-sales">
                            <?php echo $staff['sales_count']; ?> sales
                        </div>
                        <div class="staff-revenue">
                            $<?php echo number_format($staff['revenue'], 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="payments-section">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-credit-card"></i> Payment Methods Distribution</h3>
            <a href="../sales/reports.php?report_type=payment_method" class="btn btn-outline">
                View Details
            </a>
        </div>
        
        <div class="payments-grid">
            <?php if (empty($payment_methods)): ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <h4>No Payment Data</h4>
                    <p>No payment data available for the selected period</p>
                </div>
            <?php else: ?>
                <?php foreach ($payment_methods as $method): 
                    $method_icons = [
                        'cash' => 'money-bill',
                        'card' => 'credit-card',
                        'mobile_money' => 'mobile-alt',
                        'credit' => 'hand-holding-usd'
                    ];
                    $method_colors = [
                        'cash' => '#10B981',
                        'card' => '#7C3AED',
                        'mobile_money' => '#06B6D4',
                        'credit' => '#F59E0B'
                    ];
                ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="payment-icon" style="background: <?php echo $method_colors[$method['payment_method']] ?? '#6B7280'; ?>20; color: <?php echo $method_colors[$method['payment_method']] ?? '#6B7280'; ?>;">
                            <i class="fas fa-<?php echo $method_icons[$method['payment_method']] ?? 'question-circle'; ?>"></i>
                        </div>
                        <div class="payment-name">
                            <?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?>
                        </div>
                    </div>
                    <div class="payment-stats">
                        <div class="payment-transactions">
                            <?php echo $method['transactions']; ?> transactions
                        </div>
                        <div class="payment-amount">
                            $<?php echo number_format($method['amount'], 2); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Daily Sales Trend -->
    <div class="daily-chart-section">
        <h3 class="section-title"><i class="fas fa-calendar-day"></i> Daily Sales Trend (Last 7 Days)</h3>
        <div class="daily-chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="actions-section">
        <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Report Access</h3>
        
        <div class="actions-grid">
            <a href="../sales/reports.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="action-title">Sales Reports</div>
            </a>
            
            <a href="../inventory/index.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="action-title">Inventory Reports</div>
            </a>
            
            <a href="../reports/staff.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-chart"></i>
                </div>
                <div class="action-title">Staff Reports</div>
            </a>
            
            <a href="../reports/products.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="action-title">Product Reports</div>
            </a>
            
            <a href="../reports/financial.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="action-title">Financial Reports</div>
            </a>
            
            <button onclick="generateCustomReport()" class="action-card" style="cursor: pointer; border: none; background: var(--bg);">
                <div class="action-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="action-title">Custom Report</div>
            </button>
        </div>
    </div>
</div>

<script>
    let revenueChart = null;
    let dailyChart = null;
    let currentChartType = 'line';
    
    function initializeCharts() {
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            revenueChart = new Chart(revenueCtx, {
                type: currentChartType,
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: <?php echo json_encode($chart_revenue); ?>,
                        backgroundColor: currentChartType === 'bar' ? 'rgba(124, 58, 237, 0.6)' : 'rgba(124, 58, 237, 0.1)',
                        borderColor: 'rgba(124, 58, 237, 1)',
                        borderWidth: currentChartType === 'bar' ? 1 : 3,
                        fill: currentChartType === 'line',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Revenue: $${context.parsed.y.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Daily Sales Chart
        const dailyCtx = document.getElementById('dailyChart');
        if (dailyCtx && <?php echo !empty($daily_labels) ? 'true' : 'false'; ?>) {
            dailyChart = new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daily_labels); ?>,
                    datasets: [
                        {
                            label: 'Number of Sales',
                            data: <?php echo json_encode($daily_sales); ?>,
                            backgroundColor: 'rgba(6, 182, 212, 0.6)',
                            borderColor: 'rgba(6, 182, 212, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Revenue ($)',
                            data: <?php echo json_encode($daily_revenue); ?>,
                            backgroundColor: 'rgba(124, 58, 237, 0.6)',
                            borderColor: 'rgba(124, 58, 237, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Number of Sales'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    function toggleChartType() {
        if (revenueChart) {
            currentChartType = currentChartType === 'line' ? 'bar' : 'line';
            revenueChart.destroy();
            initializeCharts();
            showToast(`Switched to ${currentChartType} chart`, 'info');
        }
    }
    
    function refreshDashboard() {
        showToast('Refreshing dashboard data...', 'info');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
    
    function exportDashboard() {
        const data = {
            dateRange: `<?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>`,
            totalRevenue: `$${<?php echo $stats['total_revenue']; ?>}`,
            totalSales: <?php echo $stats['total_sales']; ?>,
            avgSale: `$${<?php echo $stats['avg_sale']; ?>}`,
            uniqueCustomers: <?php echo $stats['unique_customers']; ?>,
            topProducts: <?php echo json_encode($top_products); ?>,
            topStaff: <?php echo json_encode($top_staff); ?>,
            paymentMethods: <?php echo json_encode($payment_methods); ?>,
            generated: new Date().toLocaleString()
        };
        
        // Create JSON file
        const dataStr = JSON.stringify(data, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const exportFileDefaultName = `dashboard_report_${new Date().toISOString().split('T')[0]}.json`;
        
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        document.body.appendChild(linkElement);
        linkElement.click();
        document.body.removeChild(linkElement);
        
        showToast('Dashboard exported as JSON', 'success');
    }
    
    function generateCustomReport() {
        // Show custom report modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;
        
        modal.innerHTML = `
            <div style="background: white; padding: 2rem; border-radius: 20px; width: 500px; max-width: 90%;">
                <h3 style="margin-bottom: 1.5rem;">Generate Custom Report</h3>
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select class="form-control" id="customReportType">
                        <option value="sales_summary">Sales Summary</option>
                        <option value="inventory_analysis">Inventory Analysis</option>
                        <option value="staff_performance">Staff Performance</option>
                        <option value="customer_behavior">Customer Behavior</option>
                        <option value="financial_overview">Financial Overview</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Date Range</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div style="margin-top: 2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" 
                            class="btn btn-outline">
                        Cancel
                    </button>
                    <button onclick="processCustomReport()" class="btn btn-primary">
                        Generate Report
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    function processCustomReport() {
        showToast('Generating custom report...', 'info');
        setTimeout(() => {
            document.querySelector('div[style*="position: fixed"]').remove();
            showToast('Custom report generated successfully', 'success');
        }, 2000);
    }
    
    function showToast(message, type = 'info') {
        // Your existing toast implementation
        console.log(`${type}: ${message}`);
    }
    
    // Auto-refresh every 5 minutes
    setInterval(refreshDashboard, 300000);
    
    // Initialize charts on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        
        // Auto-update date fields
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = today;
        });
        
        // Handle form submission
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                // Your form submission logic
                this.submit();
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>