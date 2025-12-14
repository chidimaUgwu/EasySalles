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
    SUM(s.final_amount) as total_revenue,
    AVG(s.final_amount) as avg_sale_value,
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

// Calculate summary stats
$summary = [
    'total_staff' => count($staff_performance),
    'active_staff' => count(array_filter($staff_performance, fn($staff) => $staff['status'] === 'active')),
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
        return $b['total_revenue'] <=> $a['total_revenue'];
    });
    $top_performer = $staff_performance[0];
}

// Get staff efficiency (revenue per hour if shift data exists)
foreach ($staff_performance as &$staff) {
    if ($staff['shift_start'] && $staff['shift_end'] && $staff['total_revenue'] > 0) {
        $start = strtotime($staff['shift_start']);
        $end = strtotime($staff['shift_end']);
        $hours_per_day = ($end - $start) / 3600;
        $working_days = 22; // Assuming 22 working days per month
        
        if ($hours_per_day > 0 && $working_days > 0) {
            $staff['revenue_per_hour'] = $staff['total_revenue'] / ($hours_per_day * $working_days);
        } else {
            $staff['revenue_per_hour'] = 0;
        }
    } else {
        $staff['revenue_per_hour'] = 0;
    }
}
unset($staff); // Break reference
?>

<div class="page-header">
    <div class="page-title">
        <h2>Staff Performance Reports</h2>
        <p>Monitor and analyze staff sales performance and productivity</p>
    </div>
    <div class="page-actions">
        <button onclick="exportStaffReport()" class="btn btn-secondary">
            <i class="fas fa-file-export"></i> Export Report
        </button>
        <button onclick="printStaffReport()" class="btn btn-outline" style="margin-left: 0.5rem;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Report Filters</h3>
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
                    <label class="form-label">Staff Member</label>
                    <select name="staff_id" class="form-control">
                        <option value="">All Staff</option>
                        <?php foreach ($all_staff as $staff): ?>
                        <option value="<?php echo $staff['user_id']; ?>" 
                            <?php echo $staff_id == $staff['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-control">
                        <option value="revenue" <?php echo $sort_by == 'revenue' ? 'selected' : ''; ?>>Revenue (High to Low)</option>
                        <option value="sales" <?php echo $sort_by == 'sales' ? 'selected' : ''; ?>>Sales Count</option>
                        <option value="average" <?php echo $sort_by == 'average' ? 'selected' : ''; ?>>Average Sale Value</option>
                        <option value="customers" <?php echo $sort_by == 'customers' ? 'selected' : ''; ?>>Customers Served</option>
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="date" <?php echo $sort_by == 'date' ? 'selected' : ''; ?>>Last Sale Date</option>
                    </select>
                </div>
            </div>
            
            <div class="col-12" style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button type="button" onclick="window.location.href='staff.php'" class="btn btn-outline">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($summary['total_staff']); ?></h3>
            <p>Total Staff</p>
            <small class="text-muted">
                <?php echo $summary['active_staff']; ?> active
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($summary['total_revenue'], 0); ?></h3>
            <p>Total Revenue</p>
            <small class="text-muted">
                Avg: $<?php echo number_format($summary['avg_revenue_per_staff'], 0); ?> per staff
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($summary['total_sales']); ?></h3>
            <p>Total Sales</p>
            <small class="text-muted">
                <?php echo count($staff_performance) > 0 ? round($summary['total_sales'] / count($staff_performance)) : 0; ?> avg per staff
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-crown"></i>
        </div>
        <div class="stat-content">
            <h3>
                <?php if ($top_performer): ?>
                    <?php echo htmlspecialchars($top_performer['full_name'] ?: $top_performer['username']); ?>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </h3>
            <p>Top Performer</p>
            <small class="text-muted" style="color: var(--success);">
                <?php if ($top_performer): ?>
                    $<?php echo number_format($top_performer['total_revenue']); ?>
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>

<!-- Performance Metrics -->
<div class="row" style="margin-bottom: 2rem;">
    <div class="col-8">
        <div class="card" style="height: 400px;">
            <div class="card-header">
                <h3 class="card-title">Staff Performance Comparison</h3>
                <div class="btn-group">
                    <button onclick="togglePerformanceChart()" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                </div>
            </div>
            <div style="padding: 1.5rem; height: calc(100% - 60px);">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card" style="height: 400px; overflow-y: auto;">
            <div class="card-header">
                <h3 class="card-title">Performance Rankings</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (empty($staff_performance)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-user-slash" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No performance data available</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $rank = 1;
                    foreach ($staff_performance as $staff): 
                        $medals = [
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉'
                        ];
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; 
                                padding: 1rem; background: var(--bg); border-radius: 10px; margin-bottom: 0.8rem;">
                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                            <div style="width: 40px; height: 40px; 
                                      background: <?php echo $staff['status'] == 'active' ? 'var(--success-light)' : 'var(--error-light)'; ?>; 
                                      color: <?php echo $staff['status'] == 'active' ? 'var(--success)' : 'var(--error)'; ?>; 
                                      border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?php echo $medals[$rank] ?? $rank; ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></strong><br>
                                <small class="text-muted"><?php echo $staff['customers_served']; ?> customers</small>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="color: var(--success); font-weight: 600;">
                                $<?php echo number_format($staff['total_revenue'], 0); ?>
                            </span><br>
                            <small class="text-muted">
                                <?php echo $staff['total_sales']; ?> sales
                            </small>
                        </div>
                    </div>
                    <?php 
                    $rank++;
                    if ($rank > 5) break; // Show only top 5
                    endforeach; 
                    ?>
                    
                    <?php if (count($staff_performance) > 5): ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="#full-list" class="btn btn-outline" style="width: 100%;">
                            View All Rankings <i class="fas fa-arrow-down"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Staff Performance Table -->
<div class="card" id="full-list">
    <div class="card-header">
        <h3 class="card-title">Staff Performance Details</h3>
        <div class="card-actions">
            <span class="text-muted">
                Showing <?php echo count($staff_performance); ?> staff members
            </span>
        </div>
    </div>
    <div class="table-container">
        <?php if (empty($staff_performance)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Performance Data</h4>
                <p class="text-muted">No staff performance data found for the selected period</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Status</th>
                        <th>Shift</th>
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
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($staff['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($staff['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $staff['status']; ?>">
                                <?php echo ucfirst($staff['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($staff['shift_start'] && $staff['shift_end']): ?>
                                <?php echo date('g:i A', strtotime($staff['shift_start'])); ?> - 
                                <?php echo date('g:i A', strtotime($staff['shift_end'])); ?><br>
                                <small class="text-muted"><?php echo $staff['shift_days'] ?: 'N/A'; ?></small>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($staff['total_sales']); ?></strong>
                        </td>
                        <td>
                            <div style="color: var(--success); font-weight: 600;">
                                $<?php echo number_format($staff['total_revenue'], 2); ?>
                            </div>
                        </td>
                        <td>
                            $<?php echo number_format($staff['avg_sale_value'] ?: 0, 2); ?>
                        </td>
                        <td>
                            <?php echo number_format($staff['customers_served']); ?>
                        </td>
                        <td>
                            <span style="color: <?php echo $staff['revenue_per_hour'] > 100 ? 'var(--success)' : 'var(--warning)'; ?>; font-weight: 600;">
                                $<?php echo number_format($staff['revenue_per_hour'], 1); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($staff['last_sale']): ?>
                                <?php echo date('M d, Y', strtotime($staff['last_sale'])); ?><br>
                                <small class="text-muted">
                                    <?php echo date('H:i', strtotime($staff['last_sale'])); ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">No sales</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.3rem;">
                                <button onclick="viewStaffDetails(<?php echo $staff['user_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="generateStaffReport(<?php echo $staff['user_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="Generate Report">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                <a href="../users/edit.php?id=<?php echo $staff['user_id']; ?>" 
                                   class="btn btn-sm btn-outline" 
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

<!-- Performance Analysis -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Performance Insights</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (!empty($staff_performance)): ?>
                    <?php
                    $active_staff = array_filter($staff_performance, fn($s) => $s['status'] === 'active');
                    $inactive_staff = array_filter($staff_performance, fn($s) => $s['status'] !== 'active');
                    
                    $top_3_revenue = array_sum(array_slice(array_column($staff_performance, 'total_revenue'), 0, 3));
                    $total_revenue = array_sum(array_column($staff_performance, 'total_revenue'));
                    $top_3_percentage = $total_revenue > 0 ? ($top_3_revenue / $total_revenue) * 100 : 0;
                    ?>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 0.5rem;">Revenue Distribution</h4>
                        <p class="text-muted">Top 3 staff members generate 
                            <strong style="color: var(--success);">
                                <?php echo round($top_3_percentage); ?>%
                            </strong> of total revenue
                        </p>
                        <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                            <div style="width: <?php echo $top_3_percentage; ?>%; height: 100%; background: var(--success);"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 0.5rem;">Performance Metrics</h4>
                        <div class="row">
                            <div class="col-6">
                                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem;">
                                    <small class="text-muted">Avg Revenue per Staff</small><br>
                                    <strong>$<?php echo number_format($summary['avg_revenue_per_staff'], 0); ?></strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem;">
                                    <small class="text-muted">Avg Customers per Staff</small><br>
                                    <strong>
                                        <?php echo count($staff_performance) > 0 ? round(array_sum(array_column($staff_performance, 'customers_served')) / count($staff_performance)) : 0; ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 0.5rem;">Staff Status</h4>
                        <div class="row">
                            <div class="col-6">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 12px; height: 12px; background: var(--success); border-radius: 50%;"></div>
                                    <span>Active: <?php echo count($active_staff); ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 12px; height: 12px; background: var(--error); border-radius: 50%;"></div>
                                    <span>Inactive: <?php echo count($inactive_staff); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <p class="text-muted">No insights available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row" style="gap: 1rem 0;">
                    <div class="col-6">
                        <button onclick="createPerformanceIncentive()" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-award"></i> Create Incentive
                        </button>
                    </div>
                    <div class="col-6">
                        <button onclick="scheduleStaffTraining()" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-graduation-cap"></i> Schedule Training
                        </button>
                    </div>
                    <div class="col-6">
                        <a href="../users/index.php?filter=staff" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-user-cog"></i> Manage Staff
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../schedule/index.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-calendar-alt"></i> View Schedule
                        </a>
                    </div>
                    <div class="col-12" style="margin-top: 1rem;">
                        <button onclick="compareStaffPerformance()" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-chart-bar"></i> Compare Performance
                        </button>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Export Options</h4>
                    <div class="row">
                        <div class="col-4">
                            <button onclick="exportAsPDF()" class="btn btn-sm btn-outline" style="width: 100%;">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                        <div class="col-4">
                            <button onclick="exportAsCSV()" class="btn btn-sm btn-outline" style="width: 100%;">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                        </div>
                        <div class="col-4">
                            <button onclick="exportAsExcel()" class="btn btn-sm btn-outline" style="width: 100%;">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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
        const staffNames = [];
        const revenues = [];
        const salesCount = [];
        <?php endif; ?>
        
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
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label.includes('Revenue')) {
                                    return `${label}: $${context.parsed.y.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })}`;
                                } else {
                                    return `${label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Sales Count'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }
    
    function togglePerformanceChart() {
        if (performanceChart) {
            chartType = chartType === 'bar' ? 'line' : 'bar';
            performanceChart.destroy();
            initializePerformanceChart();
            showToast(`Switched to ${chartType} chart`, 'info');
        }
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
        link.click();
        
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
        // In production, this would generate a PDF report
        setTimeout(() => {
            showToast('Staff report generated successfully', 'success');
        }, 2000);
    }
    
    function createPerformanceIncentive() {
        showModal('Create Performance Incentive', `
            <div class="form-group">
                <label class="form-label">Incentive Type</label>
                <select class="form-control">
                    <option value="bonus">Performance Bonus</option>
                    <option value="commission">Sales Commission</option>
                    <option value="award">Recognition Award</option>
                    <option value="gift">Gift Card</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Target Staff</label>
                <select class="form-control" multiple>
                    <?php foreach ($staff_performance as $staff): ?>
                    <option value="<?php echo $staff['user_id']; ?>">
                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Amount/Value</label>
                <input type="number" class="form-control" placeholder="0.00" step="0.01">
            </div>
            <div class="form-group">
                <label class="form-label">Criteria</label>
                <textarea class="form-control" rows="3" placeholder="Performance criteria..."></textarea>
            </div>
        `, 'Create Incentive');
    }
    
    function scheduleStaffTraining() {
        showModal('Schedule Staff Training', `
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Training Date</label>
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" value="14:00">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Training Topic</label>
                <input type="text" class="form-control" placeholder="e.g., Sales Techniques, Product Knowledge">
            </div>
            <div class="form-group">
                <label class="form-label">Staff Participants</label>
                <select class="form-control" multiple>
                    <?php foreach ($staff_performance as $staff): ?>
                    <option value="<?php echo $staff['user_id']; ?>">
                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        `, 'Schedule Training');
    }
    
    function compareStaffPerformance() {
        showModal('Compare Staff Performance', `
            <div class="form-group">
                <label class="form-label">Select Staff to Compare (2-4)</label>
                <select class="form-control" multiple size="6">
                    <?php foreach ($staff_performance as $staff): ?>
                    <option value="<?php echo $staff['user_id']; ?>">
                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Metrics to Compare</label>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.3rem;">
                        <input type="checkbox" checked> Revenue
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.3rem;">
                        <input type="checkbox" checked> Sales Count
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.3rem;">
                        <input type="checkbox"> Average Sale Value
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.3rem;">
                        <input type="checkbox"> Customers Served
                    </label>
                </div>
            </div>
        `, 'Compare');
    }
    
    function exportAsPDF() {
        showToast('Generating PDF report...', 'info');
        // PDF generation would be implemented here
        setTimeout(() => {
            showToast('PDF report ready for download', 'success');
        }, 3000);
    }
    
    function exportAsCSV() {
        const csvData = [
            ['Staff Name', 'Username', 'Status', 'Total Sales', 'Total Revenue', 'Avg Sale', 'Customers Served', 'Last Sale'],
            <?php foreach ($staff_performance as $staff): ?>
            [
                '<?php echo addslashes($staff['full_name'] ?: $staff['username']); ?>',
                '<?php echo addslashes($staff['username']); ?>',
                '<?php echo $staff['status']; ?>',
                '<?php echo $staff['total_sales']; ?>',
                '<?php echo $staff['total_revenue']; ?>',
                '<?php echo $staff['avg_sale_value']; ?>',
                '<?php echo $staff['customers_served']; ?>',
                '<?php echo $staff['last_sale'] ? date('Y-m-d', strtotime($staff['last_sale'])) : ''; ?>'
            ],
            <?php endforeach; ?>
        ];
        
        const csvContent = csvData.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `staff_performance_<?php echo date('Y-m-d'); ?>.csv`;
        link.click();
        
        showToast('CSV report exported', 'success');
    }
    
    function exportAsExcel() {
        showToast('Generating Excel report...', 'info');
        // Excel generation would be implemented here
        setTimeout(() => {
            showToast('Excel report ready for download', 'success');
        }, 3000);
    }
    
    function showModal(title, content, actionText) {
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
            <div style="background: white; padding: 2rem; border-radius: 20px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-bottom: 1.5rem;">${title}</h3>
                ${content}
                <div style="margin-top: 2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" 
                            class="btn btn-outline">
                        Cancel
                    </button>
                    <button onclick="processModalAction('${actionText}')" class="btn btn-primary">
                        ${actionText}
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    function processModalAction(action) {
        document.querySelector('div[style*="position: fixed"]').remove();
        showToast(`${action} action completed`, 'success');
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializePerformanceChart();
        
        // Auto-update date max
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = '<?php echo date("Y-m-d"); ?>';
        });
        
        // Initialize multi-selects
        document.querySelectorAll('select[multiple]').forEach(select => {
            select.style.height = '120px';
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
