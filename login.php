<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 1 ? 'admin/index.php' : 'staff-dashboard.php'));
    exit();
}

$page_title = 'Login';
//include 'includes/header.php';

// Database connection
require_once 'config/db.php';

// Handle form submission
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar_url'] ?? 'assets/images/default-avatar.png';
                $_SESSION['first_login'] = false; // We'll check if password needs changing
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->execute([$user['user_id']]);
                
                // Check if staff has active shift (if not admin)
                if ($user['role'] != 1) {
                    $shiftStmt = $pdo->prepare("
                        SELECT COUNT(*) as has_shift 
                        FROM EASYSALLES_SHIFTS 
                        WHERE user_id = ? 
                        AND DATE(start_time) = CURDATE() 
                        AND TIME(NOW()) BETWEEN TIME(start_time) AND TIME(end_time)
                    ");
                    $shiftStmt->execute([$user['user_id']]);
                    $shift = $shiftStmt->fetch();
                    
                    if (!$shift['has_shift']) {
                        $error = 'No active shift scheduled. Please contact administrator.';
                        session_destroy();
                    }
                }
                
                if (empty($error)) {
                    header('Location: ' . ($user['role'] == 1 ? 'admin/index.php' : 'staff-dashboard.php'));
                    exit();
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>

<style>
    /* Custom styles for login page */
    .login-container {
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: linear-gradient(135deg, #F8FAFC 0%, rgba(124, 58, 237, 0.05) 100%);
        position: relative;
        overflow: hidden;
    }
    
    .login-container::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(124, 58, 237, 0.08) 0%, transparent 70%);
    }
    
    .login-container::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(236, 72, 153, 0.06) 0%, transparent 70%);
    }
    
    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 450px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(226, 232, 240, 0.5);
        position: relative;
        z-index: 2;
        animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .login-logo {
        text-align: center;
        margin-bottom: 2.5rem;
    }
    
    .logo-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #7C3AED, #EC4899);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 0 12px 30px rgba(124, 58, 237, 0.3);
    }
    
    .logo-icon span {
        color: white;
        font-weight: 800;
        font-size: 2rem;
        font-family: 'Poppins', sans-serif;
    }
    
    .login-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2.2rem;
        font-weight: 700;
        color: #1E293B;
        text-align: center;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #7C3AED, #1E293B);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .login-subtitle {
        text-align: center;
        color: #64748b;
        margin-bottom: 2.5rem;
        font-size: 1.1rem;
    }
    
    .form-group {
        margin-bottom: 1.8rem;
        position: relative;
    }
    
    .form-label {
        display: block;
        color: #1E293B;
        font-weight: 600;
        margin-bottom: 0.6rem;
        font-size: 0.95rem;
    }
    
    .form-input {
        width: 100%;
        padding: 1rem 1.2rem;
        border: 2px solid #E2E8F0;
        border-radius: 14px;
        font-size: 1rem;
        font-family: 'Inter', sans-serif;
        transition: all 0.3s ease;
        background: white;
        color: #1E293B;
    }
    
    .form-input:focus {
        outline: none;
        border-color: #7C3AED;
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
    }
    
    .form-input:hover {
        border-color: #CBD5E1;
    }
    
    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 2.8rem;
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 1.2rem;
        transition: color 0.3s;
    }
    
    .password-toggle:hover {
        color: #7C3AED;
    }
    
    .error-message {
        background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(239, 68, 68, 0.1));
        color: #DC2626;
        padding: 1rem 1.5rem;
        border-radius: 14px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #EC4899;
        font-weight: 500;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .btn-login {
        width: 100%;
        padding: 1.2rem;
        background: linear-gradient(135deg, #06B6D4, #0891b2);
        color: white;
        border: none;
        border-radius: 14px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 0.5rem;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 8px 25px rgba(6, 182, 212, 0.3);
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(6, 182, 212, 0.4);
    }
    
    .btn-login:active {
        transform: translateY(0);
    }
    
    .login-footer {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #E2E8F0;
    }
    
    .forgot-link {
        color: #7C3AED;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .forgot-link:hover {
        color: #6D28D9;
        gap: 0.7rem;
    }
    
    .demo-credentials {
        background: rgba(124, 58, 237, 0.05);
        border-radius: 14px;
        padding: 1rem;
        margin-top: 1.5rem;
        text-align: left;
    }
    
    .demo-title {
        color: #7C3AED;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .demo-item {
        color: #64748b;
        font-size: 0.9rem;
        margin: 0.3rem 0;
    }
    
    .demo-item strong {
        color: #1E293B;
    }
    
    /* Responsive design */
    @media (max-width: 768px) {
        .login-container {
            padding: 1rem;
        }
        
        .login-card {
            padding: 2rem;
        }
        
        .login-title {
            font-size: 1.8rem;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
        }
        
        .logo-icon span {
            font-size: 1.7rem;
        }
    }
    
    @media (max-width: 480px) {
        .login-card {
            padding: 1.5rem;
            border-radius: 20px;
        }
        
        .login-title {
            font-size: 1.6rem;
        }
        
        .form-input {
            padding: 0.9rem 1.1rem;
        }
        
        .btn-login {
            padding: 1rem;
        }
    }
    
    /* Password strength indicator */
    .password-strength {
        height: 4px;
        background: #E2E8F0;
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s;
        border-radius: 2px;
    }
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">
                <span>ES</span>
            </div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to your EasySalles account</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">
                    <i class="fas fa-user" style="margin-right: 0.5rem;"></i>Username
                </label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-input" 
                       placeholder="Enter your username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       required
                       autocomplete="username">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fas fa-lock" style="margin-right: 0.5rem;"></i>Password
                </label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Enter your password" 
                       required
                       autocomplete="current-password">
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt" style="margin-right: 0.8rem;"></i> Sign In
            </button>
        </form>
        
        <div class="login-footer">
            <a href="#" class="forgot-link" id="forgotPassword">
                <i class="fas fa-key"></i> Forgot Password?
            </a>
            
            <div class="demo-credentials">
                <div class="demo-title">
                    <i class="fas fa-info-circle"></i> Demo Credentials
                </div>
                <div class="demo-item">
                    <strong>Admin:</strong> admin / password123
                </div>
                <div class="demo-item">
                    <strong>Staff:</strong> staff1 / password123
                </div>
                <div class="demo-item" style="font-size: 0.85rem; color: #94a3b8;">
                    <i class="fas fa-exclamation-triangle"></i> Change passwords in production
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div id="forgotModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2.5rem; border-radius: 20px; max-width: 400px; width: 90%; position: relative;">
        <h3 style="margin-top: 0; color: #1E293B; font-family: 'Poppins', sans-serif;">Reset Password</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Enter your email to receive password reset instructions.</p>
        <input type="email" placeholder="Email address" style="width: 100%; padding: 1rem; border: 2px solid #E2E8F0; border-radius: 12px; margin-bottom: 1.5rem;" id="resetEmail">
        <div style="display: flex; gap: 1rem;">
            <button id="sendReset" style="flex: 1; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 600; cursor: pointer;">Send Reset Link</button>
            <button id="closeModal" style="background: #F1F5F9; color: #64748b; border: none; padding: 1rem; border-radius: 12px; font-weight: 600; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('passwordStrengthBar');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
    
    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length > 6) strength += 25;
        if (password.length > 10) strength += 25;
        if (/[A-Z]/.test(password)) strength += 25;
        if (/[0-9]/.test(password)) strength += 25;
        
        strengthBar.style.width = strength + '%';
        
        if (strength < 50) {
            strengthBar.style.backgroundColor = '#EF4444';
        } else if (strength < 75) {
            strengthBar.style.backgroundColor = '#F59E0B';
        } else {
            strengthBar.style.backgroundColor = '#10B981';
        }
    });
    
    // Forgot password modal
    const forgotLink = document.getElementById('forgotPassword');
    const forgotModal = document.getElementById('forgotModal');
    const closeModal = document.getElementById('closeModal');
    const sendReset = document.getElementById('sendReset');
    const resetEmail = document.getElementById('resetEmail');
    
    forgotLink.addEventListener('click', function(e) {
        e.preventDefault();
        forgotModal.style.display = 'flex';
    });
    
    closeModal.addEventListener('click', function() {
        forgotModal.style.display = 'none';
    });
    
    sendReset.addEventListener('click', function() {
        const email = resetEmail.value;
        if (email && email.includes('@')) {
            alert('Password reset link sent to ' + email + ' (This is a demo)');
            forgotModal.style.display = 'none';
            resetEmail.value = '';
        } else {
            alert('Please enter a valid email address');
        }
    });
    
    // Close modal when clicking outside
    forgotModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    
    // Form validation
    const loginForm = document.getElementById('loginForm');
    loginForm.addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        if (!username) {
            e.preventDefault();
            alert('Please enter your username');
            return false;
        }
        
        if (!password) {
            e.preventDefault();
            alert('Please enter your password');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
        submitBtn.disabled = true;
        
        // Re-enable after 3 seconds (in case of error)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });
    
    // Add focus effect to form inputs
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
    
    // Auto-focus username field on page load
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('username').focus();
    });
</script>

<?php // include 'includes/footer.php'; ?>
