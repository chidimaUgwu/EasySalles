<?php
// admin/shifts/manage.php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_admin();

// Handle form submissions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_shift'])) {
        $shift_name = $_POST['shift_name'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $color = $_POST['color'];
        
        $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SHIFTS (shift_name, start_time, end_time, color) VALUES (?, ?, ?, ?)");
        $stmt->execute([$shift_name, $start_time, $end_time, $color]);
        
        $_SESSION['success'] = "Shift template added successfully!";
        header("Location: manage.php");
        exit();
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
        header("Location: manage.php");
        exit();
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
        
        header("Location: manage.php");
        exit();
    }
}

// Now start output AFTER all header redirects
$page_title = "Manage Shift Templates";
require_once ROOT_PATH . 'admin/includes/header.php';

// Get all shift templates
$stmt = $pdo->query("SELECT * FROM EASYSALLES_SHIFTS ORDER BY start_time");
$shifts = $stmt->fetchAll();
?>

<style>
/* Shift Management Styles */
.shift-management {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    animation: fadeIn 0.6s ease-out;
}

.shift-header {
    margin-bottom: 2.5rem;
    text-align: center;
}

.shift-header h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.75rem;
}

.shift-header p {
    color: #64748b;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Full Row Container */
.full-row-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Shift Form Card */
.shift-form-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.shift-form-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
}

.shift-form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 20px 0 0 20px;
}

.form-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.form-header i {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.form-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
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

.color-picker-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.color-preview {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 2px solid var(--border);
    cursor: pointer;
}

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
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
}

/* Shift Templates Card */
.shift-templates-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
}

.templates-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.templates-header i {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    background: linear-gradient(135deg, #10B981, #059669);
}

.templates-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

/* Shift Grid */
.shift-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.shift-card {
    background: var(--bg);
    border-radius: 15px;
    padding: 1.5rem;
    border-left: 4px solid;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.03);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.shift-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.shift-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.shift-name {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 1.2rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shift-time {
    background: rgba(124, 58, 237, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    color: var(--primary);
    font-family: 'Poppins', sans-serif;
}

.shift-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-label {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.detail-value {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    color: var(--text);
}

.shift-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
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

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
}

.btn-delete:hover {
    background: #EF4444;
    color: white;
    transform: scale(1.05);
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

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
    border-left: 4px solid #EF4444;
    color: #7F1D1D;
}

/* Responsive Design */
@media (max-width: 768px) {
    .shift-management {
        padding: 1.5rem;
    }
    
    .shift-header h1 {
        font-size: 2rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .time-inputs {
        grid-template-columns: 1fr;
    }
    
    .shift-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem auto;
    }
}

@media (max-width: 480px) {
    .shift-management {
        padding: 1rem;
    }
    
    .shift-form-card,
    .shift-templates-card {
        padding: 1.5rem;
    }
    
    .shift-details {
        grid-template-columns: 1fr;
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

<div class="shift-management">
    <div class="shift-header">
        <h1>Manage Shift Templates</h1>
        <p>Create and manage shift templates for staff scheduling</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="full-row-container">
        <!-- Add New Shift Form -->
        <div class="shift-form-card">
            <div class="form-header">
                <i class="fas fa-plus-circle"></i>
                <h2>Add New Shift Template</h2>
            </div>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="shift_name">
                            <i class="fas fa-tag"></i> Shift Name
                        </label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" required 
                               placeholder="e.g., Morning Shift, Evening Shift, Night Shift">
                    </div>
                    
                    <div class="form-group">
                        <label for="color">
                            <i class="fas fa-palette"></i> Shift Color
                        </label>
                        <div class="color-picker-wrapper">
                            <input type="color" class="color-preview" id="color_preview" 
                                   onchange="document.getElementById('color').value = this.value">
                            <input type="text" class="form-control" id="color" name="color" 
                                   value="#7C3AED" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" required>
                        </div>
                        <small class="text-muted">Used for calendar visualization</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_time">
                            <i class="fas fa-clock"></i> Start Time
                        </label>
                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">
                            <i class="fas fa-clock"></i> End Time
                        </label>
                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                    </div>
                </div>
                
                <button type="submit" name="add_shift" class="btn-primary">
                    <i class="fas fa-plus"></i> Create Shift Template
                </button>
            </form>
        </div>

        <!-- Shift Templates Display -->
        <div class="shift-templates-card">
            <div class="templates-header">
                <i class="fas fa-calendar-alt"></i>
                <h2>Available Shift Templates</h2>
            </div>
            
            <?php if (empty($shifts)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Shift Templates Yet</h3>
                    <p>Create your first shift template to get started</p>
                </div>
            <?php else: ?>
                <div class="shift-grid">
                    <?php foreach ($shifts as $shift): 
                        $start = new DateTime($shift['start_time']);
                        $end = new DateTime($shift['end_time']);
                        $diff = $start->diff($end);
                        $duration = $diff->h . 'h';
                        if ($diff->i > 0) $duration .= ' ' . $diff->i . 'm';
                    ?>
                        <div class="shift-card" style="border-left-color: <?php echo $shift['color']; ?>;">
                            <div class="shift-header-row">
                                <span class="shift-name">
                                    <i class="fas fa-clock" style="color: <?php echo $shift['color']; ?>;"></i>
                                    <?php echo htmlspecialchars($shift['shift_name']); ?>
                                </span>
                                <span class="shift-time">
                                    <?php echo date('h:i A', strtotime($shift['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                                </span>
                            </div>
                            
                            <div class="shift-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo $duration; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Color</span>
                                    <span class="detail-value">
                                        <div style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo $shift['color']; ?>; border-radius: 4px; vertical-align: middle;"></div>
                                        <?php echo $shift['color']; ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Start</span>
                                    <span class="detail-value"><?php echo date('h:i A', strtotime($shift['start_time'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">End</span>
                                    <span class="detail-value"><?php echo date('h:i A', strtotime($shift['end_time'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="shift-actions">
                                <button type="button" class="btn-icon btn-edit" 
                                        onclick="editShift(<?php echo $shift['shift_id']; ?>, '<?php echo addslashes($shift['shift_name']); ?>', '<?php echo $shift['start_time']; ?>', '<?php echo $shift['end_time']; ?>', '<?php echo $shift['color']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this shift template?')" style="display: inline;">
                                    <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                    <button type="submit" name="delete_shift" class="btn-icon btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Edit Shift Template</h4>
            <button type="button" class="close-modal">&times;</button>
        </div>
        
        <form method="POST" id="editForm">
            <div class="modal-body">
                <input type="hidden" name="shift_id" id="edit_shift_id">
                <input type="hidden" name="update_shift" value="1">
                
                <div class="form-group">
                    <label for="edit_shift_name">
                        <i class="fas fa-tag"></i> Shift Name
                    </label>
                    <input type="text" class="form-control" id="edit_shift_name" name="shift_name" required>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_start_time">
                            <i class="fas fa-clock"></i> Start Time
                        </label>
                        <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_end_time">
                            <i class="fas fa-clock"></i> End Time
                        </label>
                        <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_color">
                        <i class="fas fa-palette"></i> Shift Color
                    </label>
                    <input type="color" class="form-control" id="edit_color" name="color" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-outline close-modal">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Shift
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize color picker preview
document.getElementById('color_preview').value = document.getElementById('color').value;

// Edit Shift Function
function editShift(shiftId, shiftName, startTime, endTime, color) {
    document.getElementById('edit_shift_id').value = shiftId;
    document.getElementById('edit_shift_name').value = shiftName;
    document.getElementById('edit_start_time').value = startTime;
    document.getElementById('edit_end_time').value = endTime;
    document.getElementById('edit_color').value = color;
    
    document.getElementById('editModal').style.display = 'block';
}

// Close Modal
document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('editModal');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});

// Real-time duration calculation
const startTimeInput = document.getElementById('start_time');
const endTimeInput = document.getElementById('end_time');

function calculateDuration() {
    if (startTimeInput.value && endTimeInput.value) {
        const start = new Date(`2000-01-01T${startTimeInput.value}`);
        const end = new Date(`2000-01-01T${endTimeInput.value}`);
        
        if (end < start) {
            end.setDate(end.getDate() + 1);
        }
        
        const diff = Math.abs(end - start);
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        
        // You could display this somewhere if you want
        console.log(`Duration: ${hours}h ${minutes}m`);
    }
}

startTimeInput.addEventListener('change', calculateDuration);
endTimeInput.addEventListener('change', calculateDuration);
</script>

<?php require_once ROOT_PATH . 'admin/includes/footer.php'; ?>