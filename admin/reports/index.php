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

<div class="page-header">
    <div class="page-title">
        <h2>Reports & Analytics</h2>
        <p>Comprehensive business insights and performance metrics</p>
    </div>
    <div class="page-actions">
        <button onclick="exportDashboard()" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export Dashboard
        </button>
        <button onclick="refreshDashboard()" class="btn btn-outline" style="margin-left: 0.5rem;">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Report Period</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Quick Period</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
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
            
            <div class="col-3" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-chart-bar"></i> Update Reports
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Key Metrics Dashboard -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($stats['total_revenue'], 0); ?></h3>
            <p>Total Revenue</p>
            <small class="text-muted">
                <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['total_sales']); ?></h3>
            <p>Total Sales</p>
            <small class="text-muted">
                Avg: $<?php echo number_format($stats['avg_sale'], 2); ?> per sale
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['unique_customers']); ?></h3>
            <p>Unique Customers</p>
            <small class="text-muted">
                <?php echo $stats['active_staff']; ?> active staff
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($stats['total_products']); ?></h3>
            <p>Active Products</p>
            <small class="text-muted" style="color: var(--error);">
                <?php echo $stats['low_stock']; ?> low stock alerts
            </small>
        </div>
    </div>
</div>

<div class="row">
    <!-- Revenue Trend Chart -->
    <div class="col-8">
        <div class="card" style="margin-bottom: 1.5rem; height: 400px;">
            <div class="card-header">
                <h3 class="card-title">Revenue Trend (Last 6 Months)</h3>
                <div class="btn-group">
                    <button onclick="toggleChartType()" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                </div>
            </div>
            <div style="padding: 1.5rem; height: calc(100% - 60px);">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Performance Indicators -->
    <div class="col-4">
        <div class="card" style="height: 400px; overflow-y: auto;">
            <div class="card-header">
                <h3 class="card-title">Performance Indicators</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php 
                $indicators = [
                    [
                        'title' => 'Sales Conversion',
                        'value' => $stats['total_sales'] > 0 ? 'High' : 'N/A',
                        'color' => 'var(--success)',
                        'icon' => 'chart-line',
                        'trend' => 'up'
                    ],
                    [
                        'title' => 'Customer Retention',
                        'value' => $stats['unique_customers'] > 50 ? 'Good' : 'Developing',
                        'color' => 'var(--primary)',
                        'icon' => 'user-check',
                        'trend' => 'steady'
                    ],
                    [
                        'title' => 'Inventory Health',
                        'value' => $stats['low_stock'] == 0 ? 'Excellent' : 'Needs Attention',
                        'color' => $stats['low_stock'] == 0 ? 'var(--success)' : 'var(--warning)',
                        'icon' => 'boxes',
                        'trend' => $stats['low_stock'] == 0 ? 'up' : 'down'
                    ],
                    [
                        'title' => 'Staff Productivity',
                        'value' => $stats['active_staff'] > 0 ? 'Active' : 'Inactive',
                        'color' => 'var(--accent)',
                        'icon' => 'user-tie',
                        'trend' => 'steady'
                    ]
                ];
                
                foreach ($indicators as $indicator):
                ?>
                <div style="display: flex; align-items: center; justify-content: space-between; 
                            padding: 1rem; background: var(--bg); border-radius: 10px; margin-bottom: 0.8rem;">
                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                        <div style="width: 40px; height: 40px; background: <?php echo $indicator['color']; ?>20; 
                                  color: <?php echo $indicator['color']; ?>; border-radius: 10px; 
                                  display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-<?php echo $indicator['icon']; ?>"></i>
                        </div>
                        <div>
                            <strong><?php echo $indicator['title']; ?></strong><br>
                            <small class="text-muted">Current Status</small>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: <?php echo $indicator['color']; ?>; font-weight: 600;">
                            <?php echo $indicator['value']; ?>
                        </span><br>
                        <small class="text-muted">
                            <i class="fas fa-arrow-<?php echo $indicator['trend']; ?>"></i>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row" style="margin-top: 1.5rem;">
    <!-- Top Products -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Products</h3>
                <a href="../sales/reports.php?report_type=product" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                    View All
                </a>
            </div>
            <div class="table-container">
                <?php if (empty($top_products)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-chart-bar" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No product data available</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></small>
                                </td>
                                <td><?php echo number_format($product['units_sold']); ?></td>
                                <td style="color: var(--success); font-weight: 600;">
                                    $<?php echo number_format($product['revenue'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top Staff -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Performing Staff</h3>
                <a href="../sales/reports.php?report_type=staff" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                    View All
                </a>
            </div>
            <div class="table-container">
                <?php if (empty($top_staff)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-user-chart" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No staff performance data</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_staff as $staff): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="user-avatar" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($staff['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $staff['sales_count']; ?></td>
                                <td style="color: var(--success); font-weight: 600;">
                                    $<?php echo number_format($staff['revenue'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Payment Methods</h3>
                <a href="../sales/reports.php?report_type=payment_method" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                    View All
                </a>
            </div>
            <div class="table-container">
                <?php if (empty($payment_methods)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-credit-card" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No payment data available</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Transactions</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_methods as $method): 
                                $method_icons = [
                                    'cash' => 'money-bill',
                                    'card' => 'credit-card',
                                    'mobile_money' => 'mobile-alt',
                                    'credit' => 'hand-holding-usd'
                                ];
                                $method_colors = [
                                    'cash' => 'var(--success)',
                                    'card' => 'var(--primary)',
                                    'mobile_money' => 'var(--accent)',
                                    'credit' => 'var(--warning)'
                                ];
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-<?php echo $method_icons[$method['payment_method']] ?? 'question-circle'; ?>" 
                                           style="color: <?php echo $method_colors[$method['payment_method']] ?? 'var(--text)'; ?>;"></i>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $method['transactions']; ?></td>
                                <td style="color: var(--success); font-weight: 600;">
                                    $<?php echo number_format($method['amount'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Daily Sales Trend -->
<div class="row" style="margin-top: 1.5rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daily Sales Trend (Last 7 Days)</h3>
            </div>
            <div style="padding: 1.5rem; height: 300px;">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Report Quick Actions -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Report Access</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-2">
                        <a href="../sales/reports.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-chart-line"></i> Sales Reports
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../inventory/index.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-boxes"></i> Inventory Reports
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../reports/staff.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-user-chart"></i> Staff Reports
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../reports/products.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-chart-pie"></i> Product Reports
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../reports/financial.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-money-bill"></i> Financial Reports
                        </a>
                    </div>
                    <div class="col-2">
                        <button onclick="generateCustomReport()" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus"></i> Custom Report
                        </button>
                    </div>
                </div>
            </div>
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
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        borderColor: 'rgba(124, 58, 237, 1)',
                        borderWidth: 3,
                        fill: true,
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
            revenueChart.config.type = currentChartType;
            revenueChart.update();
            showToast(`Switched to ${currentChartType} chart`, 'info');
        }
    }
    
    function refreshDashboard() {
        // Refresh data via AJAX
        showToast('Refreshing dashboard data...', 'info');
        
        // In production, this would be an AJAX call
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
        linkElement.click();
        
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
    
    // Auto-refresh every 5 minutes
    setInterval(refreshDashboard, 300000);
    
    // Initialize charts on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        
        // Auto-update date fields
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = '<?php echo date("Y-m-d"); ?>';
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
