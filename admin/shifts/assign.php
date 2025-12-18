<?php
// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin/shifts/assign.php
ob_start(); // Start output buffering

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_admin();

// Function to safely redirect
function safe_redirect($url) {
    ob_end_clean();
    header("Location: " . $url);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_shift'])) {
        $user_id = $_POST['user_id'];
        $shift_id = $_POST['shift_id'];
        $assigned_date = $_POST['assigned_date'];
        $notes = $_POST['notes'];
        
        // Check if shift already assigned for that date
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USER_SHIFTS WHERE user_id = ? AND assigned_date = ?");
        $stmt->execute([$user_id, $assigned_date]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "This staff already has a shift assigned for this date!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_USER_SHIFTS (user_id, shift_id, assigned_date, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $shift_id, $assigned_date, $notes]);
            
            $_SESSION['success'] = "Shift assigned successfully!";
        }
        safe_redirect("assign.php");
    }
    
    if (isset($_POST['bulk_assign'])) {
        $user_ids = $_POST['user_ids'] ?? [];
        $shift_id = $_POST['bulk_shift_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $days = $_POST['days'] ?? [];
        
        if (empty($user_ids)) {
            $_SESSION['error'] = "Please select at least one staff member!";
            safe_redirect("assign.php");
        }
        
        if (empty($shift_id)) {
            $_SESSION['error'] = "Please select a shift template!";
            safe_redirect("assign.php");
        }
        
        if (empty($days)) {
            $_SESSION['error'] = "Please select at least one day of the week!";
            safe_redirect("assign.php");
        }
        
        // Validate dates
        if (strtotime($start_date) > strtotime($end_date)) {
            $_SESSION['error'] = "Start date cannot be after end date!";
            safe_redirect("assign.php");
        }
        
        // Generate dates between start and end
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            new DateTime($end_date . ' 23:59:59')
        );
        
        $assigned_count = 0;
        $errors = [];
        
        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $day_of_week = $date->format('w'); // 0 (Sunday) to 6 (Saturday)
            
            // Check if day is selected
            if (in_array($day_of_week, $days)) {
                foreach ($user_ids as $user_id) {
                    // Check if already assigned
                    $check_stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USER_SHIFTS WHERE user_id = ? AND assigned_date = ?");
                    $check_stmt->execute([$user_id, $date_str]);
                    
                    if ($check_stmt->rowCount() == 0) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_USER_SHIFTS (user_id, shift_id, assigned_date) VALUES (?, ?, ?)");
                            $stmt->execute([$user_id, $shift_id, $date_str]);
                            $assigned_count++;
                        } catch (Exception $e) {
                            $errors[] = "Error assigning shift to user $user_id on $date_str: " . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        if ($assigned_count > 0) {
            $_SESSION['success'] = "Bulk assignment completed! $assigned_count shifts assigned.";
            if (!empty($errors)) {
                $_SESSION['warning'] = implode('<br>', $errors);
            }
        } else {
            $_SESSION['error'] = "No shifts were assigned. All selected staff may already have shifts on the selected dates.";
        }
        safe_redirect("assign.php");
    }
    
    if (isset($_POST['update_status'])) {
        $user_shift_id = $_POST['user_shift_id'];
        $status = $_POST['status'];
        $notes = $_POST['update_notes'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE EASYSALLES_USER_SHIFTS SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE user_shift_id = ?");
        $stmt->execute([$status, "\n" . date('Y-m-d H:i:s') . ': ' . $notes, $user_shift_id]);
        
        $_SESSION['success'] = "Shift status updated!";
        safe_redirect("assign.php");
    }
    
    safe_redirect("assign.php");
}

// Set page title
$page_title = "Assign Shifts";
require_once ROOT_PATH . 'admin/includes/header.php';

// Get active staff members
$stmt = $pdo->query("SELECT * FROM EASYSALLES_USERS WHERE role = 2 AND status = 'active' ORDER BY full_name");
$staff = $stmt->fetchAll();

// Get shift templates
$stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
$shifts = $stmt->fetchAll();

// Get upcoming assigned shifts
$stmt = $pdo->query("SELECT us.*, u.full_name, u.username, s.shift_name, s.start_time, s.end_time, s.color 
                     FROM EASYSALLES_USER_SHIFTS us
                     JOIN EASYSALLES_USERS u ON us.user_id = u.user_id
                     JOIN EASYSALLES_SHIFTS s ON us.shift_id = s.shift_id
                     WHERE us.assigned_date >= CURDATE()
                     ORDER BY us.assigned_date, s.start_time");
$assigned_shifts = $stmt->fetchAll();
?>

<style>
/* Shift Assignment Styles */
.shift-assignment {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeIn 0.6s ease-out;
}

.assignment-header {
    margin-bottom: 2.5rem;
    text-align: center;
}

.assignment-header h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.75rem;
}

.assignment-header p {
    color: #64748b;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Assignment Layout */
.assignment-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

@media (max-width: 1024px) {
    .assignment-layout {
        grid-template-columns: 1fr;
    }
}

/* Assignment Cards */
.assignment-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.assignment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.card-header i {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
}

.single-assign .card-header i {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.bulk-assign .card-header i {
    background: linear-gradient(135deg, #10B981, #059669);
}

.upcoming-shifts .card-header i {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
}

.card-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
    font-family: 'Poppins', sans-serif;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
    color: var(--text);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

/* Staff Selection Grid */
.staff-grid {
    max-height: 200px;
    overflow-y: auto;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
}

.staff-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.staff-item:hover {
    background: rgba(124, 58, 237, 0.05);
}

.staff-checkbox {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.staff-checkbox.checked {
    background: var(--primary);
    border-color: var(--primary);
}

.staff-checkbox.checked::after {
    content: '✓';
    color: white;
    font-size: 0.9rem;
    font-weight: bold;
}

/* Days Selection */
.days-selection {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.day-checkbox {
    display: none;
}

.day-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem;
    border-radius: 8px;
    background: var(--bg);
    border: 2px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.day-label:hover {
    border-color: var(--primary);
}

.day-checkbox:checked + .day-label {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: var(--primary);
    color: white;
}

/* Upcoming Shifts */
.upcoming-shifts-list {
    max-height: 500px;
    overflow-y: auto;
}

.shift-assignment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s ease;
}

.shift-assignment-item:hover {
    background: rgba(124, 58, 237, 0.05);
    border-radius: 8px;
}

.shift-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.shift-color {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.staff-details h4 {
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--text);
}

.staff-details p {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0;
}

.shift-details {
    text-align: right;
}

.shift-date {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--text);
}

.shift-time {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.shift-status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-scheduled { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }
.status-completed { background: rgba(16, 185, 129, 0.1); color: #10B981; }
.status-cancelled { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
.status-absent { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    padding: 0.875rem 1.5rem;
    border-radius: 10px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
}

.btn-select-all {
    background: rgba(124, 58, 237, 0.1);
    color: var(--primary);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    margin-top: 0.5rem;
    transition: all 0.3s ease;
}

.btn-select-all:hover {
    background: rgba(124, 58, 237, 0.2);
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border-left: 4px solid #10B981;
    color: #065F46;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border-left: 4px solid #EF4444;
    color: #7F1D1D;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-left: 4px solid #F59E0B;
    color: #92400E;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    padding: 0;
    border: 1px solid var(--border);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-outline {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border);
    background: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Responsive */
@media (max-width: 768px) {
    .shift-assignment {
        padding: 1rem;
    }
    
    .assignment-header h1 {
        font-size: 1.75rem;
    }
    
    .card-header h2 {
        font-size: 1.25rem;
    }
    
    .days-selection {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .shift-assignment-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .shift-details {
        text-align: left;
        width: 100%;
    }
}
</style>

<div class="shift-assignment">
    <div class="assignment-header">
        <h1>Assign Shifts to Staff</h1>
        <p>Schedule and manage shifts for your team members</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
        </div>
    <?php endif; ?>

    <div class="assignment-layout">
        <!-- Single Shift Assignment -->
        <div class="assignment-card single-assign">
            <div class="card-header">
                <i class="fas fa-user-clock"></i>
                <h2>Assign Single Shift</h2>
            </div>
            
            <form method="POST" onsubmit="return validateSingleShift()">
                <div class="form-group">
                    <label for="user_id">
                        <i class="fas fa-user"></i> Staff Member
                    </label>
                    <select class="form-control" id="user_id" name="user_id" required>
                        <option value="">Select staff member...</option>
                        <?php foreach ($staff as $member): ?>
                        <option value="<?php echo $member['user_id']; ?>">
                            <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="shift_id">
                        <i class="fas fa-clock"></i> Shift
                    </label>
                    <select class="form-control" id="shift_id" name="shift_id" required>
                        <option value="">Select shift...</option>
                        <?php foreach ($shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?> 
                            (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="assigned_date">
                        <i class="fas fa-calendar-day"></i> Date
                    </label>
                    <input type="date" class="form-control" id="assigned_date" name="assigned_date" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="notes">
                        <i class="fas fa-sticky-note"></i> Notes
                    </label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                              placeholder="Any special instructions..."></textarea>
                </div>
                
                <button type="submit" name="assign_shift" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i> Assign Shift
                </button>
            </form>
        </div>

        <!-- Bulk Assignment -->
        <div class="assignment-card bulk-assign">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <h2>Bulk Assign Shifts</h2>
            </div>
            
            <form method="POST" onsubmit="return validateBulkAssignment()">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-friends"></i> Select Staff Members
                    </label>
                    <div class="staff-grid" id="staffGrid">
                        <?php foreach ($staff as $member): ?>
                        <div class="staff-item" data-user-id="<?php echo $member['user_id']; ?>">
                            <div class="staff-checkbox"></div>
                            <input type="checkbox" name="user_ids[]" 
                                   value="<?php echo $member['user_id']; ?>" 
                                   style="display: none;">
                            <div class="staff-info">
                                <h4><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></h4>
                                <span><?php echo htmlspecialchars($member['username']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-select-all" onclick="toggleSelectAll()">
                        <i class="fas fa-check-double"></i> Select All Staff
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="bulk_shift_id">
                        <i class="fas fa-clock"></i> Shift Template
                    </label>
                    <select class="form-control" id="bulk_shift_id" name="bulk_shift_id" required>
                        <option value="">Select shift...</option>
                        <?php foreach ($shifts as $shift): ?>
                        <option value="<?php echo $shift['shift_id']; ?>">
                            <?php echo htmlspecialchars($shift['shift_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="row">
                        <div class="col-6">
                            <label for="start_date">
                                <i class="fas fa-calendar-alt"></i> Start Date
                            </label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-6">
                            <label for="end_date">
                                <i class="fas fa-calendar-alt"></i> End Date
                            </label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-calendar-week"></i> Days of Week
                    </label>
                    <div class="days-selection">
                        <?php 
                        $days = [
                            ['value' => '1', 'letter' => 'M', 'name' => 'Mon'],
                            ['value' => '2', 'letter' => 'T', 'name' => 'Tue'],
                            ['value' => '3', 'letter' => 'W', 'name' => 'Wed'],
                            ['value' => '4', 'letter' => 'T', 'name' => 'Thu'],
                            ['value' => '5', 'letter' => 'F', 'name' => 'Fri'],
                            ['value' => '6', 'letter' => 'S', 'name' => 'Sat'],
                            ['value' => '0', 'letter' => 'S', 'name' => 'Sun']
                        ];
                        foreach ($days as $day): ?>
                        <div>
                            <input type="checkbox" class="day-checkbox" name="days[]" 
                                   value="<?php echo $day['value']; ?>" 
                                   id="day_<?php echo $day['value']; ?>">
                            <label for="day_<?php echo $day['value']; ?>" class="day-label">
                                <span><?php echo $day['letter']; ?></span>
                                <small><?php echo $day['name']; ?></small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-select-all" onclick="toggleSelectAllDays()">
                        <i class="fas fa-check-double"></i> Select Weekdays
                    </button>
                </div>
                
                <button type="submit" name="bulk_assign" class="btn-primary">
                    <i class="fas fa-layer-group"></i> Bulk Assign Shifts
                </button>
            </form>
        </div>
    </div>

    <!-- Upcoming Shifts -->
    <div class="assignment-card upcoming-shifts">
        <div class="card-header">
            <i class="fas fa-calendar-check"></i>
            <h2>Upcoming Shifts</h2>
        </div>
        
        <?php if (empty($assigned_shifts)): ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>No upcoming shifts scheduled</p>
            </div>
        <?php else: ?>
            <div class="upcoming-shifts-list">
                <?php foreach ($assigned_shifts as $assignment): 
                    $status_class = [
                        'scheduled' => 'status-scheduled',
                        'completed' => 'status-completed',
                        'cancelled' => 'status-cancelled',
                        'absent' => 'status-absent'
                    ];
                ?>
                <div class="shift-assignment-item">
                    <div class="shift-info">
                        <div class="shift-color" style="background: <?php echo $assignment['color']; ?>;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="staff-details">
                            <h4><?php echo htmlspecialchars($assignment['full_name'] ?: $assignment['username']); ?></h4>
                            <p><?php echo htmlspecialchars($assignment['shift_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="shift-details">
                        <div class="shift-date">
                            <?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?>
                        </div>
                        <div class="shift-time">
                            <?php echo date('h:i A', strtotime($assignment['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($assignment['end_time'])); ?>
                        </div>
                        <span class="shift-status <?php echo $status_class[$assignment['status']]; ?>">
                            <?php echo ucfirst($assignment['status']); ?>
                        </span>
                        
                        <div style="margin-top: 0.5rem;">
                            <button type="button" class="btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.85rem;"
                                    onclick="updateStatus(<?php echo $assignment['user_shift_id']; ?>, '<?php echo $assignment['status']; ?>')">
                                <i class="fas fa-edit"></i> Update
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Update Shift Status</h4>
            <button type="button" class="close-modal">&times;</button>
        </div>
        
        <form method="POST" id="statusForm">
            <div class="modal-body">
                <input type="hidden" name="user_shift_id" id="status_user_shift_id">
                <input type="hidden" name="update_status" value="1">
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_notes">Notes</label>
                    <textarea class="form-control" id="update_notes" name="update_notes" rows="3" 
                              placeholder="Add notes about status change..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-outline close-modal">Cancel</button>
                <button type="submit" class="btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize dates
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    const nextWeekStr = nextWeek.toISOString().split('T')[0];
    
    // Set default dates
    document.getElementById('start_date').value = today;
    document.getElementById('end_date').value = nextWeekStr;
    document.getElementById('assigned_date').value = today;
    
    // Initialize staff selection
    initStaffSelection();
    
    // Select weekdays by default
    [1,2,3,4,5].forEach(day => {
        const checkbox = document.getElementById('day_' + day);
        if (checkbox) checkbox.checked = true;
    });
});

// Staff selection functionality
function initStaffSelection() {
    const staffItems = document.querySelectorAll('.staff-item');
    staffItems.forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const visualCheckbox = item.querySelector('.staff-checkbox');
        
        item.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            
            checkbox.checked = !checkbox.checked;
            visualCheckbox.classList.toggle('checked', checkbox.checked);
        });
        
        // Initialize visual state
        visualCheckbox.classList.toggle('checked', checkbox.checked);
    });
}

function toggleSelectAll() {
    const staffItems = document.querySelectorAll('.staff-item');
    const allChecked = Array.from(staffItems).every(item => 
        item.querySelector('input[type="checkbox"]').checked
    );
    
    staffItems.forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const visualCheckbox = item.querySelector('.staff-checkbox');
        
        checkbox.checked = !allChecked;
        visualCheckbox.classList.toggle('checked', checkbox.checked);
    });
}

function toggleSelectAllDays() {
    const weekdays = [1,2,3,4,5]; // Monday to Friday
    const allChecked = weekdays.every(day => {
        const checkbox = document.getElementById('day_' + day);
        return checkbox ? checkbox.checked : false;
    });
    
    weekdays.forEach(day => {
        const checkbox = document.getElementById('day_' + day);
        if (checkbox) {
            checkbox.checked = !allChecked;
        }
    });
}

// Validation functions
function validateSingleShift() {
    const userId = document.getElementById('user_id').value;
    const shiftId = document.getElementById('shift_id').value;
    const date = document.getElementById('assigned_date').value;
    
    if (!userId) {
        alert('Please select a staff member');
        return false;
    }
    
    if (!shiftId) {
        alert('Please select a shift');
        return false;
    }
    
    if (!date) {
        alert('Please select a date');
        return false;
    }
    
    return true;
}

function validateBulkAssignment() {
    // Check if any staff is selected
    const selectedStaff = document.querySelectorAll('input[name="user_ids[]"]:checked');
    if (selectedStaff.length === 0) {
        alert('Please select at least one staff member');
        return false;
    }
    
    // Check if shift is selected
    const shiftId = document.getElementById('bulk_shift_id').value;
    if (!shiftId) {
        alert('Please select a shift template');
        return false;
    }
    
    // Check dates
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return false;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        alert('Start date cannot be after end date');
        return false;
    }
    
    // Check if any day is selected
    const selectedDays = document.querySelectorAll('input[name="days[]"]:checked');
    if (selectedDays.length === 0) {
        alert('Please select at least one day of the week');
        return false;
    }
    
    return true;
}

// Update shift status modal
function updateStatus(userShiftId, currentStatus) {
    document.getElementById('status_user_shift_id').value = userShiftId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
}

// Close modal
document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('statusModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('statusModal');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

<?php 
ob_flush(); // Flush output buffer
require_once ROOT_PATH . 'admin/includes/footer.php'; 
?>