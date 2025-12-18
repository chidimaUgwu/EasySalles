<?php
// admin/shifts/manage.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$page_title = "Manage Shift Templates";
require_once ROOT_PATH . 'admin/includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_shift'])) {
        $shift_name = $_POST['shift_name'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $color = $_POST['color'];
        
        $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SHIFTS (shift_name, start_time, end_time, color) VALUES (?, ?, ?, ?)");
        $stmt->execute([$shift_name, $start_time, $end_time, $color]);
        
        $_SESSION['success'] = "Shift template added successfully!";
    }
    
    if (isset($_POST['update_shift'])) {
        $shift_id = $_POST['shift_id'];
        $shift_name = $_POST['shift_name'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $color = $_POST['color'];
        
        $stmt = $pdo->prepare("UPDATE EASYSALLES_SHIFTS SET shift_name = ?, start_time = ?, end_time = ?, color = ? WHERE shift_id = ?");
        $stmt->execute([$shift_name, $start_time, $end_time, $color, $shift_id]);
        
        $_SESSION['success'] = "Shift template updated successfully!";
    }
    
    if (isset($_POST['delete_shift'])) {
        $shift_id = $_POST['shift_id'];
        
        // Check if shift is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_USER_SHIFTS WHERE shift_id = ?");
        $stmt->execute([$shift_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete shift that is assigned to staff members!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM EASYSALLES_SHIFTS WHERE shift_id = ?");
            $stmt->execute([$shift_id]);
            $_SESSION['success'] = "Shift template deleted successfully!";
        }
    }
    
    header("Location: manage.php");
    exit();
}

// Get all shift templates
$stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
$shifts = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2><i class="fas fa-clock"></i> Manage Shift Templates</h2>
        <p class="text-muted">Create and manage shift templates for scheduling</p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Add New Shift Card -->
    <div class="col-lg-4 col-md-6">
        <div class="card card-primary">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Shift</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="addShiftForm">
                    <div class="form-group">
                        <label for="shift_name" class="form-label">Shift Name</label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" required 
                               placeholder="e.g., Morning Shift">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="color" class="form-label">Color</label>
                        <div class="input-group">
                            <input type="color" class="form-control-color" id="color" name="color" value="#7C3AED" required 
                                   style="width: 60px; height: 40px;">
                            <input type="text" class="form-control" id="color_hex" value="#7C3AED" readonly 
                                   style="max-width: 120px;">
                        </div>
                        <small class="text-muted">Used for calendar display</small>
                    </div>
                    
                    <button type="submit" name="add_shift" class="btn btn-primary btn-block mt-3">
                        <i class="fas fa-plus"></i> Add Shift Template
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Shift Templates List -->
    <div class="col-lg-8 col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list-alt"></i> Shift Templates</h3>
                <div class="card-tools">
                    <span class="badge badge-info"><?php echo count($shifts); ?> shifts</span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($shifts)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <h5>No Shift Templates Found</h5>
                        <p class="text-muted">Add your first shift template to get started</p>
                    </div>
                <?php else: ?>
                    <div class="row p-3">
                        <?php foreach ($shifts as $shift): ?>
                            <?php
                            // Calculate duration
                            $start = new DateTime($shift['start_time']);
                            $end = new DateTime($shift['end_time']);
                            $diff = $start->diff($end);
                            $duration = $diff->h . 'h' . ($diff->i > 0 ? ' ' . $diff->i . 'm' : '');
                            ?>
                            
                            <div class="col-12 mb-3">
                                <div class="shift-card" style="border-left: 5px solid <?php echo $shift['color']; ?>;">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-3">
                                                    <div class="shift-info">
                                                        <h5 class="mb-1">
                                                            <span class="badge" style="background-color: <?php echo $shift['color']; ?>; color: white; font-size: 14px;">
                                                                <?php echo htmlspecialchars($shift['shift_name']); ?>
                                                            </span>
                                                        </h5>
                                                        <small class="text-muted">ID: #<?php echo $shift['shift_id']; ?></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="shift-time">
                                                        <div class="time-slot">
                                                            <i class="fas fa-play-circle text-success"></i>
                                                            <strong><?php echo date('h:i A', strtotime($shift['start_time'])); ?></strong>
                                                        </div>
                                                        <div class="time-slot mt-1">
                                                            <i class="fas fa-stop-circle text-danger"></i>
                                                            <strong><?php echo date('h:i A', strtotime($shift['end_time'])); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <div class="shift-duration">
                                                        <div class="duration-badge">
                                                            <i class="fas fa-hourglass-half"></i>
                                                            <span><?php echo $duration; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <div class="shift-color">
                                                        <div class="color-preview" style="background-color: <?php echo $shift['color']; ?>;"></div>
                                                        <small class="text-muted"><?php echo $shift['color']; ?></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-1">
                                                    <div class="shift-actions">
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($shift)); ?>)"
                                                                    data-toggle="tooltip" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                                <button type="submit" name="delete_shift" 
                                                                        class="btn btn-sm btn-outline-danger"
                                                                        onclick="return confirm('Are you sure you want to delete this shift template?')"
                                                                        data-toggle="tooltip" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editModalLabel">
                    <i class="fas fa-edit"></i> Edit Shift Template
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="shift_id" id="edit_shift_id">
                    <input type="hidden" name="update_shift" value="1">
                    
                    <div class="form-group">
                        <label for="edit_shift_name" class="form-label">Shift Name</label>
                        <input type="text" class="form-control" id="edit_shift_name" name="shift_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_color" class="form-label">Color</label>
                        <div class="input-group">
                            <input type="color" class="form-control-color" id="edit_color" name="color" required 
                                   style="width: 60px; height: 40px;">
                            <input type="text" class="form-control" id="edit_color_hex" readonly 
                                   style="max-width: 120px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update color hex value when color picker changes
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('color_hex').value = this.value;
});

// Edit modal functions
function openEditModal(shift) {
    document.getElementById('edit_shift_id').value = shift.shift_id;
    document.getElementById('edit_shift_name').value = shift.shift_name;
    document.getElementById('edit_start_time').value = shift.start_time;
    document.getElementById('edit_end_time').value = shift.end_time;
    document.getElementById('edit_color').value = shift.color;
    document.getElementById('edit_color_hex').value = shift.color;
    
    // Show modal
    $('#editModal').modal('show');
}

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<style>
.shift-card {
    transition: transform 0.2s ease-in-out;
}

.shift-card:hover {
    transform: translateY(-2px);
}

.time-slot {
    display: flex;
    align-items: center;
    gap: 8px;
}

.duration-badge {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 8px 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.color-preview {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    margin-bottom: 5px;
}

.shift-actions .btn-group {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-control-color {
    border: none;
    background: none;
    cursor: pointer;
}

@media (max-width: 768px) {
    .shift-card .card-body .row > div {
        margin-bottom: 10px;
    }
    
    .shift-actions {
        text-align: center;
        margin-top: 10px;
    }
}
</style>

<?php require_once ROOT_PATH . 'admin/includes/footer.php'; ?>