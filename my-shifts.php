<?php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// staff/shifts/my-shifts.php
require_once __DIR__ . 'config.php';
require_once __DIR__ . 'includes/auth.php';
require_login();

// Redirect admin to admin dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] == 1) {
    header('Location: /admin/dashboard.php');
    exit();
}

$page_title = 'My Shifts';
include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle shift request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_shift_change'])) {
    $request_type = $_POST['request_type'];
    $shift_id = $_POST['shift_id'];
    $requested_shift_id = $_POST['requested_shift_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    $priority = $_POST['priority'];
    
    $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SHIFT_REQUESTS 
                          (user_id, request_type, shift_id, requested_shift_id, start_date, end_date, reason, priority) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $request_type, $shift_id, $requested_shift_id, $start_date, $end_date, $reason, $priority]);
    
    $_SESSION['success'] = "Shift request submitted successfully!";
    header("Location: my-shifts.php");
    exit();
}

// Get upcoming shifts
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("SELECT us.*, s.shift_name, s.start_time, s.end_time, s.color 
                      FROM EASYSALLES_USER_SHIFTS us
                      JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                      WHERE us.user_id = ? AND us.assigned_date >= ?
                      ORDER BY us.assigned_date, s.start_time");
$stmt->execute([$user_id, $current_date]);
$upcoming_shifts = $stmt->fetchAll();

// Get shift history
$stmt = $pdo->prepare("SELECT us.*, s.shift_name, s.start_time, s.end_time 
                      FROM EASYSALLES_USER_SHIFTS us
                      JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                      WHERE us.user_id = ? AND us.assigned_date < ?
                      ORDER BY us.assigned_date DESC
                      LIMIT 20");
$stmt->execute([$user_id, $current_date]);
$shift_history = $stmt->fetchAll();

// Get my shift requests
$stmt = $pdo->prepare("SELECT * FROM EASYSALLES_SHIFT_REQUESTS 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll();

// Get available shifts for swapping
$stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
$all_shifts = $stmt->fetchAll();

// Get other staff for cover requests
$stmt = $pdo->query("SELECT user_id, full_name, username FROM EASYSALLES_USERS 
                    WHERE role = 2 AND status = 'active' AND user_id != ?");
$stmt->execute([$user_id]);
$other_staff = $stmt->fetchAll();
?>

<style>
    .calendar-day {
        border: 1px solid #e2e8f0;
        min-height: 120px;
        padding: 8px;
        position: relative;
    }
    
    .calendar-day.today {
        background-color: #f0f9ff;
        border-color: #0ea5e9;
    }
    
    .calendar-day-header {
        text-align: center;
        font-weight: 600;
        margin-bottom: 5px;
        color: #475569;
    }
    
    .calendar-shift {
        background-color: #f1f5f9;
        border-left: 4px solid #7c3aed;
        padding: 5px 8px;
        margin-bottom: 5px;
        border-radius: 3px;
        font-size: 0.85rem;
    }
    
    .shift-time {
        font-weight: 600;
        color: #475569;
    }
    
    .shift-status {
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 5px;
    }
    
    .status-scheduled { background-color: #dbeafe; color: #1d4ed8; }
    .status-completed { background-color: #dcfce7; color: #166534; }
    .status-absent { background-color: #fef3c7; color: #92400e; }
    .status-cancelled { background-color: #fee2e2; color: #991b1b; }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>My Shifts</h2>
        <p>View and manage your work schedule</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openRequestModal('swap')">
            <i class="fas fa-exchange-alt"></i> Request Shift Change
        </button>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Shifts</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Shift</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcoming_shifts)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-calendar-times" style="font-size: 2rem; color: var(--border);"></i>
                                    <p style="margin-top: 1rem;">No upcoming shifts scheduled</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($upcoming_shifts as $shift): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($shift['assigned_date'])); ?></td>
                                <td><?php echo date('D', strtotime($shift['assigned_date'])); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $shift['color']; ?>; color: white;">
                                        <?php echo htmlspecialchars($shift['shift_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $start = new DateTime($shift['start_time']);
                                    $end = new DateTime($shift['end_time']);
                                    $diff = $start->diff($end);
                                    echo $diff->h . 'h';
                                    if ($diff->i > 0) echo ' ' . $diff->i . 'm';
                                    ?>
                                </td>
                                <td>
                                    <span class="shift-status status-<?php echo $shift['status']; ?>">
                                        <?php echo ucfirst($shift['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($shift['status'] == 'scheduled' && strtotime($shift['assigned_date']) > strtotime('+1 day')): ?>
                                        <button class="btn btn-sm btn-outline" 
                                                onclick="requestForShift(<?php echo $shift['user_shift_id']; ?>, '<?php echo $shift['assigned_date']; ?>', <?php echo $shift['shift_id']; ?>)">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Shift Calendar View</h3>
                <div>
                    <button class="btn btn-outline btn-sm" onclick="changeMonth(-1)">← Prev</button>
                    <span id="currentMonth" style="margin: 0 1rem; font-weight: 600;"></span>
                    <button class="btn btn-outline btn-sm" onclick="changeMonth(1)">Next →</button>
                </div>
            </div>
            <div class="card-body">
                <div id="calendarContainer" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
                    <!-- Calendar will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Shift Requests</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Date Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_requests)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 1rem;">
                                    No shift requests
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($my_requests as $request): ?>
                            <tr>
                                <td>
                                    <?php
                                    $type_labels = [
                                        'swap' => 'Swap',
                                        'timeoff' => 'Time Off',
                                        'cover' => 'Cover',
                                        'change' => 'Change'
                                    ];
                                    echo $type_labels[$request['request_type']] ?? $request['request_type'];
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($request['start_date']) {
                                        echo date('M j', strtotime($request['start_date']));
                                        if ($request['end_date'] && $request['end_date'] != $request['start_date']) {
                                            echo ' - ' . date('j', strtotime($request['end_date']));
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'badge-warning',
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        'cancelled' => 'badge-secondary'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $status_class[$request['status']] ?? 'badge-secondary'; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j', strtotime($request['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Quick Stats</h3>
            </div>
            <div class="card-body">
                <?php
                $this_month = date('Y-m');
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as total_shifts,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_shifts,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_shifts
                    FROM EASYSALLES_USER_SHIFTS 
                    WHERE user_id = ? AND DATE_FORMAT(assigned_date, '%Y-%m') = ?");
                $stmt->execute([$user_id, $this_month]);
                $stats = $stmt->fetch();
                ?>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: var(--primary-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">
                            <?php echo $stats['total_shifts'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Total Shifts</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--success-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success);">
                            <?php echo $stats['completed_shifts'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Completed</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--warning-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning);">
                            <?php echo $stats['absent_shifts'] ?? 0; ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Absences</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: var(--info-light); border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--info);">
                            <?php 
                            $attendance_rate = $stats['total_shifts'] > 0 
                                ? round(($stats['completed_shifts'] / $stats['total_shifts']) * 100, 1) 
                                : 0;
                            echo $attendance_rate . '%';
                            ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">Attendance Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Modal -->
<div class="modal" id="requestModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Request Shift Change</h4>
            <button type="button" class="close" onclick="closeRequestModal()">&times;</button>
        </div>
        <form method="POST" id="requestForm">
            <div class="modal-body">
                <input type="hidden" name="request_shift_change" value="1">
                <input type="hidden" name="user_shift_id" id="request_user_shift_id">
                <input type="hidden" name="shift_date" id="request_shift_date">
                
                <div class="form-group">
                    <label for="request_type">Request Type</label>
                    <select class="form-control" id="request_type" name="request_type" required onchange="updateRequestForm()">
                        <option value="swap">Swap Shift</option>
                        <option value="timeoff">Time Off</option>
                        <option value="cover">Cover Request</option>
                        <option value="change">Shift Change</option>
                    </select>
                </div>
                
                <div id="shiftInfo" class="alert alert-info" style="display: none;">
                    Selected Shift: <span id="selectedShiftInfo"></span>
                </div>
                
                <div class="form-group" id="dateRangeGroup">
                    <label>Date Range</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" id="end_date" name="end_date">
                            <small class="text-muted">Leave empty for single day</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="shiftSelectionGroup" style="display: none;">
                    <label for="shift_id">Current Shift</label>
                    <select class="form-control" id="shift_id" name="shift_id">
                        <option value="">Select shift...</option>
                        <?php foreach ($all_shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?> 
                            (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="newShiftGroup" style="display: none;">
                    <label for="requested_shift_id">New Shift (Optional)</label>
                    <select class="form-control" id="requested_shift_id" name="requested_shift_id">
                        <option value="">Select new shift...</option>
                        <?php foreach ($all_shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="staffGroup" style="display: none;">
                    <label for="requested_user_id">Request Cover From</label>
                    <select class="form-control" id="requested_user_id" name="requested_user_id">
                        <option value="">Select staff member...</option>
                        <?php foreach ($other_staff as $staff): ?>
                        <option value="<?php echo $staff['user_id']; ?>">
                            <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" required 
                              placeholder="Please provide a reason for your request..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select class="form-control" id="priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

function generateCalendar(month, year) {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    
    document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
    
    const container = document.getElementById('calendarContainer');
    container.innerHTML = '';
    
    // Add day headers
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        container.appendChild(dayHeader);
    });
    
    // Get first day of month
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Add empty days
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        container.appendChild(emptyDay);
    }
    
    // Add days
    const today = new Date();
    const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        
        if (isCurrentMonth && day === today.getDate()) {
            dayDiv.classList.add('today');
        }
        
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        dayDiv.appendChild(dayHeader);
        
        // Add shift events for this day
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const shifts = <?php echo json_encode($upcoming_shifts); ?>;
        
        shifts.forEach(shift => {
            if (shift.assigned_date === dateStr) {
                const shiftDiv = document.createElement('div');
                shiftDiv.className = 'calendar-shift';
                shiftDiv.style.borderLeftColor = shift.color;
                shiftDiv.innerHTML = `
                    <div class="shift-time">${shift.shift_name}</div>
                    <div>${formatTime(shift.start_time)} - ${formatTime(shift.end_time)}</div>
                    <span class="shift-status status-${shift.status}">${shift.status}</span>
                `;
                dayDiv.appendChild(shiftDiv);
            }
        });
        
        container.appendChild(dayDiv);
    }
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    } else if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    generateCalendar(currentMonth, currentYear);
}

function openRequestModal(type) {
    document.getElementById('requestModal').style.display = 'block';
    if (type) {
        document.getElementById('request_type').value = type;
        updateRequestForm();
    }
}

function requestForShift(userShiftId, shiftDate, shiftId) {
    openRequestModal('swap');
    document.getElementById('request_user_shift_id').value = userShiftId;
    document.getElementById('request_shift_date').value = shiftDate;
    
    // Find shift info
    const shifts = <?php echo json_encode($upcoming_shifts); ?>;
    const shift = shifts.find(s => s.user_shift_id == userShiftId);
    if (shift) {
        document.getElementById('shiftInfo').style.display = 'block';
        document.getElementById('selectedShiftInfo').textContent = 
            `${shift.shift_name} on ${shiftDate} (${formatTime(shift.start_time)} - ${formatTime(shift.end_time)})`;
        document.getElementById('start_date').value = shiftDate;
        document.getElementById('end_date').value = shiftDate;
        document.getElementById('shift_id').value = shiftId;
    }
}

function updateRequestForm() {
    const type = document.getElementById('request_type').value;
    
    // Hide all optional groups first
    document.getElementById('shiftSelectionGroup').style.display = 'none';
    document.getElementById('newShiftGroup').style.display = 'none';
    document.getElementById('staffGroup').style.display = 'none';
    
    // Show relevant groups based on type
    switch(type) {
        case 'swap':
            document.getElementById('newShiftGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Swap';
            break;
        case 'cover':
            document.getElementById('staffGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Cover';
            break;
        case 'change':
            document.getElementById('newShiftGroup').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Request Shift Change';
            break;
        case 'timeoff':
            document.getElementById('modalTitle').textContent = 'Request Time Off';
            break;
    }
    
    // Show shift selection for non-specific requests
    if (!document.getElementById('request_user_shift_id').value) {
        document.getElementById('shiftSelectionGroup').style.display = 'block';
    }
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
    // Reset form
    document.getElementById('requestForm').reset();
    document.getElementById('request_user_shift_id').value = '';
    document.getElementById('shiftInfo').style.display = 'none';
}

// Initialize calendar
document.addEventListener('DOMContentLoaded', function() {
    generateCalendar(currentMonth, currentYear);
    
    // Set default dates in modal
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').value = today;
});
</script>

<?php include 'includes/footer.php'; ?>