<?php
// admin/shifts/assign.php
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
        $user_ids = $_POST['user_ids'];
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
    
    header("Location: assign.php");
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

<div class="page-header">
    <div class="page-title">
        <h2>Assign Shifts to Staff</h2>
        <p>Schedule shifts for your team members</p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Assign Single Shift</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">Staff Member</label>
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
                        <label for="shift_id">Shift</label>
                        <select class="form-control" id="shift_id" name="shift_id" required>
                            <option value="">Select shift...</option>
                            <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo $shift['shift_id']; ?>" data-start="<?php echo $shift['start_time']; ?>" data-end="<?php echo $shift['end_time']; ?>">
                                <?php echo htmlspecialchars($shift['shift_name']); ?> 
                                (<?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($shift['end_time'])); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assigned_date">Date</label>
                        <input type="date" class="form-control" id="assigned_date" name="assigned_date" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any special instructions..."></textarea>
                    </div>
                    
                    <button type="submit" name="assign_shift" class="btn btn-primary">Assign Shift</button>
                </form>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Bulk Assign Shifts</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Staff Members</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); padding: 10px; border-radius: 5px;">
                            <?php foreach ($staff as $member): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="user_ids[]" 
                                       value="<?php echo $member['user_id']; ?>" id="user_<?php echo $member['user_id']; ?>">
                                <label class="form-check-label" for="user_<?php echo $member['user_id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_shift_id">Shift</label>
                        <select class="form-control" id="bulk_shift_id" name="bulk_shift_id" required>
                            <option value="">Select shift...</option>
                            <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo $shift['shift_id']; ?>">
                                <?php echo htmlspecialchars($shift['shift_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Days of Week</label>
                        <div class="row" style="padding: 0 15px;">
                            <?php 
                            $days = [
                                ['value' => '1', 'label' => 'Mon'],
                                ['value' => '2', 'label' => 'Tue'],
                                ['value' => '3', 'label' => 'Wed'],
                                ['value' => '4', 'label' => 'Thu'],
                                ['value' => '5', 'label' => 'Fri'],
                                ['value' => '6', 'label' => 'Sat'],
                                ['value' => '0', 'label' => 'Sun']
                            ];
                            foreach ($days as $day): ?>
                            <div class="col-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="days[]" 
                                           value="<?php echo $day['value']; ?>" id="day_<?php echo $day['value']; ?>">
                                    <label class="form-check-label" for="day_<?php echo $day['value']; ?>">
                                        <?php echo $day['label']; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="bulk_assign" class="btn btn-primary">Bulk Assign</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Shifts</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_shifts as $assignment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assignment['full_name'] ?: $assignment['username']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $assignment['color']; ?>; color: white;">
                                    <?php echo htmlspecialchars($assignment['shift_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('h:i A', strtotime($assignment['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($assignment['end_time'])); ?>
                            </td>
                            <td>
                                <?php 
                                $status_class = [
                                    'scheduled' => 'badge-info',
                                    'completed' => 'badge-success',
                                    'cancelled' => 'badge-danger',
                                    'absent' => 'badge-warning'
                                ];
                                ?>
                                <span class="badge <?php echo $status_class[$assignment['status']] ?? 'badge-secondary'; ?>">
                                    <?php echo ucfirst($assignment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="updateStatus(<?php echo $assignment['user_shift_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Update Shift Status</h4>
            <button type="button" class="close" onclick="closeModal()">&times;</button>
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
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateStatus(userShiftId) {
    document.getElementById('status_user_shift_id').value = userShiftId;
    document.getElementById('statusModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
}

// Set default dates for bulk assignment
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    const nextWeekStr = nextWeek.toISOString().split('T')[0];
    
    document.getElementById('start_date').value = today;
    document.getElementById('end_date').value = nextWeekStr;
    document.getElementById('assigned_date').value = today;
    
    // Select all weekdays by default
    [1,2,3,4,5].forEach(day => {
        const checkbox = document.getElementById('day_' + day);
        if (checkbox) checkbox.checked = true;
    });
});
</script>

<?php require_once ROOT_PATH . 'admin/includes/footer.php'; ?>