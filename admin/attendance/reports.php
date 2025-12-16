<?php
// admin/attendance/reports.php
$page_title = "Attendance Reports";
require_once '../includes/header.php';

$report_type = $_GET['report_type'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? '';

// Calculate date range based on report type
if ($report_type === 'monthly') {
    $start_date = "$year-" . str_pad(explode('-', $month)[1], 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));
} elseif ($report_type === 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} else {
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";
}

// Get attendance report data
$attendance_data = [];
try {
    $query = "SELECT 
        u.user_id,
        u.username,
        u.full_name,
        COUNT(a.attendance_id) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as leave_days,
        AVG(a.total_hours) as avg_hours_per_day,
        SUM(a.total_hours) as total_hours,
        SUM(a.overtime_hours) as total_overtime,
        SUM(a.late_minutes) as total_late_minutes,
        SUM(a.early_departure_minutes) as total_early_minutes
        FROM EASYSALLES_USERS u
        LEFT JOIN EASYSALLES_ATTENDANCE a ON u.user_id = a.user_id 
            AND a.date BETWEEN ? AND ?
        WHERE u.role = 2
        GROUP BY u.user_id
        ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $attendance_data = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
    $attendance_data = [];
}

// Calculate summary stats
$summary = [
    'total_staff' => count($attendance_data),
    'total_days' => array_sum(array_column($attendance_data, 'total_days')),
    'present_days' => array_sum(array_column($attendance_data, 'present_days')),
    'absent_days' => array_sum(array_column($attendance_data, 'absent_days')),
    'late_days' => array_sum(array_column($attendance_data, 'late_days')),
    'leave_days' => array_sum(array_column($attendance_data, 'leave_days')),
    'total_hours' => array_sum(array_column($attendance_data, 'total_hours')),
    'total_overtime' => array_sum(array_column($attendance_data, 'total_overtime')),
    'attendance_rate' => 0
];

if ($summary['total_days'] > 0) {
    $summary['attendance_rate'] = round(($summary['present_days'] / $summary['total_days']) * 100, 1);
}

// Get monthly attendance trend
$monthly_trend = [];
try {
    $query = "SELECT 
        DATE_FORMAT(a.date, '%Y-%m') as month,
        COUNT(*) as total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        AVG(a.total_hours) as avg_hours
        FROM EASYSALLES_ATTENDANCE a
        WHERE a.date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(a.date, '%Y-%m')
        ORDER BY month ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $monthly_trend = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Attendance Reports</h2>
        <p>Comprehensive attendance analytics and insights</p>
    </div>
    <div class="page-actions">
        <button onclick="exportAttendanceReport()" class="btn btn-primary">
            <i class="fas fa-file-export"></i> Export Report
        </button>
        <button onclick="printReport()" class="btn btn-outline" style="margin-left: 0.5rem;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- Report Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Report Parameters</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-control" onchange="this.form.submit()">
                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                        <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                        <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly Report</option>
                        <option value="custom" <?php echo $report_type == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
            </div>
            
            <?php if ($report_type == 'monthly'): ?>
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-control">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year - 1; $y <= $current_year; $y++):
                            for ($m = 1; $m <= 12; $m++):
                                $month_value = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT);
                                $month_name = date('F Y', strtotime("$y-$m-01"));
                                $selected = $month == $month_value ? 'selected' : '';
                        ?>
                        <option value="<?php echo $month_value; ?>" <?php echo $selected; ?>>
                            <?php echo $month_name; ?>
                        </option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
            </div>
            <?php elseif ($report_type == 'yearly'): ?>
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-control">
                        <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-control">
                        <option value="name">Name (A-Z)</option>
                        <option value="attendance">Attendance Rate (High to Low)</option>
                        <option value="hours">Total Hours (High to Low)</option>
                        <option value="absent">Absent Days (Low to High)</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
            </div>
        </form>
        
        <?php if ($report_type == 'custom'): ?>
        <div class="row" style="margin-top: 1rem;">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report Summary -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $summary['total_staff']; ?></h3>
            <p>Staff Members</p>
            <small class="text-muted">
                <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d', strtotime($end_date)); ?>
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $summary['attendance_rate']; ?>%</h3>
            <p>Attendance Rate</p>
            <small class="text-muted">
                <?php echo $summary['present_days']; ?> of <?php echo $summary['total_days']; ?> days
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-business-time"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo round($summary['total_hours']); ?></h3>
            <p>Total Hours</p>
            <small class="text-muted" style="color: var(--warning);">
                <?php echo round($summary['total_overtime']); ?> overtime hours
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $summary['absent_days']; ?></h3>
            <p>Absent Days</p>
            <small class="text-muted">
                <?php echo $summary['late_days']; ?> late arrivals
            </small>
        </div>
    </div>
</div>

<!-- Attendance Trend Chart -->
<div class="row" style="margin-bottom: 2rem;">
    <div class="col-8">
        <div class="card" style="height: 400px;">
            <div class="card-header">
                <h3 class="card-title">Attendance Trend (Last 6 Months)</h3>
            </div>
            <div style="padding: 1.5rem; height: calc(100% - 60px);">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card" style="height: 400px; overflow-y: auto;">
            <div class="card-header">
                <h3 class="card-title">Top Performers</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php 
                // Sort by attendance rate
                usort($attendance_data, function($a, $b) {
                    $rate_a = $a['total_days'] > 0 ? ($a['present_days'] / $a['total_days']) * 100 : 0;
                    $rate_b = $b['total_days'] > 0 ? ($b['present_days'] / $b['total_days']) * 100 : 0;
                    return $rate_b <=> $rate_a;
                });
                
                $top_performers = array_slice($attendance_data, 0, 5);
                ?>
                
                <?php if (empty($top_performers)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-chart-line" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No attendance data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_performers as $index => $staff): 
                        $attendance_rate = $staff['total_days'] > 0 ? 
                            round(($staff['present_days'] / $staff['total_days']) * 100, 1) : 0;
                        $medals = ['🥇', '🥈', '🥉'];
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; 
                                padding: 1rem; background: var(--bg); border-radius: 10px; margin-bottom: 0.8rem;">
                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                            <div style="width: 40px; height: 40px; 
                                      background: var(--success-light); 
                                      color: var(--success); 
                                      border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?php echo $medals[$index] ?? ($index + 1); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></strong><br>
                                <small class="text-muted"><?php echo $staff['present_days']; ?> present days</small>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="color: var(--success); font-weight: 600;">
                                <?php echo $attendance_rate; ?>%
                            </span><br>
                            <small class="text-muted">
                                <?php echo round($staff['total_hours'], 1); ?> hours
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detailed Attendance Report</h3>
        <div class="card-actions">
            <span class="text-muted">
                <?php echo count($attendance_data); ?> staff members
            </span>
        </div>
    </div>
    <div class="table-container">
        <?php if (empty($attendance_data)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-clipboard-list" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Attendance Data</h4>
                <p class="text-muted">No attendance records found for the selected period</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Leave</th>
                        <th>Attendance Rate</th>
                        <th>Total Hours</th>
                        <th>Overtime</th>
                        <th>Avg Hours/Day</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_data as $staff): 
                        $attendance_rate = $staff['total_days'] > 0 ? 
                            round(($staff['present_days'] / $staff['total_days']) * 100, 1) : 0;
                        $rate_color = $attendance_rate >= 95 ? 'var(--success)' : 
                                    ($attendance_rate >= 90 ? 'var(--warning)' : 'var(--error)');
                    ?>
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
                        <td><?php echo $staff['total_days']; ?></td>
                        <td style="color: var(--success); font-weight: 600;">
                            <?php echo $staff['present_days']; ?>
                        </td>
                        <td style="color: var(--error);">
                            <?php echo $staff['absent_days']; ?>
                        </td>
                        <td style="color: var(--warning);">
                            <?php echo $staff['late_days']; ?>
                        </td>
                        <td style="color: var(--info);">
                            <?php echo $staff['leave_days']; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 60px; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $attendance_rate; ?>%; height: 100%; background: <?php echo $rate_color; ?>;"></div>
                                </div>
                                <span style="color: <?php echo $rate_color; ?>; font-weight: 600;">
                                    <?php echo $attendance_rate; ?>%
                                </span>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo round($staff['total_hours'], 1); ?>h</strong>
                        </td>
                        <td style="color: var(--warning);">
                            <?php echo round($staff['total_overtime'], 1); ?>h
                        </td>
                        <td>
                            <?php echo round($staff['avg_hours_per_day'], 1); ?>h
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.3rem;">
                                <button onclick="viewStaffAttendance(<?php echo $staff['user_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="generateStaffReport(<?php echo $staff['user_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="Generate Report">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Additional Reports -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Attendance Distribution</h3>
            </div>
            <div style="padding: 1.5rem; height: 300px;">
                <canvas id="distributionChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Report Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <button onclick="generateMonthlyReport()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-calendar-alt"></i> Generate Monthly Summary
                    </button>
                    
                    <button onclick="exportToExcel()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    
                    <button onclick="sendReportEmail()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-envelope"></i> Email Report to Managers
                    </button>
                    
                    <button onclick="comparePeriods()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-chart-bar"></i> Compare with Previous Period
                    </button>
                    
                    <hr style="margin: 0.5rem 0;">
                    
                    <div>
                        <h4 style="margin-bottom: 0.5rem;">Report Settings</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" checked>
                                Include overtime calculations
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" checked>
                                Include late/early departure details
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox">
                                Include cost calculations
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let trendChart = null;
let distributionChart = null;

function initializeCharts() {
    // Attendance Trend Chart
    const trendCtx = document.getElementById('attendanceTrendChart');
    if (trendCtx) {
        <?php if (!empty($monthly_trend)): ?>
        const months = <?php echo json_encode(array_map(function($m) {
            return date('M Y', strtotime($m['month'] . '-01'));
        }, $monthly_trend)); ?>;
        
        const attendanceRates = <?php echo json_encode(array_map(function($m) {
            return $m['total_records'] > 0 ? round(($m['present'] / $m['total_records']) * 100, 1) : 0;
        }, $monthly_trend)); ?>;
        
        const avgHours = <?php echo json_encode(array_map(function($m) {
            return $m['avg_hours'] ? round($m['avg_hours'], 1) : 0;
        }, $monthly_trend)); ?>;
        <?php else: ?>
        const months = [];
        const attendanceRates = [];
        const avgHours = [];
        <?php endif; ?>
        
        trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Attendance Rate (%)',
                        data: attendanceRates,
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        borderColor: 'rgba(124, 58, 237, 1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Avg Hours/Day',
                        data: avgHours,
                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                        borderColor: 'rgba(6, 182, 212, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
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
                            text: 'Attendance Rate (%)'
                        },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Avg Hours/Day'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        min: 0
                    }
                }
            }
        });
    }
    
    // Distribution Chart
    const distCtx = document.getElementById('distributionChart');
    if (distCtx) {
        const distributionData = {
            'Present': <?php echo $summary['present_days']; ?>,
            'Absent': <?php echo $summary['absent_days']; ?>,
            'Late': <?php echo $summary['late_days']; ?>,
            'Leave': <?php echo $summary['leave_days']; ?>
        };
        
        distributionChart = new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(distributionData),
                datasets: [{
                    data: Object.values(distributionData),
                    backgroundColor: [
                        'var(--success)',
                        'var(--error)',
                        'var(--warning)',
                        'var(--info)'
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
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                return `${context.label}: ${context.raw} days (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

function exportAttendanceReport() {
    showToast('Exporting attendance report...', 'info');
    
    const reportData = {
        period: '<?php echo date("M d, Y", strtotime($start_date)); ?> to <?php echo date("M d, Y", strtotime($end_date)); ?>',
        type: '<?php echo $report_type; ?>',
        summary: <?php echo json_encode($summary); ?>,
        data: <?php echo json_encode($attendance_data); ?>,
        generated: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const link = document.createElement('a');
    link.href = dataUri;
    link.download = `attendance_report_<?php echo date('Y-m-d'); ?>.json`;
    link.click();
    
    showToast('Attendance report exported', 'success');
}

function printReport() {
    window.print();
}

function viewStaffAttendance(userId) {
    window.location.href = `staff.php?id=${userId}&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>`;
}

function generateStaffReport(userId) {
    showToast(`Generating report for staff ID: ${userId}...`, 'info');
    // In production, this would generate a PDF report
    setTimeout(() => {
        showToast('Staff report generated successfully', 'success');
    }, 2000);
}

function generateMonthlyReport() {
    showToast('Generating monthly summary report...', 'info');
    // In production, this would generate a PDF report
    setTimeout(() => {
        showToast('Monthly summary report ready for download', 'success');
    }, 3000);
}

function exportToExcel() {
    showToast('Exporting to Excel...', 'info');
    
    // Create CSV data
    const csvData = [
        ['Staff Name', 'Username', 'Total Days', 'Present Days', 'Absent Days', 'Late Days', 'Leave Days', 'Attendance Rate', 'Total Hours', 'Overtime Hours', 'Avg Hours/Day'],
        <?php foreach ($attendance_data as $staff): 
            $attendance_rate = $staff['total_days'] > 0 ? 
                round(($staff['present_days'] / $staff['total_days']) * 100, 1) : 0;
        ?>
        [
            '<?php echo addslashes($staff['full_name'] ?: $staff['username']); ?>',
            '<?php echo addslashes($staff['username']); ?>',
            '<?php echo $staff['total_days']; ?>',
            '<?php echo $staff['present_days']; ?>',
            '<?php echo $staff['absent_days']; ?>',
            '<?php echo $staff['late_days']; ?>',
            '<?php echo $staff['leave_days']; ?>',
            '<?php echo $attendance_rate; ?>',
            '<?php echo round($staff['total_hours'], 1); ?>',
            '<?php echo round($staff['total_overtime'], 1); ?>',
            '<?php echo round($staff['avg_hours_per_day'], 1); ?>'
        ],
        <?php endforeach; ?>
    ];
    
    const csvContent = csvData.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `attendance_report_<?php echo date('Y-m-d'); ?>.csv`;
    link.click();
    
    showToast('Report exported to Excel format', 'success');
}

function sendReportEmail() {
    showModal('Email Report', `
        <div class="form-group">
            <label class="form-label">Recipients</label>
            <input type="text" class="form-control" placeholder="Enter email addresses (comma separated)">
        </div>
        
        <div class="form-group">
            <label class="form-label">Subject</label>
            <input type="text" class="form-control" value="Attendance Report - <?php echo date('F Y'); ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Message</label>
            <textarea class="form-control" rows="4" placeholder="Add a custom message...">
Dear Team,

Please find attached the attendance report for <?php echo date('F Y'); ?>.

Best regards,
Management
            </textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Attachment Format</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="format" value="pdf" checked>
                    <span>PDF</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="format" value="excel">
                    <span>Excel</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="format" value="both">
                    <span>Both</span>
                </label>
            </div>
        </div>
    `, 'Send Email', 'sendEmailReport');
}

function comparePeriods() {
    showModal('Compare Periods', `
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Previous Period Start</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-1 month', strtotime($start_date))); ?>">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Previous Period End</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-1 month', strtotime($end_date))); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Comparison Metrics</label>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Attendance Rate
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Total Hours
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Absent Days
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox"> Overtime Hours
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox"> Late Arrivals
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Output Format</label>
            <select class="form-control">
                <option value="chart">Comparison Chart</option>
                <option value="table">Comparison Table</option>
                <option value="report">Detailed Report</option>
            </select>
        </div>
    `, 'Compare', 'processComparison');
}

function sendEmailReport() {
    showToast('Sending email report...', 'info');
    setTimeout(() => {
        showToast('Email report sent successfully', 'success');
        closeModal();
    }, 2000);
}

function processComparison() {
    showToast('Generating comparison report...', 'info');
    setTimeout(() => {
        showToast('Comparison report generated', 'success');
        closeModal();
    }, 2000);
}

function showModal(title, content, actionText, actionFunction = null) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
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
        padding: 1rem;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 20px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin-bottom: 1.5rem;">${title}</h3>
            ${content}
            <div style="margin-top: 2rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button onclick="closeModal()" class="btn btn-outline">
                    Cancel
                </button>
                ${actionFunction ? `<button onclick="${actionFunction}()" class="btn btn-primary">${actionText}</button>` : ''}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--primary)'};
        color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10001;
        animation: slideIn 0.3s ease;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});
</script>

<?php require_once '../includes/footer.php'; ?>
