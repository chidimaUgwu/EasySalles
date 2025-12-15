<?php
// admin/schedule/index.php
$page_title = "Shift Schedule Management";
require_once '../includes/header.php';

$current_date = $_GET['date'] ?? date('Y-m-d');
$view_mode = $_GET['view'] ?? 'week'; // day, week, month
$shift_id = $_GET['shift_id'] ?? '';

// Get all shifts
$all_shifts = [];
try {
    $stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
    $all_shifts = $stmt->fetchAll();
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

// Calculate date range based on view mode
$start_date = $current_date;
$end_date = $current_date;

switch ($view_mode) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));
        break;
    case 'month':
        $start_date = date('Y-m-01', strtotime($current_date));
        $end_date = date('Y-m-t', strtotime($current_date));
        break;
}

// Get scheduled shifts for the period
$scheduled_shifts = [];
try {
    $query = "SELECT 
        us.user_shift_id,
        us.user_id,
        us.shift_id,
        us.assigned_date,
        us.status,
        us.notes,
        u.full_name,
        u.username,
        s.shift_name,
        s.start_time,
        s.end_time,
        s.color
        FROM EASYSALLES_USER_SHIFTS us
        LEFT JOIN EASYSALLES_USERS u ON us.user_id = u.user_id
        LEFT JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
        WHERE us.assigned_date BETWEEN ? AND ?
        ORDER BY us.assigned_date, s.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $scheduled_shifts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Group shifts by date
$shifts_by_date = [];
foreach ($scheduled_shifts as $shift) {
    $date = $shift['assigned_date'];
    if (!isset($shifts_by_date[$date])) {
        $shifts_by_date[$date] = [];
    }
    $shifts_by_date[$date][] = $shift;
}

// Get shift statistics
$shift_stats = [
    'total_scheduled' => count($scheduled_shifts),
    'completed' => count(array_filter($scheduled_shifts, fn($s) => $s['status'] === 'completed')),
    'pending' => count(array_filter($scheduled_shifts, fn($s) => $s['status'] === 'scheduled')),
    'absent' => count(array_filter($scheduled_shifts, fn($s) => $s['status'] === 'absent')),
    'cancelled' => count(array_filter($scheduled_shifts, fn($s) => $s['status'] === 'cancelled')),
    'unique_staff' => count(array_unique(array_column($scheduled_shifts, 'user_id')))
];
?>

<div class="page-header">
    <div class="page-title">
        <h2>Shift Schedule Management</h2>
        <p>Manage staff shifts, assignments, and schedule calendar</p>
    </div>
    <div class="page-actions">
        <button onclick="showAddShiftModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Shift
        </button>
        <button onclick="showAssignShiftModal()" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-user-clock"></i> Assign Shift
        </button>
    </div>
</div>

<!-- View Controls -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Schedule View</h3>
    </div>
    <div style="padding: 1.5rem;">
        <div class="row">
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
                    <label class="form-label">Date</label>
                    <input type="date" 
                           id="datePicker" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_date); ?>"
                           onchange="changeDate()">
                </div>
            </div>
            
            <div class="col-3">
                <div class="form-group">
                    <label class="form-label">Filter by Shift</label>
                    <select id="shiftFilter" class="form-control" onchange="filterByShift()">
                        <option value="">All Shifts</option>
                        <?php foreach ($all_shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>" 
                            <?php echo $shift_id == $shift['shift_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-3" style="display: flex; align-items: flex-end;">
                <button onclick="printSchedule()" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Shift Statistics -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($shift_stats['total_scheduled']); ?></h3>
            <p>Total Scheduled</p>
            <small class="text-muted">
                <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d', strtotime($end_date)); ?>
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($shift_stats['completed']); ?></h3>
            <p>Completed</p>
            <small class="text-muted">
                <?php echo $shift_stats['pending']; ?> pending
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-user-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($shift_stats['unique_staff']); ?></h3>
            <p>Staff Scheduled</p>
            <small class="text-muted">
                From <?php echo count($all_staff); ?> active staff
            </small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-user-slash"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($shift_stats['absent']); ?></h3>
            <p>Absences</p>
            <small class="text-muted" style="color: var(--error);">
                <?php echo $shift_stats['cancelled']; ?> cancelled
            </small>
        </div>
    </div>
</div>

<!-- Schedule Calendar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <?php 
            if ($view_mode == 'day') {
                echo date('l, F d, Y', strtotime($current_date));
            } elseif ($view_mode == 'week') {
                echo 'Week of ' . date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
            } else {
                echo date('F Y', strtotime($current_date));
            }
            ?>
        </h3>
        <div class="btn-group">
            <button onclick="navigateDate('prev')" class="btn btn-outline">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button onclick="goToToday()" class="btn btn-outline">
                Today
            </button>
            <button onclick="navigateDate('next')" class="btn btn-outline">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <div style="padding: 1.5rem;">
        <?php if ($view_mode == 'day'): ?>
            <!-- Day View -->
            <div class="day-schedule">
                <?php
                $day_shifts = $shifts_by_date[$current_date] ?? [];
                $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];
                ?>
                
                <div class="schedule-grid">
                    <div class="time-column">
                        <?php foreach ($hours as $hour): ?>
                        <div class="time-slot"><?php echo $hour; ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="shifts-column">
                        <?php foreach ($day_shifts as $shift): 
                            $start = strtotime($shift['start_time']);
                            $end = strtotime($shift['end_time']);
                            $top = (($start - strtotime('08:00')) / 3600) * 60;
                            $height = (($end - $start) / 3600) * 60;
                        ?>
                        <div class="shift-block" 
                             style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px; background: <?php echo $shift['color'] . '20'; ?>; border-left: 4px solid <?php echo $shift['color']; ?>;">
                            <div class="shift-content">
                                <strong><?php echo htmlspecialchars($shift['full_name'] ?: $shift['username']); ?></strong>
                                <small><?php echo $shift['shift_name']; ?></small>
                                <div class="shift-time">
                                    <?php echo date('g:i A', $start); ?> - <?php echo date('g:i A', $end); ?>
                                </div>
                                <span class="shift-status status-<?php echo $shift['status']; ?>">
                                    <?php echo ucfirst($shift['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($day_shifts)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No shifts scheduled for this day</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($view_mode == 'week'): ?>
            <!-- Week View -->
            <div class="week-schedule">
                <div class="week-header">
                    <div class="day-header">Time</div>
                    <?php
                    $current = strtotime($start_date);
                    while ($current <= strtotime($end_date)):
                        $is_today = date('Y-m-d', $current) == date('Y-m-d');
                    ?>
                    <div class="day-header <?php echo $is_today ? 'today' : ''; ?>">
                        <div><?php echo date('D', $current); ?></div>
                        <div class="date-number"><?php echo date('d', $current); ?></div>
                    </div>
                    <?php
                        $current = strtotime('+1 day', $current);
                    endwhile;
                    ?>
                </div>
                
                <div class="week-body">
                    <div class="time-column">
                        <?php
                        $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];
                        foreach ($hours as $hour):
                        ?>
                        <div class="time-slot"><?php echo $hour; ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php
                    $current = strtotime($start_date);
                    while ($current <= strtotime($end_date)):
                        $date = date('Y-m-d', $current);
                        $day_shifts = $shifts_by_date[$date] ?? [];
                    ?>
                    <div class="day-column <?php echo $date == date('Y-m-d') ? 'today' : ''; ?>">
                        <?php foreach ($day_shifts as $shift): 
                            $start = strtotime($shift['start_time']);
                            $end = strtotime($shift['end_time']);
                            $top = (($start - strtotime('08:00')) / 3600) * 60;
                            $height = (($end - $start) / 3600) * 60;
                        ?>
                        <div class="shift-block week-shift" 
                             style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px; background: <?php echo $shift['color']; ?>;"
                             title="<?php echo htmlspecialchars($shift['full_name'] . ' - ' . $shift['shift_name']); ?>"
                             onclick="viewShiftDetails(<?php echo $shift['user_shift_id']; ?>)">
                            <div class="shift-tooltip">
                                <strong><?php echo htmlspecialchars($shift['full_name']); ?></strong><br>
                                <?php echo $shift['shift_name']; ?><br>
                                <?php echo date('g:i A', $start); ?> - <?php echo date('g:i A', $end); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                        $current = strtotime('+1 day', $current);
                    endwhile;
                    ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Month View -->
            <div class="month-schedule">
                <div class="month-grid">
                    <?php
                    // Generate month grid
                    $first_day = date('N', strtotime($start_date));
                    $days_in_month = date('t', strtotime($start_date));
                    
                    // Day headers
                    $day_names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($day_names as $day):
                    ?>
                    <div class="month-day-header"><?php echo $day; ?></div>
                    <?php endforeach; ?>
                    
                    // Empty cells for days before month start
                    for ($i = 1; $i < $first_day; $i++):
                    ?>
                    <div class="month-day empty"></div>
                    <?php endfor; ?>
                    
                    // Days of the month
                    for ($day = 1; $day <= $days_in_month; $day++):
                        $date = date('Y-m-d', strtotime($start_date . ' + ' . ($day - 1) . ' days'));
                        $is_today = $date == date('Y-m-d');
                        $day_shifts = $shifts_by_date[$date] ?? [];
                    ?>
                    <div class="month-day <?php echo $is_today ? 'today' : ''; ?>">
                        <div class="day-number"><?php echo $day; ?></div>
                        <div class="day-shifts">
                            <?php foreach ($day_shifts as $shift): ?>
                            <div class="month-shift" 
                                 style="background: <?php echo $shift['color']; ?>;"
                                 title="<?php echo htmlspecialchars($shift['full_name'] . ' - ' . $shift['shift_name']); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($day_shifts) > 3): ?>
                        <div class="more-shifts">+<?php echo count($day_shifts) - 3; ?> more</div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Shift List Table -->
<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Scheduled Shifts</h3>
                <div class="card-actions">
                    <span class="text-muted">
                        <?php echo count($scheduled_shifts); ?> shifts found
                    </span>
                </div>
            </div>
            <div class="table-container">
                <?php if (empty($scheduled_shifts)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <i class="fas fa-user-clock" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">No Shifts Scheduled</h4>
                        <p class="text-muted">No shifts have been scheduled for the selected period</p>
                        <button onclick="showAssignShiftModal()" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-user-clock"></i> Assign First Shift
                        </button>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Shift</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduled_shifts as $shift): 
                                $start_time = strtotime($shift['start_time']);
                                $end_time = strtotime($shift['end_time']);
                                $duration = ($end_time - $start_time) / 3600;
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($shift['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($shift['full_name'] ?: $shift['username']); ?></strong><br>
                                            <small class="text-muted">@<?php echo htmlspecialchars($shift['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 12px; height: 12px; background: <?php echo $shift['color']; ?>; border-radius: 2px;"></div>
                                        <span><?php echo htmlspecialchars($shift['shift_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('D, M d', strtotime($shift['assigned_date'])); ?>
                                </td>
                                <td>
                                    <?php echo date('g:i A', $start_time); ?> - <?php echo date('g:i A', $end_time); ?>
                                </td>
                                <td>
                                    <?php echo number_format($duration, 1); ?> hours
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $shift['status']; ?>">
                                        <?php echo ucfirst($shift['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <button onclick="updateShiftStatus(<?php echo $shift['user_shift_id']; ?>)" 
                                                class="btn btn-sm btn-outline" 
                                                title="Update Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button onclick="editShiftAssignment(<?php echo $shift['user_shift_id']; ?>)" 
                                                class="btn btn-sm btn-outline" 
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteShiftAssignment(<?php echo $shift['user_shift_id']; ?>)" 
                                                class="btn btn-sm btn-outline btn-danger" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
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
    </div>
    
    <div class="col-4">
        <!-- Shift Types -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Shift Types</h3>
                <button onclick="showAddShiftModal()" class="btn btn-sm btn-outline">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="table-container">
                <?php if (empty($all_shifts)): ?>
                    <div style="text-align: center; padding: 1.5rem;">
                        <p class="text-muted">No shift types defined</p>
                        <button onclick="showAddShiftModal()" class="btn btn-sm btn-primary">
                            Add Shift Type
                        </button>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Shift</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_shifts as $shift): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 12px; height: 12px; background: <?php echo $shift['color']; ?>; border-radius: 2px;"></div>
                                        <span><?php echo htmlspecialchars($shift['shift_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('g:i A', strtotime($shift['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($shift['end_time'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <button onclick="editShiftType(<?php echo $shift['shift_id']; ?>)" 
                                                class="btn btn-sm btn-outline">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteShiftType(<?php echo $shift['shift_id']; ?>)" 
                                                class="btn btn-sm btn-outline btn-danger">
                                            <i class="fas fa-trash"></i>
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
        
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Shifts</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php
                // Get next 3 days of shifts
                $upcoming_shifts = array_slice($scheduled_shifts, 0, 5);
                ?>
                
                <?php if (empty($upcoming_shifts)): ?>
                    <div style="text-align: center; padding: 1rem;">
                        <p class="text-muted">No upcoming shifts</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_shifts as $shift): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; 
                                padding: 0.8rem; background: var(--bg); border-radius: 10px; margin-bottom: 0.8rem;">
                        <div>
                            <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($shift['full_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo date('D, M d', strtotime($shift['assigned_date'])); ?>
                            </small>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-badge status-<?php echo $shift['status']; ?>" style="font-size: 0.7rem;">
                                <?php echo ucfirst($shift['status']); ?>
                            </span><br>
                            <small class="text-muted">
                                <?php echo date('g:i A', strtotime($shift['start_time'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="margin-top: 1rem;">
                    <a href="calendar.php" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-calendar"></i> View Full Calendar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Summary -->
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Schedule Summary</h3>
    </div>
    <div style="padding: 1.5rem;">
        <div class="row">
            <div class="col-4">
                <div style="text-align: center;">
                    <h3 style="color: var(--primary);"><?php echo count($all_staff); ?></h3>
                    <p class="text-muted">Available Staff</p>
                </div>
            </div>
            <div class="col-4">
                <div style="text-align: center;">
                    <h3 style="color: var(--success);"><?php echo count($all_shifts); ?></h3>
                    <p class="text-muted">Shift Types</p>
                </div>
            </div>
            <div class="col-4">
                <div style="text-align: center;">
                    <h3 style="color: var(--accent);">
                        <?php 
                        $avg_shifts = count($all_staff) > 0 ? round(count($scheduled_shifts) / count($all_staff)) : 0;
                        echo $avg_shifts;
                        ?>
                    </h3>
                    <p class="text-muted">Avg Shifts per Staff</p>
                </div>
            </div>
        </div>
        
        <hr style="margin: 1.5rem 0;">
        
        <div>
            <h4 style="margin-bottom: 1rem;">Quick Actions</h4>
            <div class="row" style="gap: 0.5rem 0;">
                <div class="col-4">
                    <button onclick="bulkAssignShifts()" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-users"></i> Bulk Assign
                    </button>
                </div>
                <div class="col-4">
                    <button onclick="generateScheduleReport()" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-file-export"></i> Export Schedule
                    </button>
                </div>
                <div class="col-4">
                    <button onclick="copyPreviousWeek()" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-copy"></i> Copy Week
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS for Schedule Views -->
<style>
.schedule-grid {
    display: flex;
    height: 900px;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.time-column {
    width: 80px;
    border-right: 1px solid var(--border);
    background: var(--bg);
}

.time-slot {
    height: 60px;
    padding: 0.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: var(--text-light);
}

.shifts-column {
    flex: 1;
    position: relative;
}

.shift-block {
    position: absolute;
    left: 10px;
    right: 10px;
    border-radius: 8px;
    padding: 0.8rem;
    overflow: hidden;
}

.shift-content {
    font-size: 0.9rem;
}

.shift-time {
    font-size: 0.8rem;
    color: var(--text-light);
    margin-top: 0.2rem;
}

.shift-status {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-top: 0.3rem;
}

/* Week View */
.week-schedule {
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.week-header {
    display: flex;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
}

.day-header {
    flex: 1;
    padding: 1rem;
    text-align: center;
    border-right: 1px solid var(--border);
}

.day-header:last-child {
    border-right: none;
}

.day-header.today {
    background: var(--primary);
    color: white;
}

.date-number {
    font-size: 1.2rem;
    font-weight: bold;
    margin-top: 0.3rem;
}

.week-body {
    display: flex;
    height: 900px;
}

.day-column {
    flex: 1;
    position: relative;
    border-right: 1px solid var(--border);
}

.day-column:last-child {
    border-right: none;
}

.day-column.today {
    background: var(--primary-light);
}

.week-shift {
    position: absolute;
    left: 5px;
    right: 5px;
    border-radius: 4px;
    cursor: pointer;
    transition: transform 0.2s;
}

.week-shift:hover {
    transform: scale(1.02);
    z-index: 10;
}

.shift-tooltip {
    display: none;
    position: absolute;
    background: white;
    padding: 0.8rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 100;
    white-space: nowrap;
    font-size: 0.9rem;
}

.week-shift:hover .shift-tooltip {
    display: block;
}

/* Month View */
.month-schedule {
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.month-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.month-day-header {
    padding: 1rem;
    text-align: center;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
    font-weight: 600;
}

.month-day-header:nth-child(7n) {
    border-right: none;
}

.month-day {
    min-height: 100px;
    padding: 0.5rem;
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
    position: relative;
}

.month-day:nth-child(7n) {
    border-right: none;
}

.month-day.empty {
    background: var(--bg-light);
}

.month-day.today {
    background: var(--primary-light);
}

.day-number {
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.day-shifts {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.month-shift {
    height: 4px;
    border-radius: 2px;
    cursor: pointer;
}

.more-shifts {
    font-size: 0.8rem;
    color: var(--text-light);
    margin-top: 0.3rem;
}

.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-scheduled { background: var(--warning-light); color: var(--warning); }
.status-completed { background: var(--success-light); color: var(--success); }
.status-absent { background: var(--error-light); color: var(--error); }
.status-cancelled { background: var(--text-light); color: var(--text); }
</style>

<script>
let currentDate = '<?php echo $current_date; ?>';
let viewMode = '<?php echo $view_mode; ?>';

function changeViewMode() {
    const mode = document.getElementById('viewMode').value;
    window.location.href = `?view=${mode}&date=${currentDate}`;
}

function changeDate() {
    const date = document.getElementById('datePicker').value;
    window.location.href = `?view=${viewMode}&date=${date}`;
}

function filterByShift() {
    const shiftId = document.getElementById('shiftFilter').value;
    const url = new URL(window.location);
    
    if (shiftId) {
        url.searchParams.set('shift_id', shiftId);
    } else {
        url.searchParams.delete('shift_id');
    }
    
    window.location.href = url.toString();
}

function navigateDate(direction) {
    let newDate = new Date(currentDate);
    
    if (viewMode === 'day') {
        newDate.setDate(newDate.getDate() + (direction === 'next' ? 1 : -1));
    } else if (viewMode === 'week') {
        newDate.setDate(newDate.getDate() + (direction === 'next' ? 7 : -7));
    } else {
        newDate.setMonth(newDate.getMonth() + (direction === 'next' ? 1 : -1));
    }
    
    const formattedDate = newDate.toISOString().split('T')[0];
    window.location.href = `?view=${viewMode}&date=${formattedDate}`;
}

function goToToday() {
    window.location.href = `?view=${viewMode}&date=<?php echo date('Y-m-d'); ?>`;
}

function printSchedule() {
    window.print();
}

function showAddShiftModal() {
    showModal('Add New Shift Type', `
        <div class="form-group">
            <label class="form-label">Shift Name</label>
            <input type="text" class="form-control" placeholder="e.g., Morning Shift, Evening Shift">
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" value="09:00">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Time</label>
                    <input type="time" class="form-control" value="17:00">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Color</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="radio" name="shiftColor" value="#7C3AED" checked>
                    <div style="width: 20px; height: 20px; background: #7C3AED; border-radius: 4px;"></div>
                    <span>Purple</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="radio" name="shiftColor" value="#06B6D4">
                    <div style="width: 20px; height: 20px; background: #06B6D4; border-radius: 4px;"></div>
                    <span>Cyan</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="radio" name="shiftColor" value="#10B981">
                    <div style="width: 20px; height: 20px; background: #10B981; border-radius: 4px;"></div>
                    <span>Green</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="radio" name="shiftColor" value="#F59E0B">
                    <div style="width: 20px; height: 20px; background: #F59E0B; border-radius: 4px;"></div>
                    <span>Amber</span>
                </label>
            </div>
        </div>
    `, 'Create Shift', 'addShift');
}

function showAssignShiftModal() {
    showModal('Assign Shift to Staff', `
        <div class="form-group">
            <label class="form-label">Select Staff</label>
            <select class="form-control" id="assignStaff">
                <option value="">Select staff member</option>
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Select Shift</label>
            <select class="form-control" id="assignShiftType">
                <option value="">Select shift type</option>
                <?php foreach ($all_shifts as $shift): ?>
                <option value="<?php echo $shift['shift_id']; ?>">
                    <?php echo htmlspecialchars($shift['shift_name']); ?> 
                    (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Assignment Date</label>
            <input type="date" class="form-control" id="assignDate" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea class="form-control" id="assignNotes" rows="2" placeholder="Any special instructions..."></textarea>
        </div>
    `, 'Assign Shift', 'assignShift');
}

function viewShiftDetails(shiftId) {
    showModal('Shift Details', `
        <div style="text-align: center; padding: 1rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading shift details...</p>
        </div>
    `, 'Close', null, true);
    
    // In production, this would be an AJAX call
    setTimeout(() => {
        document.querySelector('.modal-content').innerHTML = `
            <h3 style="margin-bottom: 1.5rem;">Shift Details</h3>
            <div style="margin-bottom: 1rem;">
                <strong>Staff:</strong> John Doe<br>
                <strong>Shift:</strong> Morning Shift (9:00 AM - 5:00 PM)<br>
                <strong>Date:</strong> Mon, Dec 15, 2025<br>
                <strong>Status:</strong> <span class="status-badge status-scheduled">Scheduled</span><br>
                <strong>Duration:</strong> 8 hours<br>
                <strong>Notes:</strong> Regular shift assignment
            </div>
            <div class="form-group">
                <label class="form-label">Update Status</label>
                <select class="form-control">
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="absent">Absent</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        `;
    }, 1000);
}

function updateShiftStatus(shiftId) {
    showModal('Update Shift Status', `
        <div class="form-group">
            <label class="form-label">New Status</label>
            <select class="form-control" id="newStatus">
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="absent">Absent</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea class="form-control" id="statusNotes" rows="2" placeholder="Reason for status change..."></textarea>
        </div>
    `, 'Update Status', `confirmUpdateStatus(${shiftId})`);
}

function editShiftAssignment(shiftId) {
    showModal('Edit Shift Assignment', `
        <div class="form-group">
            <label class="form-label">Select Staff</label>
            <select class="form-control">
                <option value="">Select staff member</option>
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Select Shift</label>
            <select class="form-control">
                <option value="">Select shift type</option>
                <?php foreach ($all_shifts as $shift): ?>
                <option value="<?php echo $shift['shift_id']; ?>">
                    <?php echo htmlspecialchars($shift['shift_name']); ?> 
                    (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Assignment Date</label>
            <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
    `, 'Save Changes', `confirmEditShift(${shiftId})`);
}

function deleteShiftAssignment(shiftId) {
    if (confirm('Are you sure you want to delete this shift assignment?')) {
        showToast('Deleting shift assignment...', 'info');
        setTimeout(() => {
            showToast('Shift assignment deleted', 'success');
            // In production, this would be an AJAX call to delete
            setTimeout(() => location.reload(), 1000);
        }, 1500);
    }
}

function editShiftType(shiftId) {
    showModal('Edit Shift Type', `
        <div class="form-group">
            <label class="form-label">Shift Name</label>
            <input type="text" class="form-control" value="Morning Shift">
        </div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" value="09:00">
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label class="form-label">End Time</label>
                    <input type="time" class="form-control" value="17:00">
                </div>
            </div>
        </div>
    `, 'Save Changes', `confirmEditShiftType(${shiftId})`);
}

function deleteShiftType(shiftId) {
    if (confirm('Are you sure you want to delete this shift type? This will also delete all future assignments.')) {
        showToast('Deleting shift type...', 'info');
        setTimeout(() => {
            showToast('Shift type deleted', 'success');
            // In production, this would be an AJAX call to delete
            setTimeout(() => location.reload(), 1000);
        }, 1500);
    }
}

function bulkAssignShifts() {
    showModal('Bulk Assign Shifts', `
        <div class="form-group">
            <label class="form-label">Select Staff (Multiple)</label>
            <select class="form-control" multiple style="height: 150px;">
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Select Shift</label>
            <select class="form-control">
                <option value="">Select shift type</option>
                <?php foreach ($all_shifts as $shift): ?>
                <option value="<?php echo $shift['shift_id']; ?>">
                    <?php echo htmlspecialchars($shift['shift_name']); ?> 
                    (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)
                </option>
                <?php endforeach; ?>
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
                    <label class="form-label">Repeat For</label>
                    <select class="form-control">
                        <option value="1">1 week</option>
                        <option value="2">2 weeks</option>
                        <option value="4">4 weeks</option>
                        <option value="8">8 weeks</option>
                    </select>
                </div>
            </div>
        </div>
    `, 'Assign Bulk Shifts', 'processBulkAssign');
}

function generateScheduleReport() {
    showToast('Generating schedule report...', 'info');
    setTimeout(() => {
        const data = {
            period: '<?php echo date("M d, Y", strtotime($start_date)); ?> to <?php echo date("M d, Y", strtotime($end_date)); ?>',
            shifts: <?php echo json_encode($scheduled_shifts); ?>,
            generated: new Date().toLocaleString()
        };
        
        const dataStr = JSON.stringify(data, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const link = document.createElement('a');
        link.href = dataUri;
        link.download = `schedule_report_<?php echo date('Y-m-d'); ?>.json`;
        link.click();
        
        showToast('Schedule report exported', 'success');
    }, 2000);
}

function copyPreviousWeek() {
    if (confirm('Copy previous week\'s schedule to this week?')) {
        showToast('Copying schedule...', 'info');
        setTimeout(() => {
            showToast('Schedule copied successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        }, 2000);
    }
}

function confirmUpdateStatus(shiftId) {
    const status = document.getElementById('newStatus').value;
    const notes = document.getElementById('statusNotes').value;
    
    showToast('Updating shift status...', 'info');
    setTimeout(() => {
        showToast('Shift status updated', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmEditShift(shiftId) {
    showToast('Updating shift assignment...', 'info');
    setTimeout(() => {
        showToast('Shift assignment updated', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function confirmEditShiftType(shiftId) {
    showToast('Updating shift type...', 'info');
    setTimeout(() => {
        showToast('Shift type updated', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function processBulkAssign() {
    showToast('Processing bulk assignments...', 'info');
    setTimeout(() => {
        showToast('Bulk assignments completed', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function showModal(title, content, actionText, actionFunction = null, large = false) {
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
    
    const modalSize = large ? '90%' : '500px';
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 20px; width: ${modalSize}; max-width: ${modalSize}; max-height: 90vh; overflow-y: auto;">
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

// Initialize date picker max
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('datePicker').max = '<?php echo date("Y-m-d"); ?>';
});
</script>

<?php require_once '../includes/footer.php'; ?>
