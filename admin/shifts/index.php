<?php
// admin/shifts/index.php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_admin();

$page_title = "Manage Shift Templates";
require_once ROOT_PATH . '/admin/includes/header.php';

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

<!-- Add custom CSS for the new design -->
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
    --transition: all 0.3s ease;
}

.page-header {
    background: var(--primary-gradient);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: var(--card-shadow);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L1000,0 L1000,100 L0,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
    background-size: cover;
}

.page-title h2 {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.page-title p {
    opacity: 0.9;
    font-size: 1rem;
}

/* Card Design */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    transition: var(--transition);
    background: white;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}

.card-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1.5rem;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.card-body {
    padding: 1.5rem;
}

/* Form Styling */
.form-group {
    margin-bottom: 1.5rem;
}

.form-control {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: var(--transition);
    font-size: 0.95rem;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

input[type="color"] {
    height: 45px;
    width: 100%;
    border-radius: 10px;
    cursor: pointer;
    border: 2px solid #e2e8f0;
}

/* Button Styling */
.btn {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: var(--transition);
    border: none;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-outline {
    border: 2px solid #667eea;
    color: #667eea;
    background: transparent;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

/* Table Redesign */
.table-container {
    overflow-x: auto;
    border-radius: 12px;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.table thead {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.table th {
    padding: 1.25rem 1rem;
    font-weight: 600;
    color: #4a5568;
    border: none;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    transform: scale(1.002);
}

.table td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

/* Full Row Enhancement */
.shift-row {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 1rem;
    border-radius: 10px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 0.5rem;
    transition: var(--transition);
}

.shift-row:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transform: translateX(5px);
}

.shift-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.shift-color {
    width: 4px;
    height: 40px;
    border-radius: 2px;
    margin-right: 1rem;
}

.shift-details {
    flex: 1;
}

.shift-name {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.shift-time {
    color: #64748b;
    font-size: 0.9rem;
}

.shift-duration {
    background: #f1f5f9;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Badge Styling */
.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.85rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    animation: modalSlideIn 0.3s ease;
    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.5rem;
    background: var(--primary-gradient);
    color: white;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    margin: 0;
    font-size: 1.5rem;
}

.close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    opacity: 0.8;
    transition: var(--transition);
}

.close:hover {
    opacity: 1;
    transform: scale(1.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .shift-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .shift-info {
        width: 100%;
        margin-bottom: 1rem;
    }
    
    .table th, .table td {
        padding: 0.75rem;
    }
}

/* Animation for alerts */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert {
    animation: slideIn 0.3s ease;
    border: none;
    border-radius: 10px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4090 100%);
}
</style>

<div class="page-header">
    <div class="page-title">
        <h2>🕐 Manage Shift Templates</h2>
        <p>Create and manage shift templates for scheduling</p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">✅ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">❌ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4 col-md-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">➕ Add New Shift Template</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="shift_name" class="form-label">Shift Name</label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" required 
                               placeholder="e.g., Morning Shift, Evening Shift, Night Shift">
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
                        <label for="color" class="form-label">Color Code</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="color" class="form-control" id="color" name="color" value="#7C3AED" required
                                   style="width: 60px; height: 45px;">
                            <small class="text-muted">Choose a color for calendar display</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_shift" class="btn btn-primary w-100 mt-2">
                        <i class="fas fa-plus-circle me-2"></i>Add Shift Template
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Available Shift Templates</h3>
                <div class="text-muted"><?php echo count($shifts); ?> shift templates found</div>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <?php if (empty($shifts)): ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3">No shift templates created yet</div>
                            <p class="text-muted">Start by adding your first shift template using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Shift Details</th>
                                    <th style="width: 25%;">Time Schedule</th>
                                    <th style="width: 15%;">Duration</th>
                                    <th style="width: 15%;">Color</th>
                                    <th style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shifts as $shift): 
                                    $start = new DateTime($shift['start_time']);
                                    $end = new DateTime($shift['end_time']);
                                    $diff = $start->diff($end);
                                    $duration = $diff->h . 'h' . ($diff->i > 0 ? ' ' . $diff->i . 'm' : '');
                                ?>
                                <tr>
                                    <td>
                                        <div class="shift-info">
                                            <div class="shift-color" style="background-color: <?php echo $shift['color']; ?>;"></div>
                                            <div class="shift-details">
                                                <div class="shift-name"><?php echo htmlspecialchars($shift['shift_name']); ?></div>
                                                <div class="shift-code text-muted">ID: SHF<?php echo str_pad($shift['shift_id'], 3, '0', STR_PAD_LEFT); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($shift['start_time'])); ?>
                                            </span>
                                            <span class="text-muted">→</span>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="shift-duration"><?php echo $duration; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 24px; height: 24px; background-color: <?php echo $shift['color']; ?>; border-radius: 6px; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>
                                            <small class="text-muted"><?php echo $shift['color']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editShift(<?php echo $shift['shift_id']; ?>,
                                                            '<?php echo htmlspecialchars($shift['shift_name']); ?>',
                                                            '<?php echo $shift['start_time']; ?>',
                                                            '<?php echo $shift['end_time']; ?>',
                                                            '<?php echo $shift['color']; ?>')">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift template?')">
                                                <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                <button type="submit" name="delete_shift" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
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
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>✏️ Edit Shift Template</h4>
            <button type="button" class="close" onclick="closeModal()">&times;</button>
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
                    <label for="edit_color" class="form-label">Color Code</label>
                    <input type="color" class="form-control" id="edit_color" name="color" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Update Shift
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editShift(shiftId, shiftName, startTime, endTime, color) {
    // Populate form fields
    document.getElementById('edit_shift_id').value = shiftId;
    document.getElementById('edit_shift_name').value = shiftName;
    document.getElementById('edit_start_time').value = startTime;
    document.getElementById('edit_end_time').value = endTime;
    document.getElementById('edit_color').value = color;
    
    // Show modal with animation
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('editModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Auto-calculate duration when time changes
document.addEventListener('DOMContentLoaded', function() {
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    function calculateDuration() {
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date('2000-01-01T' + startTimeInput.value);
            const end = new Date('2000-01-01T' + endTimeInput.value);
            const diff = Math.abs(end - start);
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            
            // Optional: Display duration somewhere
            console.log(`Shift duration: ${hours}h ${minutes}m`);
        }
    }
    
    startTimeInput.addEventListener('change', calculateDuration);
    endTimeInput.addEventListener('change', calculateDuration);
});

// Add visual feedback for form submission
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                submitBtn.disabled = true;
            }
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/admin/includes/footer.php'; ?>