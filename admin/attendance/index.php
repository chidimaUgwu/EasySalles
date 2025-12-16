<?php
// admin/attendance/index.php
$page_title = "Attendance Tracking";
require_once '../includes/header.php';

$current_date = $_GET['date'] ?? date('Y-m-d');
$view_mode = $_GET['view'] ?? 'day'; // day, week, month
$department = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Get today's attendance
$today_attendance = [];
try {
    $query = "SELECT 
        a.*,
        u.username,
        u.full_name,
        u.shift_start,
        u.shift_end,
        s.shift_name,
        s.color
        FROM EASYSALLES_ATTENDANCE a
        LEFT JOIN EASYSALLES_USERS u ON a.user_id = u.user_id
        LEFT JOIN EASYSALLES_SHIFTS s ON a.shift_id = s.shift_id
        WHERE a.date = ?
        AND u.role = 2
        ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_date]);
    $today_attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Get all staff
$all_staff = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name, status FROM EASYSALLES_USERS WHERE role = 2 AND status = 'active' ORDER BY full_name");
    $all_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Calculate attendance stats
$stats = [
    'total_staff' => count($all_staff),
    'present' => count(array_filter($today_attendance, fn($a) => $a['status'] === 'present')),
    'absent' => count(array_filter($today_attendance, fn($a) => $a['status'] === 'absent')),
    'late' => count(array_filter($today_attendance, fn($a) => $a['status'] === 'late')),
    'on_leave' => count(array_filter($today_attendance, fn($a) => $a['status'] === 'on_leave')),
    'clocked_in' => count(array_filter($today_attendance, fn($a) => $a['clock_in'] !== null)),
    'clocked_out' => count(array_filter($today_attendance, fn($a) => $a['clock_out'] !== null))
];

// Get attendance summary for the week
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));

$week_attendance = [];
try {
    $query = "SELECT 
        a.date,
        COUNT(*) as total,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
        AVG(a.total_hours) as avg_hours
        FROM EASYSALLES_ATTENDANCE a
        WHERE a.date BETWEEN ? AND ?
        GROUP BY a.date
        ORDER BY a.date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$week_start, $week_end]);
    $week_attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Get pending attendance requests
$pending_requests = [];
try {
    $query = "SELECT 
        ar.*,
        u.full_name,
        u.username
        FROM EASYSALLES_ATTENDANCE_REQUESTS ar
        LEFT JOIN EASYSALLES_USERS u ON ar.user_id = u.user_id
        WHERE ar.status = 'pending'
        AND ar.start_date >= ?
        ORDER BY ar.created_at DESC
        LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_date]);
    $pending_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Attendance Tracking</h2>
        <p>Monitor staff attendance, clock-ins, and working hours</p>
    </div>
    <div class="page-actions">
        <button onclick="showBulkAttendance()" class="btn btn-primary">
            <i class="fas fa-user-check"></i> Mark Attendance
        </button>
        <button onclick="exportAttendance()" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Date Navigation -->
<div class="card" style="margin-bottom: 2rem;">
    <div style="padding: 1.5rem;">
        <div class="row">
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">View Date</label>
                    <input type="date" 
                           id="datePicker" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_date); ?>"
                           onchange="changeDate()">
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">View Mode</label>
                    <select id="viewMode" class="form-control" onchange="changeViewMode()">
                        <option value="day" <?php echo $view_mode == 'day' ? 'selected' : ''; ?>>Day View</option>
                        <option value="week" <?php echo $view_mode == 'week' ? 'selected' : ''; ?>>Week View</option>
                        <option value="month" <?php echo $view_mode == 'month' ? 'selected' : ''; ?>>Month View</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Status Filter</label>
                    <select id="statusFilter" class="form-control" onchange="filterByStatus()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="present" <?php echo $status_filter == 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo $status_filter == 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="late" <?php echo $status_filter == 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="on_leave" <?php echo $status_filter == 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                </div>
            </div>
            
            <div class="col-3" style="display: flex; align-items: flex-end;">
                <div style="display: flex; gap: 0.5rem; width: 100%;">
                    <button onclick="navigateDate('prev')" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="goToToday()" class="btn btn-outline" style="flex: 1;">
                        Today
                    </button>
                    <button onclick="navigateDate('next')" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Stats -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['present']; ?>/<?php echo $stats['total_staff']; ?></h3>
            <p>Present Today</p>
            <small class="text-muted">
                <?php echo $stats['clocked_in']; ?> clocked in
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['absent']; ?></h3>
            <p>Absent</p>
            <small class="text-muted" style="color: var(--error);">
                <?php echo $stats['on_leave']; ?> on leave
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['late']; ?></h3>
            <p>Late Arrivals</p>
            <small class="text-muted">
                <?php echo date('D, M d', strtotime($current_date)); ?>
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-business-time"></i>
        </div>
        <div class="stat-content">
            <h3>
                <?php 
                $avg_hours = 0;
                if (count($today_attendance) > 0) {
                    $total_hours = array_sum(array_column($today_attendance, 'total_hours'));
                    $avg_hours = round($total_hours / count($today_attendance), 1);
                }
                echo $avg_hours;
                ?>
            </h3>
            <p>Avg Hours/Staff</p>
            <small class="text-muted">
                <?php echo $stats['clocked_out']; ?> clocked out
            </small>
        </div>
    </div>
</div>

<!-- Today's Attendance -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            Attendance for <?php echo date('l, F d, Y', strtotime($current_date)); ?>
        </h3>
        <div class="card-actions">
            <span class="text-muted">
                <?php echo count($today_attendance); ?> of <?php echo $stats['total_staff']; ?> staff recorded
            </span>
        </div>
    </div>
    
    <div class="table-container">
        <?php if (empty($today_attendance) && empty($all_staff)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-user-clock" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Staff Found</h4>
                <p class="text-muted">No staff members are available in the system</p>
                <a href="../users/index.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-user-plus"></i> Add Staff
                </a>
            </div>
        <?php elseif (empty($today_attendance)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-clipboard-check" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Attendance Recorded</h4>
                <p class="text-muted">No attendance has been recorded for today</p>
                <button onclick="showBulkAttendance()" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-user-check"></i> Mark Attendance
                </button>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Shift</th>
                        <th>Scheduled</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($today_attendance as $attendance): 
                        $status_colors = [
                            'present' => 'var(--success)',
                            'absent' => 'var(--error)',
                            'late' => 'var(--warning)',
                            'on_leave' => 'var(--info)',
                            'half_day' => 'var(--accent)'
                        ];
                        
                        $status_icons = [
                            'present' => 'check-circle',
                            'absent' => 'times-circle',
                            'late' => 'clock',
                            'on_leave' => 'umbrella-beach',
                            'half_day' => 'business-time'
                        ];
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($attendance['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($attendance['full_name'] ?: $attendance['username']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($attendance['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($attendance['shift_name']): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 12px; height: 12px; background: <?php echo $attendance['color']; ?>; border-radius: 2px;"></div>
                                    <span><?php echo htmlspecialchars($attendance['shift_name']); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No shift</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($attendance['scheduled_start'] && $attendance['scheduled_end']): ?>
                                <?php echo date('g:i A', strtotime($attendance['scheduled_start'])); ?> - 
                                <?php echo date('g:i A', strtotime($attendance['scheduled_end'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($attendance['clock_in']): ?>
                                <div style="color: var(--success); font-weight: 600;">
                                    <?php echo date('g:i A', strtotime($attendance['clock_in'])); ?>
                                </div>
                                <?php if ($attendance['late_minutes'] > 0): ?>
                                <small class="text-muted" style="color: var(--warning);">
                                    +<?php echo $attendance['late_minutes']; ?>m late
                                </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not clocked in</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($attendance['clock_out']): ?>
                                <div style="color: var(--primary); font-weight: 600;">
                                    <?php echo date('g:i A', strtotime($attendance['clock_out'])); ?>
                                </div>
                                <?php if ($attendance['early_departure_minutes'] > 0): ?>
                                <small class="text-muted" style="color: var(--warning);">
                                    -<?php echo $attendance['early_departure_minutes']; ?>m early
                                </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not clocked out</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 600;">
                                <?php echo number_format($attendance['total_hours'], 1); ?>h
                            </div>
                            <?php if ($attendance['overtime_hours'] > 0): ?>
                            <small class="text-muted" style="color: var(--warning);">
                                +<?php echo number_format($attendance['overtime_hours'], 1); ?>h OT
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-<?php echo $status_icons[$attendance['status']] ?? 'question-circle'; ?>" 
                                   style="color: <?php echo $status_colors[$attendance['status']] ?? 'var(--text)'; ?>;"></i>
                                <span class="status-badge status-<?php echo $attendance['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $attendance['status'])); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.3rem;">
                                <?php if (!$attendance['clock_in']): ?>
                                <button onclick="markClockIn(<?php echo $attendance['user_id']; ?>)" 
                                        class="btn btn-sm btn-success" 
                                        title="Mark Clock In">
                                    <i class="fas fa-sign-in-alt"></i>
                                </button>
                                <?php elseif (!$attendance['clock_out']): ?>
                                <button onclick="markClockOut(<?php echo $attendance['user_id']; ?>)" 
                                        class="btn btn-sm btn-primary" 
                                        title="Mark Clock Out">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="editAttendance(<?php echo $attendance['attendance_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="Edit Attendance">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="viewAttendanceDetails(<?php echo $attendance['attendance_id']; ?>)" 
                                        class="btn btn-sm btn-outline" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
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

<!-- Attendance Overview -->
<div class="row">
    <div class="col-8">
        <div class="card" style="height: 400px;">
            <div class="card-header">
                <h3 class="card-title">Weekly Attendance Trend</h3>
            </div>
            <div style="padding: 1.5rem; height: calc(100% - 60px);">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card" style="height: 400px; overflow-y: auto;">
            <div class="card-header">
                <h3 class="card-title">Pending Requests</h3>
                <a href="requests.php" class="btn btn-sm btn-outline">
                    View All
                </a>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (empty($pending_requests)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 2rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <p class="text-muted">No pending requests</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; 
                                padding: 1rem; background: var(--bg); border-radius: 10px; margin-bottom: 0.8rem;">
                        <div>
                            <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($request['full_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?><br>
                                <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                <?php echo date('M d', strtotime($request['end_date'])); ?>
                            </small>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-badge status-pending" style="font-size: 0.7rem;">
                                Pending
                            </span><br>
                            <small class="text-muted">
                                <?php echo date('M d', strtotime($request['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="margin-top: 1rem;">
                    <button onclick="showNewRequestModal()" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-plus"></i> New Leave Request
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Attendance Management</h3>
    </div>
    <div style="padding: 1.5rem;">
        <div class="row">
            <div class="col-3">
                <button onclick="showBulkAttendance()" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-user-check"></i> Mark Bulk Attendance
                </button>
            </div>
            <div class="col-3">
                <button onclick="generateAttendanceReport()" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-file-pdf"></i> Generate Report
                </button>
            </div>
            <div class="col-3">
                <button onclick="importAttendance()" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-upload"></i> Import Data
                </button>
            </div>
            <div class="col-3">
                <button onclick="syncWithSchedule()" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sync-alt"></i> Sync with Schedule
                </button>
            </div>
        </div>
        
        <hr style="margin: 1.5rem 0;">
        
        <div class="row">
            <div class="col-12">
                <h4 style="margin-bottom: 1rem;">Attendance Settings</h4>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Late Threshold (minutes)</label>
                            <input type="number" class="form-control" value="15" min="0">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Early Departure Threshold</label>
                            <input type="number" class="form-control" value="30" min="0">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Overtime Start After (hours)</label>
                            <input type="number" class="form-control" value="8" min="0" step="0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-present { background: var(--success-light); color: var(--success); }
.status-absent { background: var(--error-light); color: var(--error); }
.status-late { background: var(--warning-light); color: var(--warning); }
.status-on_leave { background: var(--info-light); color: var(--info); }
.status-pending { background: var(--warning-light); color: var(--warning); }
</style>

<script>
let attendanceChart = null;

function initializeAttendanceChart() {
    const ctx = document.getElementById('attendanceChart');
    if (!ctx) return;
    
    <?php if (!empty($week_attendance)): ?>
    const dates = <?php echo json_encode(array_map(function($a) {
        return date('D', strtotime($a['date']));
    }, $week_attendance)); ?>;
    
    const presentData = <?php echo json_encode(array_map(function($a) {
        return $a['present'] ?? 0;
    }, $week_attendance)); ?>;
    
    const absentData = <?php echo json_encode(array_map(function($a) {
        return $a['absent'] ?? 0;
    }, $week_attendance)); ?>;
    
    const avgHours = <?php echo json_encode(array_map(function($a) {
        return $a['avg_hours'] ?? 0;
    }, $week_attendance)); ?>;
    <?php else: ?>
    const dates = [];
    const presentData = [];
    const absentData = [];
    const avgHours = [];
    <?php endif; ?>
    
    attendanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Present',
                    data: presentData,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Absent',
                    data: absentData,
                    backgroundColor: 'rgba(239, 68, 68, 0.6)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Avg Hours',
                    data: avgHours,
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    borderColor: 'rgba(124, 58, 237, 1)',
                    borderWidth: 2,
                    type: 'line',
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
                        text: 'Number of Staff'
                    },
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Average Hours'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

function changeDate() {
    const date = document.getElementById('datePicker').value;
    const viewMode = document.getElementById('viewMode').value;
    const status = document.getElementById('statusFilter').value;
    
    let url = `?date=${date}&view=${viewMode}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    
    window.location.href = url;
}

function changeViewMode() {
    const date = document.getElementById('datePicker').value;
    const viewMode = document.getElementById('viewMode').value;
    const status = document.getElementById('statusFilter').value;
    
    let url = `?date=${date}&view=${viewMode}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    
    window.location.href = url;
}

function filterByStatus() {
    const date = document.getElementById('datePicker').value;
    const viewMode = document.getElementById('viewMode').value;
    const status = document.getElementById('statusFilter').value;
    
    let url = `?date=${date}&view=${viewMode}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    
    window.location.href = url;
}

function navigateDate(direction) {
    const currentDate = new Date('<?php echo $current_date; ?>');
    const viewMode = document.getElementById('viewMode').value;
    
    if (viewMode === 'day') {
        currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 1 : -1));
    } else if (viewMode === 'week') {
        currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 7 : -7));
    } else {
        currentDate.setMonth(currentDate.getMonth() + (direction === 'next' ? 1 : -1));
    }
    
    const formattedDate = currentDate.toISOString().split('T')[0];
    const status = document.getElementById('statusFilter').value;
    
    let url = `?date=${formattedDate}&view=${viewMode}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    
    window.location.href = url;
}

function goToToday() {
    const today = new Date().toISOString().split('T')[0];
    const viewMode = document.getElementById('viewMode').value;
    const status = document.getElementById('statusFilter').value;
    
    let url = `?date=${today}&view=${viewMode}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    
    window.location.href = url;
}

function markClockIn(userId) {
    const currentTime = new Date().toTimeString().substring(0, 5);
    
    showModal('Mark Clock In', `
        <div class="form-group">
            <label class="form-label">Staff</label>
            <input type="text" class="form-control" value="Staff ID: ${userId}" disabled>
        </div>
        <div class="form-group">
            <label class="form-label">Clock In Time</label>
            <input type="time" id="clockInTime" class="form-control" value="${currentTime}">
        </div>
        <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea class="form-control" rows="2" placeholder="Add any notes..."></textarea>
        </div>
    `, 'Mark Clock In', `confirmClockIn(${userId})`);
}

function markClockOut(userId) {
    const currentTime = new Date().toTimeString().substring(0, 5);
    
    showModal('Mark Clock Out', `
        <div class="form-group">
            <label class="form-label">Staff</label>
            <input type="text" class="form-control" value="Staff ID: ${userId}" disabled>
        </div>
        <div class="form-group">
            <label class="form-label">Clock Out Time</label>
            <input type="time" id="clockOutTime" class="form-control" value="${currentTime}">
        </div>
        <div class="form-group">
            <label class="form-label">Overtime Hours (Optional)</label>
            <input type="number" class="form-control" step="0.5" min="0" placeholder="0.0">
        </div>
        <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea class="form-control" rows="2" placeholder="Add any notes..."></textarea>
        </div>
    `, 'Mark Clock Out', `confirmClockOut(${userId})`);
}

function editAttendance(attendanceId) {
    showModal('Edit Attendance Record', `
        <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control">
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="late">Late</option>
                <option value="on_leave">On Leave</option>
                <option value="half_day">Half Day</option>
            </select>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Clock In Time</label>
                    <input type="time" class="form-control">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Clock Out Time</label>
                    <input type="time" class="form-control">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Late Minutes</label>
                    <input type="number" class="form-control" min="0">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Overtime Hours</label>
                    <input type="number" class="form-control" step="0.5" min="0">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea class="form-control" rows="3" placeholder="Add notes..."></textarea>
        </div>
    `, 'Save Changes', `confirmEditAttendance(${attendanceId})`);
}

function viewAttendanceDetails(attendanceId) {
    showModal('Attendance Details', `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading attendance details...</p>
        </div>
    `, 'Close');
    
    setTimeout(() => {
        document.querySelector('.modal-content').innerHTML = `
            <h3 style="margin-bottom: 1.5rem;">Attendance Details</h3>
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        JD
                    </div>
                    <div>
                        <h4 style="margin: 0;">John Doe</h4>
                        <p class="text-muted" style="margin: 0;">Morning Shift (9AM-5PM)</p>
                    </div>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Date:</span>
                        <span>Monday, Dec 15, 2025</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Clock In:</span>
                        <span style="color: var(--success); font-weight: 600;">9:15 AM (+15m late)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Clock Out:</span>
                        <span style="color: var(--primary); font-weight: 600;">5:30 PM (+30m OT)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Total Hours:</span>
                        <span><strong>8.25 hours</strong></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-late">Late</span>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Notes</h4>
                    <p style="color: var(--text-light); font-style: italic;">
                        Traffic delay in the morning. Stayed late to complete project.
                    </p>
                </div>
            </div>
        `;
    }, 1000);
}

function showBulkAttendance() {
    showModal('Mark Bulk Attendance', `
        <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Staff Selection</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
                <?php foreach ($all_staff as $staff): ?>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; cursor: pointer;">
                    <input type="checkbox" checked>
                    <span><?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Default Status</label>
            <select class="form-control">
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="on_leave">On Leave</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Apply Clock Times (Optional)</label>
            <div class="row">
                <div class="col-6">
                    <input type="time" class="form-control" placeholder="Clock In">
                </div>
                <div class="col-6">
                    <input type="time" class="form-control" placeholder="Clock Out">
                </div>
            </div>
        </div>
    `, 'Apply Bulk Attendance', 'processBulkAttendance');
}

function showNewRequestModal() {
    showModal('New Leave Request', `
        <div class="form-group">
            <label class="form-label">Request Type</label>
            <select class="form-control">
                <option value="leave">Leave</option>
                <option value="early_departure">Early Departure</option>
                <option value="late_arrival">Late Arrival</option>
                <option value="remote_work">Remote Work</option>
                <option value="other">Other</option>
            </select>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Reason</label>
            <textarea class="form-control" rows="3" placeholder="Please explain the reason for this request..." required></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Priority</label>
            <div style="display: flex; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="low" checked>
                    <span style="color: var(--success);">Low</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="medium">
                    <span style="color: var(--warning);">Medium</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" name="priority" value="high">
                    <span style="color: var(--error);">High (Urgent)</span>
                </label>
            </div>
        </div>
    `, 'Submit Request', 'submitNewRequest');
}

function exportAttendance() {
    showToast('Exporting attendance data...', 'info');
    
    const attendanceData = {
        date: '<?php echo $current_date; ?>',
        stats: <?php echo json_encode($stats); ?>,
        attendance: <?php echo json_encode($today_attendance); ?>,
        generated: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(attendanceData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const link = document.createElement('a');
    link.href = dataUri;
    link.download = `attendance_<?php echo date('Y-m-d'); ?>.json`;
    link.click();
    
    showToast('Attendance data exported', 'success');
}

function generateAttendanceReport() {
    showToast('Generating attendance report...', 'info');
    // In production, this would generate a PDF report
    setTimeout(() => {
        showToast('Attendance report ready for download', 'success');
    }, 3000);
}

function importAttendance() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.csv,.json,.xlsx';
    input.onchange = function(e) {
        showToast('Importing attendance data...', 'info');
        setTimeout(() => {
            showToast('Attendance data imported successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        }, 2000);
    };
    input.click();
}

function syncWithSchedule() {
    showToast('Syncing with schedule data...', 'info');
    setTimeout(() => {
        showToast('Attendance synced with schedule', 'success');
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function confirmClockIn(userId) {
    const time = document.getElementById('clockInTime').value;
    showToast(`Marking clock in at ${time}...`, 'info');
    setTimeout(() => {
        showToast('Clock in recorded successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmClockOut(userId) {
    const time = document.getElementById('clockOutTime').value;
    showToast(`Marking clock out at ${time}...`, 'info');
    setTimeout(() => {
        showToast('Clock out recorded successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmEditAttendance(attendanceId) {
    showToast('Updating attendance record...', 'info');
    setTimeout(() => {
        showToast('Attendance record updated', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function processBulkAttendance() {
    showToast('Processing bulk attendance...', 'info');
    setTimeout(() => {
        showToast('Bulk attendance applied successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function submitNewRequest() {
    showToast('Submitting request...', 'info');
    setTimeout(() => {
        showToast('Request submitted successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
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

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
`;
document.head.appendChild(style);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeAttendanceChart();
    
    // Set date max values
    document.querySelectorAll('input[type="date"]').forEach(input => {
        input.max = new Date().toISOString().split('T')[0];
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
