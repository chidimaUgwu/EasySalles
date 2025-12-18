<?php
// admin/sales/reports.php
$page_title = "Sales Reports & Analytics";
require_once '../includes/header.php';

// Get date parameters (default to last 30 days)
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';
$group_by = $_GET['group_by'] ?? 'daily';
$chart_type = $_GET['chart_type'] ?? 'line';

// Get all staff for filter
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS WHERE role = 2 ORDER BY username");
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue if table doesn't exist
}

// Get categories for product reports
$categories = [];
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM EASYSALLES_CATEGORIES ORDER BY category_name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue if table doesn't exist
}

// Base query for sales data
$base_query = "SELECT s.*, u.username, u.full_name 
               FROM EASYSALLES_SALES s 
               LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id 
               WHERE DATE(s.sale_date) BETWEEN ? AND ? 
               AND s.payment_status = 'paid' 
               AND s.sale_status = 'completed'";
$params = [$date_from, $date_to];

// Execute base query for overall stats
try {
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
    $all_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_sales = [];
}

// Calculate basic stats
$total_sales = count($all_sales);
$total_revenue = array_sum(array_column($all_sales, 'final_amount'));
$total_transactions = $total_sales;
$average_transaction = $total_sales > 0 ? $total_revenue / $total_sales : 0;

// Calculate date range for daily average
$date_range_days = max(1, (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1);
$daily_average = $total_revenue / $date_range_days;

// Get sales by day for charts
$sales_by_day = [];
$revenue_by_day = [];
try {
    $query = "SELECT DATE(sale_date) as sale_day, 
                     COUNT(*) as sales_count,
                     SUM(final_amount) as total_revenue,
                     AVG(final_amount) as avg_sale
              FROM EASYSALLES_SALES 
              WHERE DATE(sale_date) BETWEEN ? AND ? 
              AND payment_status = 'paid'
              AND sale_status = 'completed'
              GROUP BY DATE(sale_date)
              ORDER BY sale_day";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $daily_data = $stmt->fetchAll();
    
    // Fill in missing days
    $start = new DateTime($date_from);
    $end = new DateTime($date_to);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        $found = false;
        
        foreach ($daily_data as $day) {
            if ($day['sale_day'] == $date_str) {
                $sales_by_day[$date_str] = (int)$day['sales_count'];
                $revenue_by_day[$date_str] = (float)$day['total_revenue'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $sales_by_day[$date_str] = 0;
            $revenue_by_day[$date_str] = 0;
        }
    }
} catch (PDOException $e) {
    // Handle error
}

// Get top products
$top_products = [];
try {
    $query = "SELECT p.product_name, 
                     p.category,
                     SUM(si.quantity) as total_quantity,
                     SUM(si.subtotal) as total_revenue,
                     COUNT(DISTINCT si.sale_id) as times_sold
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
              WHERE DATE(s.sale_date) BETWEEN ? AND ?
              AND s.payment_status = 'paid'
              AND s.sale_status = 'completed'
              GROUP BY p.product_id, p.product_name, p.category
              ORDER BY total_revenue DESC
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $top_products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Get sales by payment method
$sales_by_payment = [];
try {
    $query = "SELECT payment_method,
                     COUNT(*) as transaction_count,
                     SUM(final_amount) as total_amount,
                     AVG(final_amount) as avg_amount
              FROM EASYSALLES_SALES 
              WHERE DATE(sale_date) BETWEEN ? AND ?
              AND payment_status = 'paid'
              AND sale_status = 'completed'
              GROUP BY payment_method
              ORDER BY total_amount DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $sales_by_payment = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Get sales by staff
$sales_by_staff = [];
try {
    $query = "SELECT u.full_name, u.username,
                     COUNT(s.sale_id) as sales_count,
                     SUM(s.final_amount) as total_revenue,
                     AVG(s.final_amount) as avg_sale
              FROM EASYSALLES_SALES s
              JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
              WHERE DATE(s.sale_date) BETWEEN ? AND ?
              AND s.payment_status = 'paid'
              AND s.sale_status = 'completed'
              GROUP BY s.staff_id, u.full_name, u.username
              ORDER BY total_revenue DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $sales_by_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Get hourly sales pattern
$hourly_sales = [];
try {
    $query = "SELECT HOUR(sale_date) as hour,
                     COUNT(*) as sales_count,
                     SUM(final_amount) as total_revenue
              FROM EASYSALLES_SALES 
              WHERE DATE(sale_date) BETWEEN ? AND ?
              AND payment_status = 'paid'
              AND sale_status = 'completed'
              GROUP BY HOUR(sale_date)
              ORDER BY hour";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $hourly_data = $stmt->fetchAll();
    
    // Fill all hours
    for ($i = 0; $i < 24; $i++) {
        $found = false;
        foreach ($hourly_data as $hour) {
            if ($hour['hour'] == $i) {
                $hourly_sales[$i] = [
                    'count' => (int)$hour['sales_count'],
                    'revenue' => (float)$hour['total_revenue']
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $hourly_sales[$i] = ['count' => 0, 'revenue' => 0];
        }
    }
} catch (PDOException $e) {
    // Handle error
}

// Calculate growth metrics
$previous_period_from = date('Y-m-d', strtotime($date_from . ' -' . $date_range_days . ' days'));
$previous_period_to = date('Y-m-d', strtotime($date_from . ' -1 day'));

$previous_revenue = 0;
try {
    $query = "SELECT SUM(final_amount) as total_revenue
              FROM EASYSALLES_SALES 
              WHERE DATE(sale_date) BETWEEN ? AND ?
              AND payment_status = 'paid'
              AND sale_status = 'completed'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$previous_period_from, $previous_period_to]);
    $result = $stmt->fetch();
    $previous_revenue = $result['total_revenue'] ?? 0;
} catch (PDOException $e) {
    // Handle error
}

$revenue_growth = $previous_revenue > 0 ? (($total_revenue - $previous_revenue) / $previous_revenue) * 100 : 0;

// Get peak hours
$peak_hours = [];
if (!empty($hourly_sales)) {
    arsort($hourly_sales);
    $peak_hours = array_slice($hourly_sales, 0, 3, true);
}
?>

<style>
    .reports-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .page-title h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 0.5rem 0;
    }
    
    .page-title p {
        color: var(--text-muted);
        margin: 0;
    }
    
    .page-actions {
        display: flex;
        gap: 0.75rem;
    }
    
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
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
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
    
    /* Report Tabs */
    .report-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        background: var(--card-bg);
        padding: 0.5rem;
        border-radius: 12px;
        border: 1px solid var(--border);
    }
    
    .report-tab {
        flex: 1;
        padding: 1rem;
        border: none;
        background: none;
        color: var(--text-muted);
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .report-tab i {
        font-size: 1.2rem;
    }
    
    .report-tab.active {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    
    /* Filters Card */
    .filters-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
    }
    
    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .filters-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
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
    
    /* Report Sections */
    .report-section {
        margin-bottom: 2.5rem;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-light);
    }
    
    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title i {
        color: var(--primary);
    }
    
    /* KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }
    
    .kpi-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(to bottom, var(--primary), var(--secondary));
    }
    
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .kpi-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .kpi-title {
        font-size: 0.9rem;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }
    
    .kpi-value {
        font-family: 'Poppins', sans-serif;
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--text);
        margin: 0.5rem 0;
        line-height: 1;
    }
    
    .kpi-change {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }
    
    .kpi-change.positive {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .kpi-change.negative {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
    }
    
    /* Charts Container */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .chart-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .chart-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .chart-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    /* Tables */
    .tables-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
    }
    
    .table-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .table-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid var(--border-light);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 400px;
    }
    
    .table th {
        background: var(--table-header-bg);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text);
        border-bottom: 2px solid var(--border);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-light);
        color: var(--text);
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: var(--table-hover-bg);
    }
    
    .progress-bar {
        height: 8px;
        background: var(--border);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    
    /* Insights Grid */
    .insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .insight-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .insight-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .insight-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 1rem;
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }
    
    .insight-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.5rem;
    }
    
    .insight-text {
        font-size: 0.95rem;
        color: var(--text-muted);
        line-height: 1.6;
    }
    
    /* Date Range Display */
    .date-range-display {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid rgba(124, 58, 237, 0.1);
    }
    
    .date-range-text {
        font-size: 1rem;
        color: var(--text);
        font-weight: 500;
        text-align: center;
    }
    
    .date-range-dates {
        font-weight: 700;
        color: var(--primary);
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }
    
    /* Comparison Cards */
    .comparison-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    .comparison-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
    }
    
    .comparison-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .comparison-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
    }
    
    .comparison-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0.5rem;
    }
    
    .comparison-period {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    /* Export Options */
    .export-options {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .export-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: 2px solid var(--border);
        background: var(--card-bg);
        color: var(--text);
        cursor: pointer;
    }
    
    .export-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border-radius: 12px;
        border: 2px dashed var(--border);
        grid-column: 1 / -1;
    }
    
    .empty-state i {
        font-size: 3.5rem;
        color: var(--border);
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
</style>

<div class="reports-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h2>📈 Sales Reports & Analytics</h2>
            <p>Comprehensive sales analysis and performance insights</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Sales
            </a>
        </div>
    </div>

    <!-- Date Range Display -->
    <div class="date-range-display">
        <div class="date-range-text">
            📅 Analysis Period: <span class="date-range-dates">
                <?php echo date('M d, Y', strtotime($date_from)); ?>
            </span> to <span class="date-range-dates">
                <?php echo date('M d, Y', strtotime($date_to)); ?>
            </span>
            (<?php echo $date_range_days; ?> days)
        </div>
    </div>

    <!-- Report Tabs -->
    <div class="report-tabs">
        <button class="report-tab <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                onclick="setReportType('overview')">
            <i class="fas fa-chart-pie"></i>
            <span>Overview</span>
        </button>
        <button class="report-tab <?php echo $report_type == 'products' ? 'active' : ''; ?>" 
                onclick="setReportType('products')">
            <i class="fas fa-box"></i>
            <span>Products</span>
        </button>
        <button class="report-tab <?php echo $report_type == 'staff' ? 'active' : ''; ?>" 
                onclick="setReportType('staff')">
            <i class="fas fa-users"></i>
            <span>Staff</span>
        </button>
        <button class="report-tab <?php echo $report_type == 'comparison' ? 'active' : ''; ?>" 
                onclick="setReportType('comparison')">
            <i class="fas fa-exchange-alt"></i>
            <span>Comparison</span>
        </button>
        <button class="report-tab <?php echo $report_type == 'insights' ? 'active' : ''; ?>" 
                onclick="setReportType('insights')">
            <i class="fas fa-lightbulb"></i>
            <span>Insights</span>
        </button>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Report Filters
            </div>
            <button type="button" onclick="exportReport()" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fas fa-file-export"></i> Export Report
            </button>
        </div>
        
        <form method="GET" action="" id="reportFilter">
            <input type="hidden" name="report_type" id="reportType" value="<?php echo $report_type; ?>">
            
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
                    <label class="filter-label">Group By</label>
                    <select name="group_by" class="filter-control" id="groupBy">
                        <option value="daily" <?php echo $group_by == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $group_by == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $group_by == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Chart Type</label>
                    <select name="chart_type" class="filter-control" id="chartType">
                        <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart</option>
                        <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                        <option value="area" <?php echo $chart_type == 'area' ? 'selected' : ''; ?>>Area Chart</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px;">
                        <i class="fas fa-sync-alt"></i> Update Report
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($report_type == 'overview'): ?>
        <!-- Overview Report -->
        <div class="report-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Key Performance Indicators
                </div>
                <div class="export-options">
                    <button type="button" onclick="printReport()" class="export-btn">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" onclick="exportToPDF()" class="export-btn">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button type="button" onclick="exportToExcel()" class="export-btn">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-header">
                        <div class="kpi-title">Total Revenue</div>
                        <div class="kpi-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="kpi-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="kpi-change <?php echo $revenue_growth >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $revenue_growth >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo number_format(abs($revenue_growth), 1); ?>%
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                        vs previous period
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-header">
                        <div class="kpi-title">Total Transactions</div>
                        <div class="kpi-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="kpi-value"><?php echo number_format($total_transactions); ?></div>
                    <div style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.5rem;">
                        $<?php echo number_format($average_transaction, 2); ?> average
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-header">
                        <div class="kpi-title">Daily Average</div>
                        <div class="kpi-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="kpi-value">$<?php echo number_format($daily_average, 2); ?></div>
                    <div style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Over <?php echo $date_range_days; ?> days
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-header">
                        <div class="kpi-title">Peak Hour</div>
                        <div class="kpi-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="kpi-value">
                        <?php 
                        if (!empty($peak_hours)) {
                            $peak_hour = array_key_first($peak_hours);
                            echo date('g A', strtotime($peak_hour . ':00'));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Most sales activity
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="section-header" style="margin-top: 2.5rem;">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Sales Trends & Patterns
                </div>
            </div>
            
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Revenue Trend
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-chart-bar"></i>
                            Transaction Volume
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Methods
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-clock"></i>
                            Hourly Sales Pattern
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Data Tables -->
            <div class="section-header" style="margin-top: 2.5rem;">
                <div class="section-title">
                    <i class="fas fa-table"></i>
                    Detailed Breakdown
                </div>
            </div>
            
            <div class="tables-grid">
                <div class="table-card">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-money-bill-wave"></i>
                            Revenue by Payment Method
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Transactions</th>
                                    <th>Total Revenue</th>
                                    <th>Average</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_by_payment as $payment): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <?php 
                                            $payment_icons = [
                                                'cash' => 'money-bill',
                                                'card' => 'credit-card',
                                                'mobile_money' => 'mobile-alt',
                                                'credit' => 'hand-holding-usd'
                                            ];
                                            ?>
                                            <i class="fas fa-<?php echo $payment_icons[$payment['payment_method']] ?? 'money-bill'; ?>" 
                                               style="color: var(--primary);"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($payment['transaction_count']); ?></td>
                                    <td><strong>$<?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($payment['avg_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($total_revenue > 0): ?>
                                            <?php $share = ($payment['total_amount'] / $total_revenue) * 100; ?>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div class="progress-bar" style="flex: 1;">
                                                    <div class="progress-fill" style="width: <?php echo $share; ?>%;"></div>
                                                </div>
                                                <span><?php echo number_format($share, 1); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="table-card">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-users"></i>
                            Top Performing Staff
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                    <th>Average</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_by_staff as $staff): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></div>
                                    </td>
                                    <td><?php echo number_format($staff['sales_count']); ?></td>
                                    <td><strong>$<?php echo number_format($staff['total_revenue'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($staff['avg_sale'], 2); ?></td>
                                    <td>
                                        <?php if ($total_revenue > 0): ?>
                                            <?php $share = ($staff['total_revenue'] / $total_revenue) * 100; ?>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div class="progress-bar" style="flex: 1;">
                                                    <div class="progress-fill" style="width: <?php echo min($share * 2, 100); ?>%;"></div>
                                                </div>
                                                <span><?php echo number_format($share, 1); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'products'): ?>
        <!-- Products Report -->
        <div class="report-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-box"></i>
                    Product Performance Analysis
                </div>
            </div>
            
            <!-- Top Products Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-trophy"></i>
                        Top 10 Products by Revenue
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity Sold</th>
                                <th>Total Revenue</th>
                                <th>Times Sold</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                                        No product sales data available for this period.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $index => $product): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    </td>
                                    <td>
                                        <span style="padding: 0.25rem 0.75rem; background: rgba(124, 58, 237, 0.1); color: #7C3AED; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($product['total_quantity']); ?></td>
                                    <td><strong>$<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                    <td><?php echo number_format($product['times_sold']); ?></td>
                                    <td>
                                        <?php if ($index == 0): ?>
                                            <span style="padding: 0.25rem 0.75rem; background: rgba(245, 158, 11, 0.1); color: #F59E0B; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-crown"></i> Top Seller
                                            </span>
                                        <?php elseif ($index < 3): ?>
                                            <span style="padding: 0.25rem 0.75rem; background: rgba(107, 114, 128, 0.1); color: #6B7280; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                Top <?php echo $index + 1; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'staff'): ?>
        <!-- Staff Performance Report -->
        <div class="report-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Staff Performance Analysis
                </div>
            </div>
            
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-chart-bar"></i>
                            Revenue by Staff Member
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="staffRevenueChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Sales Distribution
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="staffDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Staff Performance Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-user-tie"></i>
                        Staff Performance Details
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Total Sales</th>
                                <th>Total Revenue</th>
                                <th>Average Sale</th>
                                <th>Revenue/Day</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_by_staff)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        <i class="fas fa-users-slash" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                                        No staff sales data available for this period.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_by_staff as $staff): ?>
                                <?php 
                                $revenue_per_day = $date_range_days > 0 ? $staff['total_revenue'] / $date_range_days : 0;
                                $performance_score = ($staff['avg_sale'] * $staff['sales_count']) / 1000; // Simple score
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></div>
                                    </td>
                                    <td><?php echo number_format($staff['sales_count']); ?></td>
                                    <td><strong>$<?php echo number_format($staff['total_revenue'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($staff['avg_sale'], 2); ?></td>
                                    <td>$<?php echo number_format($revenue_per_day, 2); ?></td>
                                    <td>
                                        <?php if ($performance_score > 20): ?>
                                            <span style="padding: 0.25rem 0.75rem; background: rgba(16, 185, 129, 0.1); color: #10B981; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-star"></i> Excellent
                                            </span>
                                        <?php elseif ($performance_score > 10): ?>
                                            <span style="padding: 0.25rem 0.75rem; background: rgba(59, 130, 246, 0.1); color: #3B82F6; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-chart-line"></i> Good
                                            </span>
                                        <?php else: ?>
                                            <span style="padding: 0.25rem 0.75rem; background: rgba(245, 158, 11, 0.1); color: #F59E0B; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-chart-bar"></i> Average
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'comparison'): ?>
        <!-- Comparison Report -->
        <div class="report-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-exchange-alt"></i>
                    Period Comparison Analysis
                </div>
            </div>
            
            <div class="comparison-grid">
                <div class="comparison-card">
                    <div class="comparison-header">
                        <div class="comparison-title">Current Period</div>
                        <div style="color: var(--primary);">
                            <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>
                        </div>
                    </div>
                    <div class="comparison-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="comparison-period">Total Revenue</div>
                    <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                        <?php echo number_format($total_transactions); ?> transactions<br>
                        $<?php echo number_format($average_transaction, 2); ?> average sale<br>
                        $<?php echo number_format($daily_average, 2); ?> daily average
                    </div>
                </div>
                
                <div class="comparison-card">
                    <div class="comparison-header">
                        <div class="comparison-title">Previous Period</div>
                        <div style="color: var(--text-muted);">
                            <?php echo date('M d, Y', strtotime($previous_period_from)); ?> - <?php echo date('M d, Y', strtotime($previous_period_to)); ?>
                        </div>
                    </div>
                    <div class="comparison-value">$<?php echo number_format($previous_revenue, 2); ?></div>
                    <div class="comparison-period">Total Revenue</div>
                    <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                        <?php echo $date_range_days; ?> days period<br>
                        Same duration comparison<br>
                        Previous <?php echo $date_range_days; ?> days
                    </div>
                </div>
                
                <div class="comparison-card">
                    <div class="comparison-header">
                        <div class="comparison-title">Growth Analysis</div>
                    </div>
                    <div class="comparison-value" style="color: <?php echo $revenue_growth >= 0 ? '#10B981' : '#EF4444'; ?>;">
                        <?php echo $revenue_growth >= 0 ? '+' : ''; ?><?php echo number_format($revenue_growth, 1); ?>%
                    </div>
                    <div class="comparison-period">Revenue Growth</div>
                    <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                        <?php if ($revenue_growth > 0): ?>
                            <span style="color: #10B981;">
                                <i class="fas fa-arrow-up"></i> Positive growth trend
                            </span>
                        <?php elseif ($revenue_growth < 0): ?>
                            <span style="color: #EF4444;">
                                <i class="fas fa-arrow-down"></i> Negative growth trend
                            </span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">
                                <i class="fas fa-minus"></i> No change
                            </span>
                        <?php endif; ?>
                        <br>
                        Change: $<?php echo number_format($total_revenue - $previous_revenue, 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="chart-card" style="margin-top: 2rem;">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Revenue Growth Trend
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type == 'insights'): ?>
        <!-- Insights Report -->
        <div class="report-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    Business Insights & Recommendations
                </div>
            </div>
            
            <div class="insights-grid">
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="insight-title">Revenue Trend</div>
                    <div class="insight-text">
                        <?php if ($revenue_growth > 10): ?>
                            Strong revenue growth of <?php echo number_format($revenue_growth, 1); ?>% compared to previous period.
                            Consider expanding high-performing product lines.
                        <?php elseif ($revenue_growth > 0): ?>
                            Steady revenue growth of <?php echo number_format($revenue_growth, 1); ?>%.
                            Focus on maintaining current strategies.
                        <?php elseif ($revenue_growth < -10): ?>
                            Revenue decline of <?php echo number_format(abs($revenue_growth), 1); ?>%.
                            Review pricing and promotional strategies.
                        <?php else: ?>
                            Revenue has remained stable. Consider introducing new promotions to boost growth.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="insight-title">Peak Hours Analysis</div>
                    <div class="insight-text">
                        <?php if (!empty($peak_hours)): ?>
                            Peak sales hours: 
                            <?php 
                            $peak_times = [];
                            foreach (array_keys($peak_hours) as $hour) {
                                $peak_times[] = date('g A', strtotime($hour . ':00'));
                            }
                            echo implode(', ', $peak_times);
                            ?>.
                            Consider scheduling extra staff during these hours.
                        <?php else: ?>
                            Sales distribution is relatively even throughout the day.
                            Maintain consistent staffing levels.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="insight-title">Payment Method Trends</div>
                    <div class="insight-text">
                        <?php if (!empty($sales_by_payment)): 
                            $top_method = $sales_by_payment[0];
                        ?>
                            <?php echo ucfirst(str_replace('_', ' ', $top_method['payment_method'])); ?> is the most popular payment method,
                            accounting for 
                            <?php 
                            $share = ($top_method['total_amount'] / $total_revenue) * 100;
                            echo number_format($share, 1);
                            ?>% of transactions.
                        <?php else: ?>
                            Payment methods are evenly distributed. Ensure all payment options are properly supported.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="insight-title">Staff Performance</div>
                    <div class="insight-text">
                        <?php if (!empty($sales_by_staff)): 
                            $top_staff = $sales_by_staff[0];
                            $share = ($top_staff['total_revenue'] / $total_revenue) * 100;
                        ?>
                            Top performer: <?php echo htmlspecialchars($top_staff['full_name'] ?: $top_staff['username']); ?>,
                            generating <?php echo number_format($share, 1); ?>% of total revenue.
                            Consider sharing best practices with other staff.
                        <?php else: ?>
                            Staff performance data unavailable. Ensure all sales are properly recorded.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="insight-title">Product Performance</div>
                    <div class="insight-text">
                        <?php if (!empty($top_products) && count($top_products) >= 3): ?>
                            Top 3 products account for significant revenue. 
                            Ensure adequate stock levels for these items.
                            Consider bundling or promoting related products.
                        <?php else: ?>
                            Product sales are diverse. Continue offering a wide range of products to meet customer needs.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="insight-title">Seasonal Trends</div>
                    <div class="insight-text">
                        Based on the selected period, consider:
                        <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: var(--text-muted);">
                            <li>Planning inventory for upcoming seasonal changes</li>
                            <li>Adjusting staffing based on historical patterns</li>
                            <li>Creating targeted promotions for slower periods</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

<script>
    // Set report type
    function setReportType(type) {
        document.getElementById('reportType').value = type;
        document.getElementById('reportFilter').submit();
    }
    
    // Export report
    function exportReport() {
        const reportType = document.getElementById('reportType').value;
        const dateFrom = document.querySelector('input[name="date_from"]').value;
        const dateTo = document.querySelector('input[name="date_to"]').value;
        
        window.open(`export_report.php?type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}`, '_blank');
    }
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportToPDF() {
        showToast('PDF export feature coming soon!', 'info');
    }
    
    // Export to Excel
    function exportToExcel() {
        showToast('Excel export feature coming soon!', 'info');
    }
    
    // Initialize charts when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($report_type == 'overview'): ?>
            initializeOverviewCharts();
        <?php elseif ($report_type == 'staff'): ?>
            initializeStaffCharts();
        <?php elseif ($report_type == 'comparison'): ?>
            initializeComparisonChart();
        <?php endif; ?>
        
        // Set max dates for date inputs
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = today;
        });
    });
    
    // Overview Charts
    function initializeOverviewCharts() {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: '<?php echo $chart_type; ?>',
            data: {
                labels: [<?php echo implode(',', array_map(function($date) { return "'" . date('M d', strtotime($date)) . "'"; }, array_keys($revenue_by_day))); ?>],
                datasets: [{
                    label: 'Daily Revenue',
                    data: [<?php echo implode(',', array_values($revenue_by_day)); ?>],
                    borderColor: '#7C3AED',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Sales Volume Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($date) { return "'" . date('M d', strtotime($date)) . "'"; }, array_keys($sales_by_day))); ?>],
                datasets: [{
                    label: 'Transactions',
                    data: [<?php echo implode(',', array_values($sales_by_day)); ?>],
                    backgroundColor: '#10B981',
                    borderColor: '#10B981',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($sales_by_payment as $payment): ?>
                        '<?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($sales_by_payment as $payment): ?>
                            <?php echo $payment['total_amount']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#10B981', // Cash - green
                        '#3B82F6', // Card - blue
                        '#8B5CF6', // Mobile Money - purple
                        '#F59E0B'  // Credit - orange
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Hourly Sales Pattern
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyChart = new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00'),
                datasets: [{
                    label: 'Revenue per Hour',
                    data: [
                        <?php 
                        for ($i = 0; $i < 24; $i++) {
                            echo $hourly_sales[$i]['revenue'];
                            if ($i < 23) echo ',';
                        }
                        ?>
                    ],
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Staff Charts
    function initializeStaffCharts() {
        // Staff Revenue Chart
        const staffRevenueCtx = document.getElementById('staffRevenueChart').getContext('2d');
        const staffRevenueChart = new Chart(staffRevenueCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($sales_by_staff as $staff): ?>
                        '<?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Revenue',
                    data: [
                        <?php foreach ($sales_by_staff as $staff): ?>
                            <?php echo $staff['total_revenue']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#7C3AED',
                    borderColor: '#7C3AED',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Staff Distribution Chart
        const staffDistributionCtx = document.getElementById('staffDistributionChart').getContext('2d');
        const staffDistributionChart = new Chart(staffDistributionCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($sales_by_staff as $staff): ?>
                        '<?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($sales_by_staff as $staff): ?>
                            <?php echo $staff['total_revenue']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#7C3AED', '#10B981', '#3B82F6', '#F59E0B', '#EF4444',
                        '#8B5CF6', '#06B6D4', '#EC4899', '#84CC16', '#F97316'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
    
    // Comparison Chart
    function initializeComparisonChart() {
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        const comparisonChart = new Chart(comparisonCtx, {
            type: 'line',
            data: {
                labels: ['Previous Period', 'Current Period'],
                datasets: [{
                    label: 'Total Revenue',
                    data: [<?php echo $previous_revenue; ?>, <?php echo $total_revenue; ?>],
                    borderColor: '#7C3AED',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
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
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        // Add styles if not already added
        if (!document.getElementById('toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                .toast {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 8px;
                    padding: 1rem 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 9999;
                    min-width: 300px;
                    animation: slideIn 0.3s ease;
                }
                .toast-success { border-left: 4px solid #10B981; }
                .toast-error { border-left: 4px solid #EF4444; }
                .toast-info { border-left: 4px solid #3B82F6; }
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                .toast-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: var(--text-muted);
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
</script>

<?php require_once '../includes/footer.php'; ?>