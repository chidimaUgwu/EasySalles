<?php
// admin/shifts/assign.php
ob_start(); // Start output buffering

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$page_title = "Assign Shifts";
require_once ROOT_PATH . 'admin/includes/header.php';

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
    }
    
    if (isset($_POST['bulk_assign'])) {
        $user_ids = $_POST['user_ids'] ?? [];
        $shift_id = $_POST['bulk_shift_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $days = isset($_POST['days']) ? $_POST['days'] : [];
        
        // Generate dates between start and end
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            new DateTime($end_date . ' +1 day')
        );
        
        $assigned_count = 0;
        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $day_of_week = $date->format('w'); // 0 (Sunday) to 6 (Saturday)
            
            // Check if day is selected
            if (in_array($day_of_week, $days)) {
                foreach ($user_ids as $user_id) {
                    // Check if already assigned
                    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USER_SHIFTS WHERE user_id = ? AND assigned_date = ?");
                    $stmt->execute([$user_id, $date_str]);
                    
                    if ($stmt->rowCount() == 0) {
                        $stmt = $pdo->prepare("INSERT INTO EASYSALLES_USER_SHIFTS (user_id, shift_id, assigned_date) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $shift_id, $date_str]);
                        $assigned_count++;
                    }
                }
            }
        }
        
        $_SESSION['success'] = "Bulk assignment completed! $assigned_count shifts assigned.";
    }
    
    if (isset($_POST['update_status'])) {
        $user_shift_id = $_POST['user_shift_id'];
        $status = $_POST['status'];
        $notes = $_POST['update_notes'];
        
        $stmt = $pdo->prepare("UPDATE EASYSALLES_USER_SHIFTS SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE user_shift_id = ?");
        $stmt->execute([$status, "\n" . date('Y-m-d H:i:s') . ': ' . $notes, $user_shift_id]);
        
        $_SESSION['success'] = "Shift status updated!";
    }
    
    // JavaScript redirect to avoid header issues
    echo '<script>window.location.href = "assign.php";</script>';
    exit();
}

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
/* Dashboard Specific Styles */
.shift-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeIn 0.6s ease-out;
}

.dashboard-header {
    margin-bottom: 3rem;
    animation: fadeInDown 0.6s ease-out;
}

.welcome-section {
    text-align: center;
    margin-bottom: 2rem;
}

.welcome-text h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.welcome-text p {
    color: #64748b;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Assignment Grid */
.assignment-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin-bottom: 3rem;
}

@media (max-width: 1024px) {
    .assignment-grid {
        grid-template-columns: 1fr;
    }
}

/* Shift Cards */
.shift-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.shift-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
}

.shift-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.card-header i {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.single-shift .card-header i {
    background: linear-gradient(135deg, var(--primary), #8B5CF6);
}

.bulk-shift .card-header i {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}

.upcoming-shifts .card-header i {
    background: linear-gradient(135deg, #10B981, #059669);
}

.card-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
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
    padding: 0.875rem 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
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

.time-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Staff Selection */
.staff-selection {
    max-height: 200px;
    overflow-y: auto;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg);
}

.staff-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.staff-item:hover {
    background: rgba(124, 58, 237, 0.05);
}

.staff-checkbox {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
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

.staff-info h4 {
    font-weight: 600;
    margin: 0;
    color: var(--text);
}

.staff-info span {
    font-size: 0.85rem;
    color: #64748b;
}

/* Days Selection */
.days-selection {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.day-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.day-checkbox {
    display: none;
}

.day-label {
    width: 100%;
    padding: 0.75rem 0;
    border-radius: 10px;
    background: var(--bg);
    border: 2px solid var(--border);
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.day-label:hover {
    border-color: var(--primary);
}

.day-checkbox:checked + .day-label {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: var(--primary);
    color: white;
}

.day-label .day-letter {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.day-label .day-name {
    font-size: 0.75rem;
    opacity: 0.9;
}

/* Upcoming Shifts */
.upcoming-shifts-container {
    margin-top: 3rem;
}

.shifts-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.shift-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s ease;
}

.shift-item:hover {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    transform: translateX(5px);
    border-radius: 10px;
}

.shift-item:last-child {
    border-bottom: none;
}

.shift-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.shift-color {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.staff-details h4 {
    font-weight: 600;
    margin-bottom: 0.25rem;
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
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.25rem;
}

.shift-time {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.shift-status {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.status-scheduled {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.status-completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
}

.status-cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
}

.status-absent {
    background: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
}

.shift-actions {
    display: flex;
    gap: 0.5rem;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
}

.btn-select-all {
    background: rgba(124, 58, 237, 0.1);
    color: var(--primary);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    cursor: pointer;
    margin-top: 0.5rem;
    transition: all 0.3s ease;
}

.btn-select-all:hover {
    background: rgba(124, 58, 237, 0.2);
}

.btn-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn-edit {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.btn-edit:hover {
    background: #3B82F6;
    color: white;
    transform: scale(1.05);
}

/* Alert Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
    border-left: 4px solid #10B981;
    color: #065F46;
}

.alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
    border-left: 4px solid #EF4444;
    color: #7F1D1D;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    margin: 2rem auto;
    position: relative;
    animation: slideDown 0.4s ease;
    border: 1px solid var(--border);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: var(--text);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close-modal:hover {
    color: var(--text);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text);
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}

.empty-state i {
    font-size: 3rem;
    color: var(--border);
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Responsive Design */
@media (max-width: 768px) {
    .shift-dashboard {
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 2rem;
    }
    
    .assignment-grid {
        gap: 1.5rem;
    }
    
    .shift-card {
        padding: 1.5rem;
    }
    
    .days-selection {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .shift-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .shift-details {
        text-align: left;
        width: 100%;
    }
}

@media (max-width: 480px) {
    .shift-dashboard {
        padding: 1rem;
    }
    
    .days-selection {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="shift-dashboard">
    <div class="dashboard-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Assign Shifts to Staff</h1>
                <p>Schedule shifts for your team members with ease</p>
            </div>
        </div>
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

    <div class="assignment-grid">
        <!-- Single Shift Assignment -->
        <div class="shift-card single-shift">
            <div class="card-header">
                <i class="fas fa-user-plus"></i>
                <h2>Assign Single Shift</h2>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="user_id">
                        <i class="fas fa-user"></i> Staff Member
                    </label>
                    <select class="form-control" id="user_id" name="user_id" required>
                        <option value="">Select staff member...</option>
                        <?php foreach ($staff as $member): ?>
                        <option value="<?php echo $member['user_id']; ?>">
                            <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                            <?php if ($member['full_name']): ?> (<?php echo htmlspecialchars($member['username']); ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="shift_id">
                        <i class="fas fa-clock"></i> Shift Template
                    </label>
                    <select class="form-control" id="shift_id" name="shift_id" required>
                        <option value="">Select shift...</option>
                        <?php foreach ($shifts as $shift): 
                            $duration = (new DateTime($shift['start_time']))->diff(new DateTime($shift['end_time']));
                            $duration_text = $duration->h . 'h';
                            if ($duration->i > 0) $duration_text .= ' ' . $duration->i . 'm';
                        ?>
                        <option value="<?php echo $shift['shift_id']; ?>" 
                                data-color="<?php echo $shift['color']; ?>"
                                data-duration="<?php echo $duration_text; ?>">
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
                        <i class="fas fa-sticky-note"></i> Notes (Optional)
                    </label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                              placeholder="Any special instructions or notes for this shift..."></textarea>
                </div>
                
                <button type="submit" name="assign_shift" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i> Assign Shift
                </button>
            </form>
        </div>

        <!-- Bulk Assignment -->
        <div class="shift-card bulk-shift">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <h2>Bulk Assign Shifts</h2>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-friends"></i> Select Staff Members
                    </label>
                    <div class="staff-selection">
                        <?php foreach ($staff as $member): ?>
                        <div class="staff-item">
                            <div class="staff-checkbox" onclick="toggleStaff(this, <?php echo $member['user_id']; ?>)"></div>
                            <input type="checkbox" name="user_ids[]" 
                                   value="<?php echo $member['user_id']; ?>" 
                                   id="user_<?php echo $member['user_id']; ?>" 
                                   style="display: none;">
                            <div class="staff-info">
                                <h4><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></h4>
                                <span><?php echo htmlspecialchars($member['username']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-select-all" onclick="selectAllStaff()">
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
                
                <div class="time-inputs">
                    <div class="form-group">
                        <label for="start_date">
                            <i class="fas fa-calendar-alt"></i> Start Date
                        </label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">
                            <i class="fas fa-calendar-alt"></i> End Date
                        </label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
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
                        <div class="day-item">
                            <input type="checkbox" class="day-checkbox" name="days[]" 
                                   value="<?php echo $day['value']; ?>" 
                                   id="day_<?php echo $day['value']; ?>">
                            <label for="day_<?php echo $day['value']; ?>" class="day-label">
                                <span class="day-letter"><?php echo $day['letter']; ?></span>
                                <span class="day-name"><?php echo $day['name']; ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-select-all" onclick="selectWeekdays()">
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
    <div class="upcoming-shifts-container">
        <div class="shift-card upcoming-shifts">
            <div class="card-header">
                <i class="fas fa-calendar-check"></i>
                <h2>Upcoming Shifts</h2>
            </div>
            
            <?php if (empty($assigned_shifts)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Upcoming Shifts</h3>
                    <p>Assign shifts to see them here</p>
                </div>
            <?php else: ?>
                <div class="shifts-list">
                    <?php foreach ($assigned_shifts as $assignment): 
                        $status_class = [
                            'scheduled' => 'status-scheduled',
                            'completed' => 'status-completed',
                            'cancelled' => 'status-cancelled',
                            'absent' => 'status-absent'
                        ];
                    ?>
                    <div class="shift-item">
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
                            
                            <div class="shift-actions">
                                <button type="button" class="btn-icon btn-edit" 
                                        onclick="updateStatus(<?php echo $assignment['user_shift_id']; ?>, '<?php echo $assignment['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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
                    <label for="status">
                        <i class="fas fa-info-circle"></i> Status
                    </label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_notes">
                        <i class="fas fa-sticky-note"></i> Notes
                    </label>
                    <textarea class="form-control" id="update_notes" name="update_notes" rows="3" 
                              placeholder="Add notes about status change..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-outline close-modal">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize date inputs
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    const nextWeekStr = nextWeek.toISOString().split('T')[0];
    
    // Set default dates
    document.getElementById('start_date').value = today;
    document.getElementById('end_date').value = nextWeekStr;
    document.getElementById('assigned_date').value = today;
    
    // Select weekdays by default
    [1,2,3,4,5].forEach(day => {
        const checkbox = document.getElementById('day_' + day);
        if (checkbox) checkbox.checked = true;
    });
});

// Staff selection functions
function toggleStaff(element, userId) {
    const checkbox = document.getElementById('user_' + userId);
    checkbox.checked = !checkbox.checked;
    element.classList.toggle('checked', checkbox.checked);
}

function selectAllStaff() {
    const staffCheckboxes = document.querySelectorAll('.staff-checkbox');
    const allChecked = Array.from(staffCheckboxes).every(cb => cb.classList.contains('checked'));
    
    staffCheckboxes.forEach((cb, index) => {
        const userId = cb.parentElement.querySelector('input[type="checkbox"]').value;
        const checkbox = document.getElementById('user_' + userId);
        
        checkbox.checked = !allChecked;
        cb.classList.toggle('checked', checkbox.checked);
    });
}

function selectWeekdays() {
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

// Update shift status modal
function updateStatus(userShiftId, currentStatus) {
    document.getElementById('status_user_shift_id').value = userShiftId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'block';
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

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const bulkForm = e.target.closest('form[action*="bulk"]');
    if (bulkForm) {
        const selectedStaff = bulkForm.querySelectorAll('input[name="user_ids[]"]:checked');
        const selectedDays = bulkForm.querySelectorAll('input[name="days[]"]:checked');
        
        if (selectedStaff.length === 0) {
            e.preventDefault();
            alert('Please select at least one staff member');
            return false;
        }
        
        if (selectedDays.length === 0) {
            e.preventDefault();
            alert('Please select at least one day of the week');
            return false;
        }
    }
});

// Real-time shift duration display
const shiftSelect = document.getElementById('shift_id');
const shiftInfo = document.createElement('div');
shiftInfo.className = 'shift-info-display';
shiftInfo.style.marginTop = '0.5rem';
shiftInfo.style.fontSize = '0.9rem';
shiftInfo.style.color = '#64748b';

shiftSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        const color = selectedOption.getAttribute('data-color');
        const duration = selectedOption.getAttribute('data-duration');
        
        shiftInfo.innerHTML = `
            <span style="display: inline-block; width: 12px; height: 12px; background-color: ${color}; 
                  border-radius: 3px; margin-right: 0.5rem;"></span>
            Duration: ${duration}
        `;
        
        if (!this.parentNode.contains(shiftInfo)) {
            this.parentNode.appendChild(shiftInfo);
        }
    } else if (this.parentNode.contains(shiftInfo)) {
        this.parentNode.removeChild(shiftInfo);
    }
});
</script>

<?php 
ob_flush(); // Flush output buffer
require_once ROOT_PATH . 'admin/includes/footer.php'; 
?>