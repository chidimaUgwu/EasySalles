<?php
// admin/reports/staff.php
$page_title = "Staff Performance Reports";
require_once '../includes/header.php';

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$sort_by = $_GET['sort_by'] ?? 'revenue';
$staff_id = $_GET['staff_id'] ?? '';

// Get all staff
$all_staff = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name, status FROM EASYSALLES_USERS WHERE role = 2 ORDER BY username");
    $all_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Build query
$query = "SELECT 
    u.user_id,
    u.username,
    u.full_name,
    u.status,
    u.shift_start,
    u.shift_end,
    u.shift_days,
    u.salary,
    u.hire_date,
    COUNT(s.sale_id) as total_sales,
    COALESCE(SUM(s.final_amount), 0) as total_revenue,
    COALESCE(AVG(s.final_amount), 0) as avg_sale_value,
    COUNT(DISTINCT s.customer_name) as customers_served,
    MIN(s.sale_date) as first_sale,
    MAX(s.sale_date) as last_sale
    FROM EASYSALLES_USERS u
    LEFT JOIN EASYSALLES_SALES s ON u.user_id = s.staff_id 
        AND DATE(s.sale_date) BETWEEN ? AND ?
    WHERE u.role = 2";

$params = [$date_from, $date_to];

if (!empty($staff_id)) {
    $query .= " AND u.user_id = ?";
    $params[] = $staff_id;
}

$query .= " GROUP BY u.user_id";

// Add sorting
$sort_options = [
    'revenue' => 'total_revenue DESC',
    'sales' => 'total_sales DESC',
    'average' => 'avg_sale_value DESC',
    'name' => 'u.username ASC',
    'customers' => 'customers_served DESC',
    'date' => 'last_sale DESC'
];

$query .= " ORDER BY " . ($sort_options[$sort_by] ?? 'total_revenue DESC');

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staff_performance = $stmt->fetchAll();
} catch (PDOException $e) {
    $staff_performance = [];
}

// Calculate summary stats with default values
$summary = [
    'total_staff' => count($staff_performance),
    'active_staff' => count(array_filter($staff_performance, fn($staff) => ($staff['status'] ?? '') === 'active')),
    'total_revenue' => array_sum(array_column($staff_performance, 'total_revenue')),
    'total_sales' => array_sum(array_column($staff_performance, 'total_sales')),
    'avg_revenue_per_staff' => count($staff_performance) > 0 ? array_sum(array_column($staff_performance, 'total_revenue')) / count($staff_performance) : 0
];

// Get performance chart data
$chart_data = [];
try {
    $stmt = $pdo->prepare("SELECT 
        u.username,
        DATE_FORMAT(s.sale_date, '%Y-%m') as month,
        SUM(s.final_amount) as revenue
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        AND u.role = 2
        GROUP BY u.user_id, DATE_FORMAT(s.sale_date, '%Y-%m')
        ORDER BY month ASC");
    $stmt->execute([$date_from, $date_to]);
    $chart_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $chart_data = [];
}

// Get top performer details
$top_performer = null;
if (!empty($staff_performance)) {
    usort($staff_performance, function($a, $b) {
        return ($b['total_revenue'] ?? 0) <=> ($a['total_revenue'] ?? 0);
    });
    $top_performer = $staff_performance[0] ?? null;
}

// Get staff efficiency (revenue per hour if shift data exists)
foreach ($staff_performance as &$staff) {
    if (!empty($staff['shift_start']) && !empty($staff['shift_end']) && ($staff['total_revenue'] ?? 0) > 0) {
        $start = strtotime($staff['shift_start']);
        $end = strtotime($staff['shift_end']);
        if ($start && $end) {
            $hours_per_day = ($end - $start) / 3600;
            $working_days = 22; // Assuming 22 working days per month
            
            if ($hours_per_day > 0 && $working_days > 0) {
                $staff['revenue_per_hour'] = ($staff['total_revenue'] ?? 0) / ($hours_per_day * $working_days);
            } else {
                $staff['revenue_per_hour'] = 0;
            }
        } else {
            $staff['revenue_per_hour'] = 0;
        }
    } else {
        $staff['revenue_per_hour'] = 0;
    }
    
    // Ensure all required fields have values
    $staff['total_revenue'] = $staff['total_revenue'] ?? 0;
    $staff['total_sales'] = $staff['total_sales'] ?? 0;
    $staff['avg_sale_value'] = $staff['avg_sale_value'] ?? 0;
    $staff['customers_served'] = $staff['customers_served'] ?? 0;
    $staff['status'] = $staff['status'] ?? 'active';
}
unset($staff); // Break reference
?>

<style>
    /* Reset and base styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    

    .single-column-container {
        width: 80%;
        max-width: 1200px;
        margin: 0 auto;
        padding: -10px;
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
    }
    
    /* Summary Stats */
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
    
    /* Performance Chart */
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
    }
    
    .chart-container {
        height: 300px;
        position: relative;
    }
    
    /* Rankings */
    .rankings-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .rankings-list {
        display: grid;
        gap: 1rem;
    }
    
    .ranking-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg);
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .ranking-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .ranking-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .ranking-medal {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .ranking-details {
        flex: 1;
    }
    
    .ranking-name {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
    }
    
    .ranking-meta {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    .ranking-stats {
        text-align: right;
    }
    
    .ranking-revenue {
        color: var(--success);
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 0.25rem;
    }
    
    .ranking-sales {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    /* Performance Table */
    .performance-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
        overflow-x: auto;
    }
    
    .performance-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .performance-table th {
        background: var(--table-header-bg);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text);
        border-bottom: 2px solid var(--border);
        font-size: 0.9rem;
        white-space: nowrap;
    }
    
    .performance-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-light);
        color: var(--text);
        vertical-align: middle;
    }
    
    .performance-table tr:hover {
        background: var(--table-hover-bg);
    }
    
    /* Staff Info */
    .staff-info {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .staff-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .staff-details {
        min-width: 0;
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
    
    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .status-inactive {
        background: rgba(245, 158, 11, 0.1);
        color: #F59E0B;
    }
    
    .status-suspended {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
    }
    
    /* Metrics */
    .metric-revenue {
        color: var(--success);
        font-weight: 600;
    }
    
    .metric-average {
        color: var(--primary);
        font-weight: 600;
    }
    
    .metric-hourly {
        color: var(--accent);
        font-weight: 600;
    }
    
    /* Actions */
    .table-actions {
        display: flex;
        gap: 0.3rem;
    }
    
    .table-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 2px solid transparent;
        background: none;
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .table-btn:hover {
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: rgba(59, 130, 246, 0.1);
        border-color: rgba(59, 130, 246, 0.2);
        color: #3B82F6;
    }
    
    .btn-report {
        background: rgba(124, 58, 237, 0.1);
        border-color: rgba(124, 58, 237, 0.2);
        color: #7C3AED;
    }
    
    .btn-edit {
        background: rgba(16, 185, 129, 0.1);
        border-color: rgba(16, 185, 129, 0.2);
        color: #10B981;
    }
    
    /* Insights */
    .insights-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }
    
    .insights-grid {
        display: grid;
        gap: 1.5rem;
    }
    
    .insight-card {
        background: var(--bg);
        border-radius: 10px;
        padding: 1.5rem;
    }
    
    .insight-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 1rem;
    }
    
    /* Progress Bar */
    .progress-bar {
        height: 10px;
        background: var(--border);
        border-radius: 5px;
        overflow: hidden;
        margin: 0.5rem 0;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 5px;
        transition: width 1s ease;
    }
    
    /* Quick Actions */
    .actions-section {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--border);
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .action-btn {
        padding: 1rem;
        background: var(--bg);
        border-radius: 10px;
        border: 2px solid var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        color: var(--text);
        transition: all 0.3s ease;
        cursor: pointer;
        text-align: center;
    }
    
    .action-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-3px);
    }
    
    .action-btn i {
        font-size: 1.5rem;
        color: var(--primary);
    }
    
    .action-label {
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    /* Export Options */
    .export-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 0.75rem;
    }
    
    .export-btn {
        padding: 0.75rem;
        background: var(--bg);
        border-radius: 8px;
        border: 2px solid var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .export-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .export-btn i {
        font-size: 1.25rem;
    }
    
    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
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
        .single-column-container {
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
        
        .actions-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .export-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .performance-section {
            padding: 1rem;
        }
        
        .performance-table {
            font-size: 0.9rem;
        }
        
        .performance-table th,
        .performance-table td {
            padding: 0.75rem;
        }
    }
</style>

<div class="single-column-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h2>📊 Staff Performance Reports</h2>
            <p>Monitor and analyze staff sales performance and productivity</p>
        </div>
        <div class="page-actions">
            <button onclick="exportStaffReport()" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> Export Report
            </button>
            <button onclick="printStaffReport()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <h3 class="section-title"><i class="fas fa-filter"></i> Report Filters</h3>
        
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
                <label class="filter-label">Staff Member</label>
                <select name="staff_id" class="filter-control">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                    <option value="<?php echo $staff['user_id']; ?>" 
                        <?php echo $staff_id == $staff['user_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Sort By</label>
                <select name="sort_by" class="filter-control">
                    <option value="revenue" <?php echo $sort_by == 'revenue' ? 'selected' : ''; ?>>Revenue (High to Low)</option>
                    <option value="sales" <?php echo $sort_by == 'sales' ? 'selected' : ''; ?>>Sales Count</option>
                    <option value="average" <?php echo $sort_by == 'average' ? 'selected' : ''; ?>>Average Sale Value</option>
                    <option value="customers" <?php echo $sort_by == 'customers' ? 'selected' : ''; ?>>Customers Served</option>
                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="date" <?php echo $sort_by == 'date' ? 'selected' : ''; ?>>Last Sale Date</option>
                </select>
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="staff.php" class="btn btn-outline">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="summary-stats">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo number_format($summary['total_staff']); ?></h3>
                <p class="stat-label">Total Staff</p>
                <small class="text-muted">
                    <?php echo $summary['active_staff']; ?> active
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">$<?php echo number_format($summary['total_revenue'], 0); ?></h3>
                <p class="stat-label">Total Revenue</p>
                <small class="text-muted">
                    Avg: $<?php echo number_format($summary['avg_revenue_per_staff'], 0); ?> per staff
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(6, 182, 212, 0.1); color: #06B6D4;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo number_format($summary['total_sales']); ?></h3>
                <p class="stat-label">Total Sales</p>
                <small class="text-muted">
                    <?php echo count($staff_performance) > 0 ? round($summary['total_sales'] / count($staff_performance)) : 0; ?> avg per staff
                </small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">
                    <?php if ($top_performer): ?>
                        <?php echo htmlspecialchars($top_performer['full_name'] ?: $top_performer['username']); ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </h3>
                <p class="stat-label">Top Performer</p>
                <small class="text-muted" style="color: var(--success);">
                    <?php if ($top_performer): ?>
                        $<?php echo number_format($top_performer['total_revenue'] ?? 0, 0); ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="chart-section">
        <div class="chart-header">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Performance Comparison</h3>
            <button onclick="togglePerformanceChart()" class="btn btn-outline">
                <i class="fas fa-exchange-alt"></i> Switch Chart Type
            </button>
        </div>
        <div class="chart-container">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>

    <!-- Rankings -->
    <div class="rankings-section">
        <h3 class="section-title"><i class="fas fa-trophy"></i> Performance Rankings</h3>
        
        <div class="rankings-list">
            <?php if (empty($staff_performance)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Performance Data</h4>
                    <p>No staff performance data available</p>
                </div>
            <?php else: ?>
                <?php 
                $rank = 1;
                foreach ($staff_performance as $staff): 
                    if ($rank > 5) break; // Show only top 5
                    
                    $medal_colors = [
                        1 => 'linear-gradient(135deg, #FFD700, #FFA500)',
                        2 => 'linear-gradient(135deg, #C0C0C0, #A0A0A0)',
                        3 => 'linear-gradient(135deg, #CD7F32, #B5651D)',
                        4 => 'linear-gradient(135deg, #7C3AED, #6D28D9)',
                        5 => 'linear-gradient(135deg, #06B6D4, #0891B2)'
                    ];
                    
                    $medal_text_colors = [
                        1 => '#FFFFFF',
                        2 => '#FFFFFF',
                        3 => '#FFFFFF',
                        4 => '#FFFFFF',
                        5 => '#FFFFFF'
                    ];
                ?>
                <div class="ranking-item">
                    <div class="ranking-info">
                        <div class="ranking-medal" style="background: <?php echo $medal_colors[$rank] ?? 'var(--bg)'; ?>; color: <?php echo $medal_text_colors[$rank] ?? 'var(--text)'; ?>;">
                            <?php echo $rank; ?>
                        </div>
                        <div class="ranking-details">
                            <div class="ranking-name">
                                <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                            </div>
                            <div class="ranking-meta">
                                <?php echo $staff['customers_served']; ?> customers served • 
                                <span class="status-badge status-<?php echo $staff['status']; ?>" style="font-size: 0.8rem;">
                                    <?php echo ucfirst($staff['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="ranking-stats">
                        <div class="ranking-revenue">
                            $<?php echo number_format($staff['total_revenue'], 0); ?>
                        </div>
                        <div class="ranking-sales">
                            <?php echo $staff['total_sales']; ?> sales
                        </div>
                    </div>
                </div>
                <?php 
                $rank++;
                endforeach; 
                ?>
                
                <?php if (count($staff_performance) > 5): ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="#performance-table" class="btn btn-outline" style="width: 100%;">
                        View All Staff Members <i class="fas fa-arrow-down"></i>
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="performance-section" id="performance-table">
        <h3 class="section-title"><i class="fas fa-table"></i> Staff Performance Details</h3>
        
        <div style="overflow-x: auto;">
            <?php if (empty($staff_performance)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h4>No Performance Data</h4>
                    <p>No staff performance data found for the selected period</p>
                </div>
            <?php else: ?>
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Status</th>
                            <th>Sales Count</th>
                            <th>Total Revenue</th>
                            <th>Avg Sale Value</th>
                            <th>Customers</th>
                            <th>Revenue/Hour</th>
                            <th>Last Sale</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_performance as $staff): ?>
                        <tr>
                            <td>
                                <div class="staff-info">
                                    <div class="staff-avatar">
                                        <?php echo strtoupper(substr($staff['username'], 0, 1)); ?>
                                    </div>
                                    <div class="staff-details">
                                        <div class="staff-name">
                                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                                        </div>
                                        <div class="staff-username">
                                            @<?php echo htmlspecialchars($staff['username']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $staff['status']; ?>">
                                    <?php echo ucfirst($staff['status']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo number_format($staff['total_sales']); ?></strong>
                            </td>
                            <td>
                                <div class="metric-revenue">
                                    $<?php echo number_format($staff['total_revenue'], 2); ?>
                                </div>
                            </td>
                            <td>
                                <div class="metric-average">
                                    $<?php echo number_format($staff['avg_sale_value'] ?: 0, 2); ?>
                                </div>
                            </td>
                            <td>
                                <?php echo number_format($staff['customers_served']); ?>
                            </td>
                            <td>
                                <div class="metric-hourly">
                                    $<?php echo number_format($staff['revenue_per_hour'] ?? 0, 1); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($staff['last_sale']): ?>
                                    <?php echo date('M d, Y', strtotime($staff['last_sale'])); ?><br>
                                    <small style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo date('H:i', strtotime($staff['last_sale'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">No sales</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button onclick="viewStaffDetails(<?php echo $staff['user_id']; ?>)" 
                                            class="table-btn btn-view" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="generateStaffReport(<?php echo $staff['user_id']; ?>)" 
                                            class="table-btn btn-report" 
                                            title="Generate Report">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <a href="../users/edit.php?id=<?php echo $staff['user_id']; ?>" 
                                       class="table-btn btn-edit" 
                                       title="Edit Staff">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Insights -->
    <div class="insights-section">
        <h3 class="section-title"><i class="fas fa-chart-pie"></i> Performance Insights</h3>
        
        <?php if (!empty($staff_performance)): ?>
            <?php
            $active_staff = array_filter($staff_performance, fn($s) => ($s['status'] ?? '') === 'active');
            $inactive_staff = array_filter($staff_performance, fn($s) => ($s['status'] ?? '') !== 'active');
            
            $top_3_revenue = array_sum(array_slice(array_column($staff_performance, 'total_revenue'), 0, 3));
            $total_revenue = array_sum(array_column($staff_performance, 'total_revenue'));
            $top_3_percentage = $total_revenue > 0 ? ($top_3_revenue / $total_revenue) * 100 : 0;
            
            $avg_customers_per_staff = count($staff_performance) > 0 ? round(array_sum(array_column($staff_performance, 'customers_served')) / count($staff_performance)) : 0;
            ?>
            
            <div class="insights-grid">
                <div class="insight-card">
                    <h4 class="insight-title">Revenue Distribution</h4>
                    <p style="color: var(--text-muted); margin-bottom: 0.75rem;">
                        Top 3 staff members generate 
                        <strong style="color: var(--success);">
                            <?php echo round($top_3_percentage); ?>%
                        </strong> of total revenue
                    </p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $top_3_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="insight-card">
                    <h4 class="insight-title">Performance Metrics</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div>
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">Avg Revenue per Staff</div>
                            <div style="font-weight: 700; color: var(--text); font-size: 1.2rem;">
                                $<?php echo number_format($summary['avg_revenue_per_staff'], 0); ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">Avg Customers per Staff</div>
                            <div style="font-weight: 700; color: var(--text); font-size: 1.2rem;">
                                <?php echo $avg_customers_per_staff; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="insight-card">
                    <h4 class="insight-title">Staff Status Overview</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 12px; height: 12px; background: var(--success); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 600; color: var(--text);">Active</div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">
                                    <?php echo count($active_staff); ?> staff
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 12px; height: 12px; background: var(--error); border-radius: 50%;"></div>
                            <div>
                                <div style="font-weight: 600; color: var(--text);">Inactive</div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">
                                    <?php echo count($inactive_staff); ?> staff
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-pie"></i>
                <h4>No Insights Available</h4>
                <p>No performance data to generate insights</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="actions-section">
        <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
        
        <div class="actions-grid">
            <button onclick="createPerformanceIncentive()" class="action-btn">
                <i class="fas fa-award"></i>
                <span class="action-label">Create Incentive</span>
            </button>
            
            <button onclick="scheduleStaffTraining()" class="action-btn">
                <i class="fas fa-graduation-cap"></i>
                <span class="action-label">Schedule Training</span>
            </button>
            
            <a href="../users/index.php?filter=staff" class="action-btn">
                <i class="fas fa-user-cog"></i>
                <span class="action-label">Manage Staff</span>
            </a>
            
            <a href="../schedule/index.php" class="action-btn">
                <i class="fas fa-calendar-alt"></i>
                <span class="action-label">View Schedule</span>
            </a>
        </div>
        
        <div style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
            <button onclick="compareStaffPerformance()" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-chart-bar"></i> Compare Staff Performance
            </button>
        </div>
        
        <h4 class="section-title"><i class="fas fa-download"></i> Export Options</h4>
        <div class="export-grid">
            <button onclick="exportAsPDF()" class="export-btn">
                <i class="fas fa-file-pdf"></i>
                <span>PDF</span>
            </button>
            <button onclick="exportAsCSV()" class="export-btn">
                <i class="fas fa-file-csv"></i>
                <span>CSV</span>
            </button>
            <button onclick="exportAsExcel()" class="export-btn">
                <i class="fas fa-file-excel"></i>
                <span>Excel</span>
            </button>
        </div>
    </div>
</div>

<script>
    let performanceChart = null;
    let chartType = 'bar';
    
    function initializePerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;
        
        <?php if (!empty($staff_performance)): ?>
        const staffNames = <?php echo json_encode(array_map(function($s) {
            return $s['full_name'] ?: $s['username'];
        }, array_slice($staff_performance, 0, 8))); ?>;
        
        const revenues = <?php echo json_encode(array_map(function($s) {
            return $s['total_revenue'];
        }, array_slice($staff_performance, 0, 8))); ?>;
        
        const salesCount = <?php echo json_encode(array_map(function($s) {
            return $s['total_sales'];
        }, array_slice($staff_performance, 0, 8))); ?>;
        <?php else: ?>
        const staffNames = ['No Data'];
        const revenues = [0];
        const salesCount = [0];
        <?php endif; ?>
        
        // Destroy existing chart if it exists
        if (performanceChart) {
            performanceChart.destroy();
        }
        
        performanceChart = new Chart(ctx, {
            type: chartType,
            data: {
                labels: staffNames,
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenues,
                    backgroundColor: chartType === 'bar' ? 'rgba(124, 58, 237, 0.6)' : 'rgba(124, 58, 237, 0.1)',
                    borderColor: 'rgba(124, 58, 237, 1)',
                    borderWidth: chartType === 'bar' ? 1 : 3,
                    fill: chartType === 'line',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Sales Count',
                    data: salesCount,
                    backgroundColor: chartType === 'bar' ? 'rgba(6, 182, 212, 0.6)' : 'rgba(6, 182, 212, 0.1)',
                    borderColor: 'rgba(6, 182, 212, 1)',
                    borderWidth: chartType === 'bar' ? 1 : 3,
                    fill: chartType === 'line',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function togglePerformanceChart() {
        chartType = chartType === 'bar' ? 'line' : 'bar';
        initializePerformanceChart();
        showToast(`Switched to ${chartType} chart`, 'info');
    }
    
    function exportStaffReport() {
        const reportData = {
            period: `<?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>`,
            summary: <?php echo json_encode($summary); ?>,
            staffPerformance: <?php echo json_encode($staff_performance); ?>,
            generated: new Date().toLocaleString()
        };
        
        const dataStr = JSON.stringify(reportData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const fileName = `staff_performance_report_<?php echo date('Y-m-d'); ?>.json`;
        
        const link = document.createElement('a');
        link.setAttribute('href', dataUri);
        link.setAttribute('download', fileName);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('Staff performance report exported', 'success');
    }
    
    function printStaffReport() {
        window.print();
    }
    
    function viewStaffDetails(userId) {
        window.location.href = `../users/view.php?id=${userId}`;
    }
    
    function generateStaffReport(userId) {
        showToast(`Generating report for staff ID: ${userId}...`, 'info');
        setTimeout(() => {
            showToast('Staff report generated successfully', 'success');
        }, 2000);
    }
    
    function createPerformanceIncentive() {
        alert('Create Incentive feature would open a form here');
    }
    
    function scheduleStaffTraining() {
        alert('Schedule Training feature would open a form here');
    }
    
    function compareStaffPerformance() {
        alert('Compare Performance feature would open a comparison view here');
    }
    
    function exportAsPDF() {
        showToast('Generating PDF report...', 'info');
        setTimeout(() => {
            showToast('PDF report ready for download', 'success');
        }, 3000);
    }
    
    function exportAsCSV() {
        <?php if (!empty($staff_performance)): ?>
        const csvData = [
            ['Staff Name', 'Username', 'Status', 'Total Sales', 'Total Revenue', 'Avg Sale', 'Customers Served', 'Last Sale']
        ];
        
        <?php foreach ($staff_performance as $staff): ?>
        csvData.push([
            `<?php echo addslashes($staff['full_name'] ?: $staff['username']); ?>`,
            `<?php echo addslashes($staff['username']); ?>`,
            `<?php echo $staff['status']; ?>`,
            `<?php echo $staff['total_sales']; ?>`,
            `<?php echo $staff['total_revenue']; ?>`,
            `<?php echo $staff['avg_sale_value']; ?>`,
            `<?php echo $staff['customers_served']; ?>`,
            `<?php echo $staff['last_sale'] ? date('Y-m-d', strtotime($staff['last_sale'])) : ''; ?>`
        ]);
        <?php endforeach; ?>
        
        const csvContent = csvData.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `staff_performance_<?php echo date('Y-m-d'); ?>.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('CSV report exported', 'success');
        <?php else: ?>
        showToast('No data to export', 'warning');
        <?php endif; ?>
    }
    
    function exportAsExcel() {
        showToast('Generating Excel report...', 'info');
        setTimeout(() => {
            showToast('Excel report ready for download', 'success');
        }, 3000);
    }
    
    function showToast(message, type = 'info') {
        // Your existing toast implementation
        console.log(`${type}: ${message}`);
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializePerformanceChart();
        
        // Set max dates for date inputs
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = today;
        });
        
        // Handle form submission
        const applyBtn = document.querySelector('.filter-actions .btn-primary');
        const resetBtn = document.querySelector('.filter-actions .btn-outline');
        
        applyBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('date_from', document.querySelector('input[name="date_from"]').value);
            formData.append('date_to', document.querySelector('input[name="date_to"]').value);
            formData.append('staff_id', document.querySelector('select[name="staff_id"]').value);
            formData.append('sort_by', document.querySelector('select[name="sort_by"]').value);
            
            const params = new URLSearchParams(formData).toString();
            window.location.href = `staff.php?${params}`;
        });
        
        // Handle date validation
        const dateFrom = document.querySelector('input[name="date_from"]');
        const dateTo = document.querySelector('input[name="date_to"]');
        
        dateFrom.addEventListener('change', function() {
            if (dateTo.value && new Date(dateTo.value) < new Date(this.value)) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (dateFrom.value && new Date(dateFrom.value) > new Date(this.value)) {
                dateFrom.value = this.value;
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>