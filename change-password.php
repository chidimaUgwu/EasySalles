<?php
// change-password.php
require 'includes/auth.php';
require_login();

$page_title = 'Change Password';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'includes/db.php';
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        // Get user's current password hash
        $sql = "SELECT password_hash, force_password_change FROM EASYSALLES_USERS WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE EASYSALLES_USERS SET 
                          password_hash = ?, 
                          force_password_change = 0,
                          last_password_change = NOW()
                          WHERE user_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            
            if ($update_stmt->execute([$new_hash, $_SESSION['user_id']])) {
                $_SESSION['force_password_change'] = 0;
                $success = "Password changed successfully!";
                
                // Redirect after 2 seconds
                header("refresh:2;url=staff-dashboard.php");
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<div class="container" style="max-width: 500px; margin: 3rem auto; padding: 0 1rem;">
    <div class="card" style="background: var(--card-bg); border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--warning), #F59E0B); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="fas fa-key" style="font-size: 2rem; color: white;"></i>
            </div>
            <h1 style="font-family: 'Poppins', sans-serif; color: var(--text); margin-bottom: 0.5rem;">Change Password</h1>
            <p style="color: #64748b;">You must change your password before continuing.</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); color: #EF4444; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #EF4444;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); color: #10B981; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #10B981;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text);">
                    <i class="fas fa-lock"></i> Current Password
                </label>
                <input type="password" name="current_password" required 
                       style="width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: 12px; font-size: 1rem; transition: all 0.3s ease;"
                       onfocus="this.style.borderColor='var(--primary)';"
                       onblur="this.style.borderColor='var(--border)';">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text);">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <input type="password" name="new_password" required 
                       style="width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: 12px; font-size: 1rem; transition: all 0.3s ease;"
                       onfocus="this.style.borderColor='var(--primary)';"
                       onblur="this.style.borderColor='var(--border)';">
                <small style="display: block; margin-top: 0.5rem; color: #64748b;">
                    <i class="fas fa-info-circle"></i> Must be at least 8 characters long
                </small>
            </div>
            
            <div style="margin-bottom: 2rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text);">
                    <i class="fas fa-lock"></i> Confirm New Password
                </label>
                <input type="password" name="confirm_password" required 
                       style="width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: 12px; font-size: 1rem; transition: all 0.3s ease;"
                       onfocus="this.style.borderColor='var(--primary)';"
                       onblur="this.style.borderColor='var(--border)';">
            </div>
            
            <button type="submit" 
                    style="width: 100%; padding: 1rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"
                    onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 25px rgba(124, 58, 237, 0.3)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-sync-alt"></i> Change Password
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>