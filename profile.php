<?php
// profile.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();

$page_title = 'Profile Settings';
include 'includes/header.php';
require 'config/db.php';

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM EASYSALLES_USERS WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    try {
        // Update user profile
        $update_sql = "UPDATE EASYSALLES_USERS 
                      SET full_name = ?, email = ?, phone = ?, address = ?
                      WHERE user_id = ?";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$full_name, $email, $phone, $address, $user_id]);
        
        // Update session variables
        $_SESSION['username'] = $user['username'];
        if ($full_name) {
            $_SESSION['full_name'] = $full_name;
        }
        
        $success = "Profile updated successfully!";
        
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password_hash'])) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = "UPDATE EASYSALLES_USERS 
                           SET password_hash = ?, last_password_change = NOW()
                           WHERE user_id = ?";
            $password_stmt = $pdo->prepare($password_sql);
            
            if ($password_stmt->execute([$new_hash, $user_id])) {
                $password_success = "Password changed successfully!";
                
                // Clear force password change flag if it was set
                if ($user['force_password_change'] == 1) {
                    $clear_sql = "UPDATE EASYSALLES_USERS SET force_password_change = 0 WHERE user_id = ?";
                    $pdo->prepare($clear_sql)->execute([$user_id]);
                    $_SESSION['force_password_change'] = 0;
                }
            } else {
                $password_error = "Failed to update password.";
            }
        } else {
            $password_error = "Current password is incorrect.";
        }
    }
}
?>

<style>
    .profile-container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .page-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 2rem;
    }
    
    @media (max-width: 768px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .profile-sidebar {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        text-align: center;
    }
    
    .profile-avatar {
        width: 150px;
        height: 150px;
        border-radius: 20px;
        object-fit: cover;
        margin: 0 auto 1.5rem;
        border: 5px solid white;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        font-weight: bold;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 15px;
        object-fit: cover;
    }
    
    .profile-info h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .profile-role {
        display: inline-block;
        padding: 0.25rem 1rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        color: var(--primary);
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }
    
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .profile-stat {
        text-align: center;
    }
    
    .profile-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }
    
    .profile-stat-label {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .profile-content {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .profile-section {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title i {
        color: var(--primary);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    @media (max-width: 576px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
    }
    
    .form-control {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .form-control:disabled {
        background: var(--bg);
        color: #64748b;
        cursor: not-allowed;
    }
    
    .submit-btn {
        padding: 0.875rem 2rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(124, 58, 237, 0.3);
    }
    
    .alert {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: #10B981;
        border-left-color: #10B981;
    }
    
    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border-left-color: #EF4444;
    }
    
    .info-text {
        color: #64748b;
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }
    
    .password-strength {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
    }
    
    .strength-weak { background: #EF4444; }
    .strength-medium { background: #F59E0B; }
    .strength-strong { background: #10B981; }
</style>

<div class="profile-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-user-cog"></i> Profile Settings
        </h1>
    </div>
    
    <div class="profile-grid">
        <!-- Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <?php 
                if ($user['avatar_url']): 
                    echo '<img src="' . htmlspecialchars($user['avatar_url']) . '" alt="Profile Avatar">';
                else:
                    $initials = substr($user['full_name'] ?? $user['username'], 0, 2);
                    echo strtoupper($initials);
                endif; 
                ?>
            </div>
            
            <div class="profile-info">
                <h3><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h3>
                <div class="profile-role">
                    <?php echo $user['role'] == 1 ? 'Administrator' : 'Sales Staff'; ?>
                </div>
                <p style="color: #64748b; font-size: 0.9rem;">
                    <i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?>
                </p>
                <?php if ($user['email']): ?>
                    <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.5rem;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-value">
                        <?php 
                        // Get today's sales count
                        $today_sql = "SELECT COUNT(*) FROM EASYSALLES_SALES 
                                     WHERE staff_id = ? AND DATE(sale_date) = ?";
                        $today_stmt = $pdo->prepare($today_sql);
                        $today_stmt->execute([$user_id, date('Y-m-d')]);
                        echo $today_stmt->fetchColumn();
                        ?>
                    </div>
                    <div class="profile-stat-label">Today's Sales</div>
                </div>
                
                <div class="profile-stat">
                    <div class="profile-stat-value">
                        <?php 
                        // Get total sales count
                        $total_sql = "SELECT COUNT(*) FROM EASYSALLES_SALES WHERE staff_id = ?";
                        $total_stmt = $pdo->prepare($total_sql);
                        $total_stmt->execute([$user_id]);
                        echo $total_stmt->fetchColumn();
                        ?>
                    </div>
                    <div class="profile-stat-label">Total Sales</div>
                </div>
            </div>
            
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                <p style="color: #64748b; font-size: 0.85rem;">
                    <i class="fas fa-calendar-alt"></i> 
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="profile-content">
            <!-- Personal Information -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    <span>Personal Information</span>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="info-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   placeholder="Enter your full name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   placeholder="Enter your email">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="Enter phone number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" 
                                  placeholder="Enter your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-lock"></i>
                    <span>Change Password</span>
                </div>
                
                <?php if (isset($password_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($password_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $password_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($user['force_password_change'] == 1): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> 
                        You must change your password before continuing.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" 
                               placeholder="Enter current password" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" 
                               placeholder="Enter new password" required 
                               oninput="checkPasswordStrength(this.value)">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                        <div class="info-text">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="submit-btn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Information (Read-only) -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Account Information</span>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">User ID</label>
                        <input type="text" class="form-control" 
                               value="ES-<?php echo str_pad($user['user_id'], 6, '0', STR_PAD_LEFT); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" 
                               value="<?php echo ucfirst($user['status']); ?>" disabled>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Last Login</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $user['last_login'] ? date('M j, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Password Change</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $user['last_password_change'] ? date('M j, Y', strtotime($user['last_password_change'])) : 'Never'; ?>" disabled>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 2): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Shift Hours</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo $user['shift_start'] && $user['shift_end'] ? date('g:i A', strtotime($user['shift_start'])) . ' - ' . date('g:i A', strtotime($user['shift_end'])) : 'Not set'; ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Shift Days</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['shift_days'] ?? 'Not set'); ?>" disabled>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Password strength checker
    function checkPasswordStrength(password) {
        const strengthBar = document.getElementById('passwordStrength');
        let strength = 0;
        
        if (password.length >= 8) strength += 25;
        if (/[A-Z]/.test(password)) strength += 25;
        if (/[0-9]/.test(password)) strength += 25;
        if (/[^A-Za-z0-9]/.test(password)) strength += 25;
        
        strengthBar.style.width = strength + '%';
        
        if (strength < 50) {
            strengthBar.className = 'password-strength-bar strength-weak';
        } else if (strength < 75) {
            strengthBar.className = 'password-strength-bar strength-medium';
        } else {
            strengthBar.className = 'password-strength-bar strength-strong';
        }
    }
    
    // Initialize password strength
    document.addEventListener('DOMContentLoaded', function() {
        const newPasswordInput = document.querySelector('input[name="new_password"]');
        if (newPasswordInput) {
            checkPasswordStrength(newPasswordInput.value);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>