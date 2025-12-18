<?php
// admin/settings/profile.php
$page_title = "Profile Settings";
require_once '../includes/header.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$user_info = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();
} catch (PDOException $e) {
    // Handle error
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';
    
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                full_name = ?,
                email = ?,
                phone = ?,
                address = ?
                WHERE user_id = ?");
            
            $stmt->execute([$full_name, $email, $phone, $address, $user_id]);
            $success = true;
            $message = 'Profile updated successfully';
            
            // Refresh user info
            $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
        }
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($current_password, $user_info['password_hash'])) {
            $message = 'Current password is incorrect';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters';
        } else {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    password_hash = ?,
                    last_password_change = NOW(),
                    force_password_change = 0
                    WHERE user_id = ?");
                
                $stmt->execute([$new_hash, $user_id]);
                $success = true;
                $message = 'Password changed successfully';
                
            } catch (PDOException $e) {
                $message = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
    
    // Show message
    if ($message) {
        $alert_class = $success ? 'alert-success' : 'alert-error';
        echo "<div class='alert $alert_class' style='margin-bottom: 2rem;'>$message</div>";
    }
}

// Calculate account age
$account_age = '';
if ($user_info['created_at']) {
    $created = new DateTime($user_info['created_at']);
    $now = new DateTime();
    $interval = $created->diff($now);
    
    if ($interval->y > 0) {
        $account_age = $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        $account_age = $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } else {
        $account_age = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Profile Settings</h2>
        <p>Manage your account information and preferences</p>
    </div>
    <div class="page-actions">
        <button onclick="window.location.href='../dashboard/'" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </button>
    </div>
</div>

<div class="row">
    <!-- Left Column: Profile Info -->
    <div class="col-4">
        <!-- Profile Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="padding: 2rem; text-align: center;">
                <div class="user-avatar-large" style="margin: 0 auto 1rem;">
                    <?php 
                    $avatar_text = $user_info['full_name'] 
                        ? strtoupper(substr($user_info['full_name'], 0, 1))
                        : strtoupper(substr($user_info['username'], 0, 1));
                    echo $avatar_text;
                    ?>
                </div>
                <h3><?php echo htmlspecialchars($user_info['full_name'] ?: $user_info['username']); ?></h3>
                <p class="text-muted">@<?php echo htmlspecialchars($user_info['username']); ?></p>
                
                <div style="margin-top: 1.5rem;">
                    <span class="status-badge status-<?php echo $user_info['status'] ?? 'active'; ?>">
                        <?php echo ucfirst($user_info['status'] ?? 'active'); ?>
                    </span>
                    
                    <?php if ($user_info['role'] == 1): ?>
                    <span class="status-badge" style="background: var(--primary-light); color: var(--primary); margin-left: 0.5rem;">
                        Administrator
                    </span>
                    <?php else: ?>
                    <span class="status-badge" style="background: var(--accent-light); color: var(--accent); margin-left: 0.5rem;">
                        Staff Member
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Account Stats -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Account Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 0.5rem;">Account Activity</h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Member Since</span>
                        <span><?php echo date('M d, Y', strtotime($user_info['created_at'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span class="text-muted">Account Age</span>
                        <span><?php echo $account_age; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Last Login</span>
                        <span>
                            <?php if ($user_info['last_login']): ?>
                                <?php echo date('M d, Y H:i', strtotime($user_info['last_login'])); ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Security Status</h4>
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Password Strength</span>
                            <span class="status-badge status-active">Strong</span>
                        </div>
                        <div style="height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;">
                            <div style="width: 80%; height: 100%; background: var(--success);"></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span>Two-Factor Auth</span>
                        <span class="text-muted">Not enabled</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Settings Forms -->
    <div class="col-8">
        <!-- Profile Information Form -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Profile Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>"
                                       placeholder="Enter your full name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>"
                                       placeholder="your.email@example.com">
                            </div>
                        </div>
                        
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>"
                                       placeholder="+233 123 456 789">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" 
                                  placeholder="Enter your address"><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $user_info['role'] == 1 ? 'Administrator' : 'Staff Member'; ?>" 
                               disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" 
                               value="<?php echo ucfirst($user_info['status'] ?? 'active'); ?>" 
                               disabled>
                    </div>
                    
                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Form -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Change Password</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <div style="position: relative;">
                            <input type="password" name="current_password" class="form-control" 
                                   placeholder="Enter current password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div style="position: relative;">
                            <input type="password" name="new_password" id="newPassword" class="form-control" 
                                   placeholder="Enter new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Must be at least 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" style="margin-top: 0.5rem; display: none;">
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i> Passwords match
                            </small>
                        </div>
                        <div id="passwordMismatch" style="margin-top: 0.5rem; display: none;">
                            <small class="text-error">
                                <i class="fas fa-times-circle"></i> Passwords do not match
                            </small>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Preferences & Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Preferences</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form id="preferencesForm">
                    <div class="form-group">
                        <label class="form-label">Theme</label>
                        <div style="display: flex; gap: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="theme" value="light" checked>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-sun"></i> Light Mode
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="theme" value="dark">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-moon"></i> Dark Mode
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="theme" value="auto">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-adjust"></i> Auto
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Language</label>
                        <select name="language" class="form-control">
                            <option value="en">English</option>
                            <option value="fr">French</option>
                            <option value="es">Spanish</option>
                            <option value="pt">Portuguese</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Time Format</label>
                        <div style="display: flex; gap: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="time_format" value="12" checked>
                                12-hour (2:30 PM)
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="time_format" value="24">
                                24-hour (14:30)
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date Format</label>
                        <select name="date_format" class="form-control">
                            <option value="Y-m-d">2025-12-15</option>
                            <option value="d/m/Y">15/12/2025</option>
                            <option value="m/d/Y">12/15/2025</option>
                            <option value="d M Y">15 Dec 2025</option>
                            <option value="M d, Y">Dec 15, 2025</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notifications</label>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="email_notifications" checked>
                                Email notifications
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="push_notifications" checked>
                                Push notifications
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="sms_notifications">
                                SMS notifications
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; display: flex; justify-content: space-between;">
                        <button type="button" onclick="resetPreferences()" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset to Defaults
                        </button>
                        <button type="button" onclick="savePreferences()" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="card" style="margin-top: 1.5rem; border-color: var(--error);">
            <div class="card-header" style="border-color: var(--error);">
                <h3 class="card-title" style="color: var(--error);">
                    <i class="fas fa-exclamation-triangle"></i> Danger Zone
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 0.5rem;">Account Deactivation</h4>
                    <p class="text-muted" style="margin-bottom: 1rem;">
                        Deactivate your account temporarily. You can reactivate it later by contacting an administrator.
                    </p>
                    <button type="button" onclick="deactivateAccount()" class="btn btn-outline" style="color: var(--warning); border-color: var(--warning);">
                        <i class="fas fa-user-slash"></i> Deactivate Account
                    </button>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Export Data</h4>
                    <p class="text-muted" style="margin-bottom: 1rem;">
                        Download all your personal data stored in the system.
                    </p>
                    <button type="button" onclick="exportData()" class="btn btn-outline">
                        <i class="fas fa-download"></i> Export My Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar-large {
    width: 100px;
    height: 100px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.5rem;
}

.password-toggle:hover {
    color: var(--text);
}

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active { background: var(--success-light); color: var(--success); }
.status-inactive { background: var(--warning-light); color: var(--warning); }
.status-suspended { background: var(--error-light); color: var(--error); }

.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.alert-success {
    background: var(--success-light);
    color: var(--success);
    border: 1px solid var(--success);
}

.alert-error {
    background: var(--error-light);
    color: var(--error);
    border: 1px solid var(--error);
}
</style>

<script>
function togglePassword(button) {
    const input = button.previousElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Password match validation
const newPassword = document.getElementById('newPassword');
const confirmPassword = document.querySelector('input[name="confirm_password"]');
const matchIndicator = document.getElementById('passwordMatch');
const mismatchIndicator = document.getElementById('passwordMismatch');

function checkPasswordMatch() {
    if (!newPassword.value || !confirmPassword.value) {
        matchIndicator.style.display = 'none';
        mismatchIndicator.style.display = 'none';
        return;
    }
    
    if (newPassword.value === confirmPassword.value) {
        matchIndicator.style.display = 'block';
        mismatchIndicator.style.display = 'none';
    } else {
        matchIndicator.style.display = 'none';
        mismatchIndicator.style.display = 'block';
    }
}

newPassword.addEventListener('input', checkPasswordMatch);
confirmPassword.addEventListener('input', checkPasswordMatch);

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const currentPassword = document.querySelector('input[name="current_password"]').value;
    const newPass = newPassword.value;
    const confirmPass = confirmPassword.value;
    
    if (!currentPassword) {
        e.preventDefault();
        alert('Please enter your current password');
        return;
    }
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match');
        return;
    }
    
    if (newPass.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters');
        return;
    }
});

function savePreferences() {
    const form = document.getElementById('preferencesForm');
    const formData = new FormData(form);
    const preferences = {};
    
    for (let [key, value] of formData.entries()) {
        preferences[key] = value;
    }
    
    // In production, this would be an AJAX call
    localStorage.setItem('user_preferences', JSON.stringify(preferences));
    
    showToast('Preferences saved successfully', 'success');
}

function resetPreferences() {
    if (confirm('Reset all preferences to default values?')) {
        document.getElementById('preferencesForm').reset();
        localStorage.removeItem('user_preferences');
        showToast('Preferences reset to defaults', 'info');
    }
}

function deactivateAccount() {
    if (confirm('Are you sure you want to deactivate your account?\n\nYou will not be able to log in until an administrator reactivates your account.')) {
        showToast('Account deactivation requested...', 'info');
        
        // In production, this would be an AJAX call
        setTimeout(() => {
            showToast('Account deactivation request submitted. An administrator will process it shortly.', 'success');
        }, 2000);
    }
}

function exportData() {
    showToast('Preparing your data export...', 'info');
    
    // In production, this would be an AJAX call to generate a data export
    setTimeout(() => {
        const userData = {
            profile: <?php echo json_encode($user_info); ?>,
            exported: new Date().toISOString(),
            dataTypes: ['profile', 'activity', 'preferences']
        };
        
        const dataStr = JSON.stringify(userData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const link = document.createElement('a');
        link.href = dataUri;
        link.download = `user_data_export_<?php echo date('Y-m-d'); ?>.json`;
        link.click();
        
        showToast('Data export completed', 'success');
    }, 3000);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--primary)'};
        color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10001;
        animation: slideIn 0.3s ease;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
`;
document.head.appendChild(style);

// Load saved preferences
document.addEventListener('DOMContentLoaded', function() {
    const savedPrefs = localStorage.getItem('user_preferences');
    if (savedPrefs) {
        const prefs = JSON.parse(savedPrefs);
        const form = document.getElementById('preferencesForm');
        
        for (const [key, value] of Object.entries(prefs)) {
            const element = form.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = value === 'on' || value === true;
                } else if (element.type === 'radio') {
                    const radio = form.querySelector(`[name="${key}"][value="${value}"]`);
                    if (radio) radio.checked = true;
                } else {
                    element.value = value;
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
