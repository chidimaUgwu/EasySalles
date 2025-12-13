<?php
// admin/users/add.php
$page_title = "Add New Staff";
require_once '../includes/header.php';

$error = '';
$success = '';

// Generate employee ID
$employee_id = generateEmployeeId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '2';
    $shift_start = $_POST['shift_start'] ?? '';
    $shift_end = $_POST['shift_end'] ?? '';
    $shift_days = $_POST['shift_days'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $notes = $_POST['notes'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    
    // Check if username exists
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM EASYSALLES_USERS WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists";
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM EASYSALLES_USERS WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_USERS 
                (username, password_hash, full_name, email, phone, role, 
                 shift_start, shift_end, shift_days, salary, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $username, $password_hash, $full_name, $email, $phone, $role,
                $shift_start, $shift_end, $shift_days, $salary, $status, $notes
            ]);
            
            if ($result) {
                $success = "Staff member added successfully!";
                // Clear form
                $_POST = [];
                $employee_id = generateEmployeeId(); // Generate new ID for next entry
                
                // Show success message with staff details
                $new_user_id = $pdo->lastInsertId();
            } else {
                $error = "Failed to add staff member. Please try again.";
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
        <h2>Add New Staff Member</h2>
        <p>Create a new sales staff account with shift schedule and permissions</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Staff List
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Staff Information</h3>
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
                        <?php if (isset($new_user_id)): ?>
                            <p style="margin-top: 0.5rem; margin-bottom: 0;">
                                Staff ID: <strong>#<?php echo $new_user_id; ?></strong> | 
                                Username: <strong><?php echo htmlspecialchars($_POST['username'] ?? ''); ?></strong>
                            </p>
                        <?php endif; ?>
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
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                       required 
                                       placeholder="John Doe">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" 
                                       name="username" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       required 
                                       placeholder="johndoe">
                                <small class="text-muted">Used for login</small>
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
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required 
                                       placeholder="john@example.com">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                       required 
                                       placeholder="+1234567890">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       class="form-control" 
                                       required 
                                       placeholder="Minimum 8 characters">
                                <div class="password-strength" style="margin-top: 0.5rem;">
                                    <div class="strength-bar" style="height: 4px; background: var(--border); border-radius: 2px;">
                                        <div id="strengthFill" style="height: 100%; width: 0%; border-radius: 2px;"></div>
                                    </div>
                                    <small id="strengthText" style="font-size: 0.8rem; color: var(--text-light);">Password strength</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password" 
                                       class="form-control" 
                                       required 
                                       placeholder="Re-enter password">
                                <small id="passwordMatch" style="font-size: 0.8rem;"></small>
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
                                       value="<?php echo htmlspecialchars($_POST['shift_start'] ?? '09:00'); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Shift End Time</label>
                                <input type="time" 
                                       name="shift_end" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['shift_end'] ?? '17:00'); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Shift Days</label>
                                <select name="shift_days" class="form-control">
                                    <option value="Mon-Fri" <?php echo ($_POST['shift_days'] ?? 'Mon-Fri') == 'Mon-Fri' ? 'selected' : ''; ?>>Monday - Friday</option>
                                    <option value="Weekends" <?php echo ($_POST['shift_days'] ?? '') == 'Weekends' ? 'selected' : ''; ?>>Weekends Only</option>
                                    <option value="Daily" <?php echo ($_POST['shift_days'] ?? '') == 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="Custom" <?php echo ($_POST['shift_days'] ?? '') == 'Custom' ? 'selected' : ''; ?>>Custom Schedule</option>
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
                                       value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>" 
                                       placeholder="0.00" 
                                       step="0.01">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo ($_POST['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($_POST['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($_POST['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="2" selected>Sales Staff</option>
                                    <option value="1" <?php echo ($_POST['role'] ?? '') == '1' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Any additional information about this staff member..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <i class="fas fa-user-plus"></i> Add Staff Member
                        </button>
                        <button type="reset" class="btn btn-outline" style="margin-left: 1rem;">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div class="user-avatar" style="width: 80px; height: 80px; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--primary), var(--secondary));">
                        <i class="fas fa-user-plus" style="font-size: 2rem;"></i>
                    </div>
                    <h4>New Staff Member</h4>
                    <p class="text-muted">Employee ID: <?php echo $employee_id; ?></p>
                </div>
                
                <div class="info-box" style="background: var(--primary-light); border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
                    <h5 style="color: var(--primary); margin-bottom: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Important Notes
                    </h5>
                    <ul style="color: var(--text-light); padding-left: 1rem; margin: 0; font-size: 0.9rem;">
                        <li>Password must be at least 8 characters</li>
                        <li>Username must be unique</li>
                        <li>Email will be used for password resets</li>
                        <li>Staff can only login during assigned shifts</li>
                    </ul>
                </div>
                
                <div class="info-box" style="background: var(--accent-light); border-radius: 10px; padding: 1rem;">
                    <h5 style="color: var(--accent); margin-bottom: 0.5rem;">
                        <i class="fas fa-shield-alt"></i> Security Tips
                    </h5>
                    <ul style="color: var(--text-light); padding-left: 1rem; margin: 0; font-size: 0.9rem;">
                        <li>Use a strong, unique password</li>
                        <li>Staff will be forced to change password on first login</li>
                        <li>Monitor shift schedules regularly</li>
                        <li>Deactivate accounts when staff leaves</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const matchText = document.getElementById('passwordMatch');
    
    function checkPasswordStrength(password) {
        let score = 0;
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        
        if (hasLength) score += 25;
        if (hasUpper) score += 25;
        if (hasLower) score += 25;
        if (hasNumber) score += 15;
        if (hasSpecial) score += 10;
        
        strengthFill.style.width = score + '%';
        
        if (score < 50) {
            strengthFill.style.backgroundColor = 'var(--error)';
            strengthText.textContent = 'Weak';
            strengthText.style.color = 'var(--error)';
        } else if (score < 75) {
            strengthFill.style.backgroundColor = 'var(--warning)';
            strengthText.textContent = 'Fair';
            strengthText.style.color = 'var(--warning)';
        } else if (score < 100) {
            strengthFill.style.backgroundColor = 'var(--success)';
            strengthText.textContent = 'Good';
            strengthText.style.color = 'var(--success)';
        } else {
            strengthFill.style.backgroundColor = 'var(--success)';
            strengthText.textContent = 'Excellent';
            strengthText.style.color = 'var(--success)';
        }
    }
    
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        if (confirm.length > 0) {
            if (password === confirm) {
                matchText.textContent = '✓ Passwords match';
                matchText.style.color = 'var(--success)';
                confirmInput.style.borderColor = 'var(--success)';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.style.color = 'var(--error)';
                confirmInput.style.borderColor = 'var(--error)';
            }
        } else {
            matchText.textContent = '';
            confirmInput.style.borderColor = '';
        }
    }
    
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });
    
    confirmInput.addEventListener('input', checkPasswordMatch);
    
    // Auto-focus first field
    document.querySelector('input[name="full_name"]').focus();
</script>

<?php require_once '../includes/footer.php'; ?>
