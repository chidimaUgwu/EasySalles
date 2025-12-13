<?php
// admin/users/edit.php
$page_title = "Edit Staff";
require_once '../includes/header.php';

$user_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

if (!$user_id) {
    header('Location: index.php');
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] == 1) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $shift_start = $_POST['shift_start'] ?? '';
    $shift_end = $_POST['shift_end'] ?? '';
    $shift_days = $_POST['shift_days'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $notes = $_POST['notes'] ?? '';
    $change_password = isset($_POST['change_password']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    
    // Check if username exists (excluding current user)
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM EASYSALLES_USERS WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists";
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM EASYSALLES_USERS WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    if ($change_password) {
        if (empty($password)) $errors[] = "Password is required when changing password";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    }
    
    if (empty($errors)) {
        try {
            if ($change_password) {
                // Update with password change
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    username = ?, password_hash = ?, full_name = ?, email = ?, phone = ?,
                    shift_start = ?, shift_end = ?, shift_days = ?, salary = ?, status = ?, notes = ?
                    WHERE user_id = ?");
                
                $result = $stmt->execute([
                    $username, $password_hash, $full_name, $email, $phone,
                    $shift_start, $shift_end, $shift_days, $salary, $status, $notes, $user_id
                ]);
            } else {
                // Update without password change
                $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    username = ?, full_name = ?, email = ?, phone = ?,
                    shift_start = ?, shift_end = ?, shift_days = ?, salary = ?, status = ?, notes = ?
                    WHERE user_id = ?");
                
                $result = $stmt->execute([
                    $username, $full_name, $email, $phone,
                    $shift_start, $shift_end, $shift_days, $salary, $status, $notes, $user_id
                ]);
            }
            
            if ($result) {
                $success = "Staff member updated successfully!";
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = "Failed to update staff member. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Edit Staff Member</h2>
        <p>Update staff information and shift schedule</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Staff List
        </a>
        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-eye"></i> View Profile
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Staff Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if ($error): ?>
                    <div class="message error-message" style="background: var(--error-light); color: var(--error); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success-message" style="background: var(--success-light); color: var(--success); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" 
                                       name="full_name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" 
                                       name="username" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-top: 1rem;">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Shift Start Time</label>
                                <input type="time" 
                                       name="shift_start" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['shift_start'] ?? '09:00'); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Shift End Time</label>
                                <input type="time" 
                                       name="shift_end" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['shift_end'] ?? '17:00'); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Shift Days</label>
                                <select name="shift_days" class="form-control">
                                    <option value="Mon-Fri" <?php echo ($user['shift_days'] ?? 'Mon-Fri') == 'Mon-Fri' ? 'selected' : ''; ?>>Monday - Friday</option>
                                    <option value="Weekends" <?php echo ($user['shift_days'] ?? '') == 'Weekends' ? 'selected' : ''; ?>>Weekends Only</option>
                                    <option value="Daily" <?php echo ($user['shift_days'] ?? '') == 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="Custom" <?php echo ($user['shift_days'] ?? '') == 'Custom' ? 'selected' : ''; ?>>Custom Schedule</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-top: 1rem;">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Monthly Salary ($)</label>
                                <input type="number" 
                                       name="salary" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['salary'] ?? ''); ?>" 
                                       step="0.01">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" 
                                  class="form-control" 
                                  rows="3"><?php echo htmlspecialchars($user['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Password Change Section -->
                    <div class="card" style="margin-top: 2rem; background: var(--bg);">
                        <div class="card-header">
                            <h4 class="card-title" style="font-size: 1rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" 
                                           id="changePasswordCheck" 
                                           name="change_password"
                                           onclick="togglePasswordFields()">
                                    <span>Change Password</span>
                                </label>
                            </h4>
                        </div>
                        <div id="passwordFields" style="padding: 1.5rem; display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" 
                                               name="password" 
                                               id="password" 
                                               class="form-control" 
                                               placeholder="Leave blank to keep current">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" 
                                               name="confirm_password" 
                                               id="confirm_password" 
                                               class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <a href="delete.php?id=<?php echo $user_id; ?>" 
                           class="btn btn-outline" 
                           style="color: var(--error); margin-left: auto;"
                           onclick="return confirm('Are you sure you want to delete this staff member? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Staff
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Staff Profile</h3>
            </div>
            <div style="padding: 1.5rem; text-align: center;">
                <div class="user-avatar" style="width: 100px; height: 100px; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); font-size: 2.5rem;">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h4><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                
                <div style="margin-top: 2rem; text-align: left;">
                    <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                        <h5 style="color: var(--primary); margin-bottom: 0.5rem;">
                            <i class="fas fa-id-card"></i> Staff Details
                        </h5>
                        <p style="margin: 0.3rem 0;">
                            <strong>ID:</strong> #<?php echo $user['user_id']; ?>
                        </p>
                        <p style="margin: 0.3rem 0;">
                            <strong>Role:</strong> Sales Staff
                        </p>
                        <p style="margin: 0.3rem 0;">
                            <strong>Joined:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div style="background: var(--accent-light); padding: 1rem; border-radius: 10px;">
                        <h5 style="color: var(--accent); margin-bottom: 0.5rem;">
                            <i class="fas fa-clock"></i> Current Schedule
                        </h5>
                        <?php if ($user['shift_start'] && $user['shift_end']): ?>
                            <p style="margin: 0.3rem 0;">
                                <strong>Shift:</strong> <?php echo date('h:i A', strtotime($user['shift_start'])); ?> - <?php echo date('h:i A', strtotime($user['shift_end'])); ?>
                            </p>
                            <p style="margin: 0.3rem 0;">
                                <strong>Days:</strong> <?php echo htmlspecialchars($user['shift_days'] ?: 'Not set'); ?>
                            </p>
                        <?php else: ?>
                            <p style="margin: 0.3rem 0; color: var(--warning);">
                                <i class="fas fa-exclamation-triangle"></i> No shift assigned
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePasswordFields() {
        const check = document.getElementById('changePasswordCheck');
        const fields = document.getElementById('passwordFields');
        const passwordInput = document.getElementById('password');
        
        if (check.checked) {
            fields.style.display = 'block';
            passwordInput.required = true;
        } else {
            fields.style.display = 'none';
            passwordInput.required = false;
        }
    }
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const changePassword = document.getElementById('changePasswordCheck').checked;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (changePassword) {
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
