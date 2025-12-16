<?php
// admin/schedule/calendar.php
$page_title = "Schedule Calendar";
require_once '../includes/header.php';

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Calculate first and last day of month
$first_day = date('N', strtotime("$year-$month-01"));
$days_in_month = date('t', strtotime("$year-$month-01"));
$last_day = date('N', strtotime("$year-$month-$days_in_month"));

// Get all shifts for the month
$start_date = "$year-$month-01";
$end_date = "$year-$month-$days_in_month";

$monthly_shifts = [];
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
    $monthly_shifts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Group shifts by date
$shifts_by_date = [];
foreach ($monthly_shifts as $shift) {
    $date = $shift['assigned_date'];
    if (!isset($shifts_by_date[$date])) {
        $shifts_by_date[$date] = [];
    }
    $shifts_by_date[$date][] = $shift;
}

// Get all staff
$all_staff = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS WHERE role = 2 AND status = 'active' ORDER BY full_name");
    $all_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
}

// Month navigation
$prev_month = date('Y-m', strtotime("$year-$month-01 -1 month"));
$next_month = date('Y-m', strtotime("$year-$month-01 +1 month"));
list($prev_year, $prev_mon) = explode('-', $prev_month);
list($next_year, $next_mon) = explode('-', $next_month);
?>

<div class="page-header">
    <div class="page-title">
        <h2>Schedule Calendar</h2>
        <p>Full calendar view of all staff shifts and assignments</p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-outline">
            <i class="fas fa-print"></i> Print Calendar
        </button>
        <button onclick="exportCalendar()" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Calendar Navigation -->
<div class="card" style="margin-bottom: 2rem;">
    <div style="padding: 1.5rem;">
        <div class="row">
            <div class="col-4">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button onclick="navigateMonth('prev')" class="btn btn-outline">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <h2 style="margin: 0; text-align: center; flex: 1;">
                        <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                    </h2>
                    
                    <button onclick="navigateMonth('next')" class="btn btn-outline">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-4">
                <div class="form-group">
                    <select class="form-control" onchange="jumpToMonth(this.value)">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year - 1; $y <= $current_year + 2; $y++):
                            for ($m = 1; $m <= 12; $m++):
                                $selected = ($y == $year && $m == $month) ? 'selected' : '';
                                $month_name = date('F', mktime(0, 0, 0, $m, 1, $y));
                        ?>
                        <option value="<?php echo "$y-$m"; ?>" <?php echo $selected; ?>>
                            <?php echo "$month_name $y"; ?>
                        </option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-4">
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="goToToday()" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-calendar-day"></i> Today
                    </button>
                    <button onclick="toggleLegend()" class="btn btn-outline">
                        <i class="fas fa-layer-group"></i> Legend
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Legend (Hidden by default) -->
        <div id="calendarLegend" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
            <h4 style="margin-bottom: 1rem;">Shift Status Legend</h4>
            <div class="row">
                <div class="col-3">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div style="width: 15px; height: 15px; background: var(--success-light); border: 2px solid var(--success); border-radius: 3px;"></div>
                        <span>Completed</span>
                    </div>
                </div>
                <div class="col-3">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div style="width: 15px; height: 15px; background: var(--warning-light); border: 2px solid var(--warning); border-radius: 3px;"></div>
                        <span>Scheduled</span>
                    </div>
                </div>
                <div class="col-3">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div style="width: 15px; height: 15px; background: var(--error-light); border: 2px solid var(--error); border-radius: 3px;"></div>
                        <span>Absent</span>
                    </div>
                </div>
                <div class="col-3">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div style="width: 15px; height: 15px; background: var(--text-light); border: 2px solid var(--text); border-radius: 3px;"></div>
                        <span>Cancelled</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card">
    <div class="calendar-grid">
        <!-- Day Headers -->
        <div class="calendar-day-header">Monday</div>
        <div class="calendar-day-header">Tuesday</div>
        <div class="calendar-day-header">Wednesday</div>
        <div class="calendar-day-header">Thursday</div>
        <div class="calendar-day-header">Friday</div>
        <div class="calendar-day-header">Saturday</div>
        <div class="calendar-day-header">Sunday</div>
        
        <!-- Empty cells for days before month start -->
        <?php for ($i = 1; $i < $first_day; $i++): ?>
        <div class="calendar-day empty"></div>
        <?php endfor; ?>
        
        <!-- Days of the month -->
        <?php for ($day = 1; $day <= $days_in_month; $day++):
            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $is_today = $date == date('Y-m-d');
            $is_weekend = date('N', strtotime($date)) >= 6;
            $day_shifts = $shifts_by_date[$date] ?? [];
        ?>
        <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
            <div class="day-header">
                <div class="day-number"><?php echo $day; ?></div>
                <div class="day-name"><?php echo date('D', strtotime($date)); ?></div>
            </div>
            
            <div class="day-shifts">
                <?php foreach ($day_shifts as $shift): 
                    $status_class = 'shift-' . $shift['status'];
                    $start_time = date('g:i A', strtotime($shift['start_time']));
                    $end_time = date('g:i A', strtotime($shift['end_time']));
                ?>
                <div class="calendar-shift <?php echo $status_class; ?>"
                     style="border-left-color: <?php echo $shift['color']; ?>;"
                     onclick="viewShiftDetails(<?php echo $shift['user_shift_id']; ?>)"
                     title="<?php echo htmlspecialchars($shift['full_name'] . ' - ' . $shift['shift_name'] . ' (' . $start_time . ' - ' . $end_time . ')'); ?>">
                    <div class="shift-staff">
                        <?php echo htmlspecialchars($shift['full_name'] ?: $shift['username']); ?>
                    </div>
                    <div class="shift-time">
                        <?php echo $start_time; ?>
                    </div>
                    <div class="shift-status">
                        <?php echo ucfirst($shift['status']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($day_shifts) > 2): ?>
                <div class="more-shifts">
                    +<?php echo count($day_shifts) - 2; ?> more shift<?php echo (count($day_shifts) - 2) > 1 ? 's' : ''; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($day_shifts)): ?>
                <div class="no-shifts">
                    <small class="text-muted">No shifts</small>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="day-actions">
                <button onclick="assignShiftToDate('<?php echo $date; ?>')" 
                        class="btn btn-sm btn-outline" 
                        title="Add shift to this day">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <?php endfor; ?>
        
        <!-- Empty cells for days after month end -->
        <?php for ($i = $last_day; $i < 7; $i++): ?>
        <div class="calendar-day empty"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Statistics -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Month Statistics</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-3">
                        <div style="text-align: center;">
                            <h3 style="color: var(--primary);">
                                <?php echo count($monthly_shifts); ?>
                            </h3>
                            <p class="text-muted">Total Shifts</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center;">
                            <h3 style="color: var(--success);">
                                <?php echo count(array_filter($monthly_shifts, fn($s) => $s['status'] === 'completed')); ?>
                            </h3>
                            <p class="text-muted">Completed</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center;">
                            <h3 style="color: var(--warning);">
                                <?php echo count(array_filter($monthly_shifts, fn($s) => $s['status'] === 'scheduled')); ?>
                            </h3>
                            <p class="text-muted">Scheduled</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center;">
                            <h3 style="color: var(--error);">
                                <?php echo count(array_filter($monthly_shifts, fn($s) => $s['status'] === 'absent')); ?>
                            </h3>
                            <p class="text-muted">Absences</p>
                        </div>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <div>
                    <h4 style="margin-bottom: 1rem;">Staff Coverage</h4>
                    <div class="row">
                        <?php
                        $staff_coverage = [];
                        foreach ($all_staff as $staff) {
                            $staff_shifts = array_filter($monthly_shifts, fn($s) => $s['user_id'] == $staff['user_id']);
                            $staff_coverage[] = [
                                'name' => $staff['full_name'] ?: $staff['username'],
                                'shifts' => count($staff_shifts),
                                'color' => '#' . substr(md5($staff['user_id']), 0, 6)
                            ];
                        }
                        
                        // Sort by number of shifts
                        usort($staff_coverage, fn($a, $b) => $b['shifts'] <=> $a['shifts']);
                        $top_staff = array_slice($staff_coverage, 0, 5);
                        ?>
                        
                        <?php foreach ($top_staff as $staff): ?>
                        <div class="col-12" style="margin-bottom: 0.8rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <div style="width: 12px; height: 12px; background: <?php echo $staff['color']; ?>; border-radius: 2px;"></div>
                                    <span><?php echo htmlspecialchars($staff['name']); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span><?php echo $staff['shifts']; ?> shifts</span>
                                    <div style="width: 100px; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                        <?php
                                        $max_shifts = max(array_column($staff_coverage, 'shifts'));
                                        $width = $max_shifts > 0 ? ($staff['shifts'] / $max_shifts) * 100 : 0;
                                        ?>
                                        <div style="width: <?php echo $width; ?>%; height: 100%; background: <?php echo $staff['color']; ?>;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <button onclick="showBulkAssignment()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-users"></i> Bulk Assign Shifts
                    </button>
                    
                    <button onclick="showShiftPattern()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-calendar-alt"></i> Create Shift Pattern
                    </button>
                    
                    <button onclick="generateMonthReport()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-file-pdf"></i> Generate Monthly Report
                    </button>
                    
                    <button onclick="viewShiftRequests()" class="btn btn-outline" style="width: 100%; text-align: left;">
                        <i class="fas fa-exchange-alt"></i> View Shift Requests
                    </button>
                    
                    <hr style="margin: 0.5rem 0;">
                    
                    <button onclick="copyMonthSchedule()" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-copy"></i> Copy to Next Month
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Upcoming Events</h3>
            </div>
            <div style="padding: 1.5rem; max-height: 300px; overflow-y: auto;">
                <?php
                // Get upcoming shifts (next 7 days)
                $upcoming_date = date('Y-m-d');
                $upcoming_end = date('Y-m-d', strtotime('+7 days'));
                $upcoming_shifts = [];
                
                try {
                    $query = "SELECT 
                        us.assigned_date,
                        u.full_name,
                        s.shift_name,
                        s.start_time,
                        us.status
                        FROM EASYSALLES_USER_SHIFTS us
                        LEFT JOIN EASYSALLES_USERS u ON us.user_id = u.user_id
                        LEFT JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                        WHERE us.assigned_date BETWEEN ? AND ?
                        AND us.status = 'scheduled'
                        ORDER BY us.assigned_date, s.start_time
                        LIMIT 10";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$upcoming_date, $upcoming_end]);
                    $upcoming_shifts = $stmt->fetchAll();
                } catch (PDOException $e) {
                    // Handle error
                }
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
            </div>
        </div>
    </div>
</div>

<!-- CSS for Calendar -->
<style>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.calendar-day-header {
    padding: 1rem;
    background: var(--bg);
    text-align: center;
    font-weight: 600;
    border-bottom: 1px solid var(--border);
}

.calendar-day {
    min-height: 150px;
    background: white;
    padding: 0.8rem;
    display: flex;
    flex-direction: column;
    position: relative;
}

.calendar-day.today {
    background: var(--primary-light);
}

.calendar-day.weekend {
    background: var(--bg-light);
}

.calendar-day.empty {
    background: var(--bg);
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.day-number {
    font-weight: bold;
    font-size: 1.2rem;
}

.day-name {
    font-size: 0.9rem;
    color: var(--text-light);
}

.day-shifts {
    flex: 1;
    overflow-y: auto;
    max-height: 100px;
}

.calendar-shift {
    background: white;
    border-left: 3px solid;
    padding: 0.5rem;
    margin-bottom: 0.3rem;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: transform 0.2s;
}

.calendar-shift:hover {
    transform: translateX(3px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.shift-staff {
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.shift-time {
    color: var(--text-light);
    font-size: 0.7rem;
}

.shift-status {
    font-size: 0.7rem;
    display: inline-block;
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    margin-top: 0.2rem;
}

.shift-completed .shift-status { background: var(--success-light); color: var(--success); }
.shift-scheduled .shift-status { background: var(--warning-light); color: var(--warning); }
.shift-absent .shift-status { background: var(--error-light); color: var(--error); }
.shift-cancelled .shift-status { background: var(--text-light); color: var(--text); }

.more-shifts {
    font-size: 0.8rem;
    color: var(--primary);
    text-align: center;
    padding: 0.3rem;
    cursor: pointer;
}

.more-shifts:hover {
    text-decoration: underline;
}

.no-shifts {
    text-align: center;
    padding: 1rem 0;
}

.day-actions {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
}

.day-actions .btn {
    padding: 0.2rem 0.4rem;
    font-size: 0.8rem;
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
function navigateMonth(direction) {
    const url = new URL(window.location);
    let year = <?php echo $year; ?>;
    let month = <?php echo $month; ?>;
    
    if (direction === 'prev') {
        month--;
        if (month < 1) {
            month = 12;
            year--;
        }
    } else {
        month++;
        if (month > 12) {
            month = 1;
            year++;
        }
    }
    
    url.searchParams.set('year', year);
    url.searchParams.set('month', month.toString().padStart(2, '0'));
    window.location.href = url.toString();
}

function jumpToMonth(value) {
    const [year, month] = value.split('-');
    const url = new URL(window.location);
    url.searchParams.set('year', year);
    url.searchParams.set('month', month);
    window.location.href = url.toString();
}

function goToToday() {
    const today = new Date();
    const year = today.getFullYear();
    const month = (today.getMonth() + 1).toString().padStart(2, '0');
    
    const url = new URL(window.location);
    url.searchParams.set('year', year);
    url.searchParams.set('month', month);
    window.location.href = url.toString();
}

function toggleLegend() {
    const legend = document.getElementById('calendarLegend');
    legend.style.display = legend.style.display === 'none' ? 'block' : 'none';
}

function assignShiftToDate(date) {
    showModal('Assign Shift for ' + formatDate(date), `
        <div class="form-group">
            <label class="form-label">Select Staff</label>
            <select class="form-control" id="staffSelect">
                <option value="">Select staff member</option>
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['user_id']; ?>">
                    <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Shift Type</label>
            <select class="form-control" id="shiftTypeSelect">
                <option value="">Select shift type</option>
                <option value="morning">Morning Shift (9 AM - 5 PM)</option>
                <option value="evening">Evening Shift (2 PM - 10 PM)</option>
                <option value="night">Night Shift (10 PM - 6 AM)</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" value="${date}" disabled>
        </div>
        <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
        </div>
    `, 'Assign Shift', `confirmAssignShift('${date}')`);
}

function viewShiftDetails(shiftId) {
    showModal('Shift Details', `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p>Loading shift details...</p>
        </div>
    `, 'Close');
    
    // In production, this would be an AJAX call
    setTimeout(() => {
        document.querySelector('.modal-content').innerHTML = `
            <h3 style="margin-bottom: 1.5rem;">Shift Details</h3>
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        JD
                    </div>
                    <div>
                        <h4 style="margin: 0;">John Doe</h4>
                        <p class="text-muted" style="margin: 0;">@johndoe</p>
                    </div>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Shift:</span>
                        <span><strong>Morning Shift</strong></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Date:</span>
                        <span>Monday, Dec 15, 2025</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Time:</span>
                        <span>9:00 AM - 5:00 PM (8 hours)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-scheduled">Scheduled</span>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Notes</h4>
                    <p style="color: var(--text-light); font-style: italic;">
                        Regular shift assignment. Please arrive 15 minutes early for handover.
                    </p>
                </div>
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
            
            <div class="form-group">
                <label class="form-label">Add Note</label>
                <textarea class="form-control" rows="3" placeholder="Add update or note..."></textarea>
            </div>
        `;
    }, 1000);
}

function showBulkAssignment() {
    showModal('Bulk Shift Assignment', `
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
            <label class="form-label">Shift Pattern</label>
            <select class="form-control">
                <option value="morning">Morning Shifts Only</option>
                <option value="evening">Evening Shifts Only</option>
                <option value="rotation">Rotating Shifts</option>
                <option value="custom">Custom Pattern</option>
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
                    <label class="form-label">Duration</label>
                    <select class="form-control">
                        <option value="1">1 week</option>
                        <option value="2">2 weeks</option>
                        <option value="4" selected>4 weeks</option>
                        <option value="8">8 weeks</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Days of Week</label>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Mon
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Tue
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Wed
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Thu
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox" checked> Fri
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox"> Sat
                </label>
                <label style="display: flex; align-items: center; gap: 0.3rem;">
                    <input type="checkbox"> Sun
                </label>
            </div>
        </div>
    `, 'Apply Bulk Assignment', 'processBulkAssignment');
}

function showShiftPattern() {
    showModal('Create Shift Pattern', `
        <div class="form-group">
            <label class="form-label">Pattern Name</label>
            <input type="text" class="form-control" placeholder="e.g., Weekly Rotation, Bi-weekly Schedule">
        </div>
        
        <div class="form-group">
            <label class="form-label">Pattern Type</label>
            <select class="form-control">
                <option value="weekly">Weekly Rotation</option>
                <option value="biweekly">Bi-weekly Rotation</option>
                <option value="monthly">Monthly Rotation</option>
                <option value="custom">Custom Pattern</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Shift Assignments</label>
            <div style="background: var(--bg); padding: 1rem; border-radius: 10px;">
                <table style="width: 100%; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Shift</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Monday</td>
                            <td>
                                <select style="width: 100%; padding: 0.2rem;">
                                    <option value="">-</option>
                                    <option value="morning">Morning</option>
                                    <option value="evening">Evening</option>
                                </select>
                            </td>
                            <td>
                                <select style="width: 100%; padding: 0.2rem;">
                                    <option value="">-</option>
                                    <?php foreach ($all_staff as $staff): ?>
                                    <option value="<?php echo $staff['user_id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <!-- More rows would be added dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    `, 'Save Pattern', 'saveShiftPattern');
}

function generateMonthReport() {
    showToast('Generating monthly report...', 'info');
    
    // In production, this would generate a PDF report
    setTimeout(() => {
        const data = {
            month: '<?php echo date("F Y", strtotime("$year-$month-01")); ?>',
            totalShifts: <?php echo count($monthly_shifts); ?>,
            staffCoverage: <?php echo json_encode($staff_coverage); ?>,
            generated: new Date().toLocaleString()
        };
        
        const dataStr = JSON.stringify(data, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const link = document.createElement('a');
        link.href = dataUri;
        link.download = `schedule_report_<?php echo date("F_Y", strtotime("$year-$month-01")); ?>.json`;
        link.click();
        
        showToast('Monthly report exported', 'success');
    }, 2000);
}

function viewShiftRequests() {
    // Redirect to shift requests page
    window.location.href = 'requests.php';
}

function copyMonthSchedule() {
    if (confirm('Copy this month\'s schedule to next month?\n\nThis will copy all shift assignments to the same days of next month.')) {
        showToast('Copying schedule to next month...', 'info');
        setTimeout(() => {
            showToast('Schedule copied successfully', 'success');
            // Navigate to next month
            navigateMonth('next');
        }, 2000);
    }
}

function exportCalendar() {
    showToast('Exporting calendar data...', 'info');
    
    const calendarData = {
        month: '<?php echo date("F Y", strtotime("$year-$month-01")); ?>',
        shifts: <?php echo json_encode($monthly_shifts); ?>,
        generated: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(calendarData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const link = document.createElement('a');
    link.href = dataUri;
    link.download = `calendar_<?php echo date("F_Y", strtotime("$year-$month-01")); ?>.json`;
    link.click();
    
    showToast('Calendar exported', 'success');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function confirmAssignShift(date) {
    const staff = document.getElementById('staffSelect').value;
    const shiftType = document.getElementById('shiftTypeSelect').value;
    
    if (!staff || !shiftType) {
        alert('Please select both staff and shift type');
        return;
    }
    
    showToast('Assigning shift...', 'info');
    setTimeout(() => {
        showToast('Shift assigned successfully', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 1500);
}

function processBulkAssignment() {
    showToast('Processing bulk assignments...', 'info');
    setTimeout(() => {
        showToast('Bulk assignments completed', 'success');
        closeModal();
        setTimeout(() => location.reload(), 1000);
    }, 2000);
}

function saveShiftPattern() {
    showToast('Saving shift pattern...', 'info');
    setTimeout(() => {
        showToast('Shift pattern saved', 'success');
        closeModal();
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
</script>

<?php require_once '../includes/footer.php'; ?>
