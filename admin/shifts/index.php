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
        <h2>Manage Shift Templates</h2>
        <p>Create and manage shift templates for scheduling</p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Add New Shift</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="shift_name">Shift Name</label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" required placeholder="e.g., Morning Shift">
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="color" class="form-control" id="color" name="color" value="#7C3AED" required>
                        <small class="text-muted">Used for calendar display</small>
                    </div>
                    
                    <button type="submit" name="add_shift" class="btn btn-primary">Add Shift</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Shift Templates</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shift Name</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Color</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                        <tr>
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
                                echo $diff->h . ' hours';
                                if ($diff->i > 0) echo ' ' . $diff->i . ' minutes';
                                ?>
                            </td>
                            <td>
                                <div style="width: 20px; height: 20px; background-color: <?php echo $shift['color']; ?>; border-radius: 3px;"></div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="editShift(<?php echo $shift['shift_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                        <button type="submit" name="delete_shift" class="btn btn-sm btn-outline" onclick="return confirm('Delete this shift template?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Edit Shift Template</h4>
            <button type="button" class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <div class="modal-body">
                <input type="hidden" name="shift_id" id="edit_shift_id">
                <input type="hidden" name="update_shift" value="1">
                
                <div class="form-group">
                    <label for="edit_shift_name">Shift Name</label>
                    <input type="text" class="form-control" id="edit_shift_name" name="shift_name" required>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit_start_time">Start Time</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit_end_time">End Time</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_color">Color</label>
                    <input type="color" class="form-control" id="edit_color" name="color" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Shift</button>
            </div>
        </form>
    </div>
</div>

<script>
function editShift(shiftId) {
    // Fetch shift data via AJAX or populate from data attributes
    // For simplicity, we'll use a simple approach
    // In production, use AJAX to fetch shift data
    
    // Show modal
    document.getElementById('editModal').style.display = 'block';
    
    // You would fetch data here, but for now we'll use placeholder
    document.getElementById('edit_shift_id').value = shiftId;
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once ROOT_PATH . 'admin/includes/footer.php'; ?>