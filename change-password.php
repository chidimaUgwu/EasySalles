<?php
// change-password.php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'config/db.php';

$error = '';
$success = '';
$current_password = '';
$new_password = '';
$confirm_password = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        try {
            // Get current user's password hash
            $stmt = $pdo->prepare("SELECT password_hash FROM EASYSALLES_USERS WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                // Current password is correct, update to new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET password_hash = ? WHERE user_id = ?");
                $updateStmt->execute([$new_password_hash, $_SESSION['user_id']]);
                
                $success = 'Password changed successfully!';
                
                // Clear form
                $current_password = $new_password = $confirm_password = '';
                
                // Mark first login as completed
                $_SESSION['first_login'] = false;
                
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles | Change Password</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #7C3AED;
            --secondary: #EC4899;
            --accent: #06B6D4;
            --bg: #F8FAFC;
            --text: #1E293B;
            --card-bg: #FFFFFF;
            --border: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --error: #EF4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #F8FAFC 0%, rgba(124, 58, 237, 0.05) 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .password-container {
            width: 100%;
            max-width: 500px;
        }
        
        .password-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(226, 232, 240, 0.5);
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
        
        .password-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .password-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.3);
        }
        
        .password-icon i {
            color: white;
            font-size: 2.5rem;
        }
        
        .password-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .password-subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            color: var(--text);
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
            color: var(--text);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
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
            color: var(--primary);
        }
        
        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: fadeIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-message {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(239, 68, 68, 0.1));
            color: #DC2626;
            border-left: 4px solid var(--secondary);
        }
        
        .success-message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        /* Password Requirements */
        .requirements {
            background: rgba(124, 58, 237, 0.05);
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 1.5rem;
        }
        
        .requirements-title {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .requirement {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .requirement.valid {
            color: var(--success);
        }
        
        .requirement.invalid {
            color: #64748b;
        }
        
        .requirement i {
            font-size: 0.9rem;
        }
        
        /* Button Styles */
        .btn {
            width: 100%;
            padding: 1.2rem;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), #6D28D9);
            color: white;
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
            margin-top: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-cancel {
            background: #F1F5F9;
            color: #64748b;
            margin-top: 1rem;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-cancel:hover {
            background: #E2E8F0;
            transform: translateY(-2px);
        }
        
        /* Password Strength Indicator */
        .password-strength {
            height: 6px;
            background: #E2E8F0;
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .strength-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.3rem;
            text-align: right;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .password-card {
                padding: 2rem;
            }
            
            .password-title {
                font-size: 1.8rem;
            }
            
            .password-icon {
                width: 70px;
                height: 70px;
            }
            
            .password-icon i {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .password-card {
                padding: 1.5rem;
                border-radius: 20px;
            }
            
            .password-title {
                font-size: 1.6rem;
            }
            
            .form-input {
                padding: 0.9rem 1.1rem;
            }
            
            .btn {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-card">
            <div class="password-header">
                <div class="password-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="password-title">Change Password</h1>
                <p class="password-subtitle">Secure your account with a new password</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = '<?php echo $_SESSION['role'] == 1 ? "admin-dashboard.php" : "staff-dashboard.php"; ?>';
                        }, 2000);
                    </script>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label class="form-label" for="current_password">
                        <i class="fas fa-lock" style="margin-right: 0.5rem;"></i>Current Password
                    </label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-input" 
                           placeholder="Enter your current password" 
                           value="<?php echo htmlspecialchars($current_password); ?>" 
                           required
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="current_password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="new_password">
                        <i class="fas fa-key" style="margin-right: 0.5rem;"></i>New Password
                    </label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-input" 
                           placeholder="Enter new password" 
                           value="<?php echo htmlspecialchars($new_password); ?>" 
                           required
                           autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="new_password">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Password strength: Very weak</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i class="fas fa-check-double" style="margin-right: 0.5rem;"></i>Confirm New Password
                    </label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-input" 
                           placeholder="Confirm new password" 
                           value="<?php echo htmlspecialchars($confirm_password); ?>" 
                           required
                           autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="confirm_password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <!-- Password Requirements -->
                <div class="requirements">
                    <div class="requirements-title">
                        <i class="fas fa-info-circle"></i> Password Requirements
                    </div>
                    <div class="requirement invalid" id="reqLength">
                        <i class="fas fa-circle"></i> At least 8 characters
                    </div>
                    <div class="requirement invalid" id="reqUppercase">
                        <i class="fas fa-circle"></i> At least one uppercase letter
                    </div>
                    <div class="requirement invalid" id="reqLowercase">
                        <i class="fas fa-circle"></i> At least one lowercase letter
                    </div>
                    <div class="requirement invalid" id="reqNumber">
                        <i class="fas fa-circle"></i> At least one number
                    </div>
                    <div class="requirement invalid" id="reqSpecial">
                        <i class="fas fa-circle"></i> At least one special character (!@#$%^&*)
                    </div>
                </div>
                
                <button type="submit" class="btn btn-submit" id="submitBtn">
                    <i class="fas fa-save"></i> Change Password
                </button>
                
                <a href="<?php echo $_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'; ?>" 
                   class="btn btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' 
                    ? '<i class="fas fa-eye"></i>' 
                    : '<i class="fas fa-eye-slash"></i>';
            });
        });
        
        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        
        const requirements = {
            length: document.getElementById('reqLength'),
            uppercase: document.getElementById('reqUppercase'),
            lowercase: document.getElementById('reqLowercase'),
            number: document.getElementById('reqNumber'),
            special: document.getElementById('reqSpecial')
        };
        
        function checkPasswordStrength(password) {
            let score = 0;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            
            // Update requirement indicators
            updateRequirement('length', hasLength);
            updateRequirement('uppercase', hasUppercase);
            updateRequirement('lowercase', hasLowercase);
            updateRequirement('number', hasNumber);
            updateRequirement('special', hasSpecial);
            
            // Calculate score
            if (hasLength) score += 20;
            if (hasUppercase) score += 20;
            if (hasLowercase) score += 20;
            if (hasNumber) score += 20;
            if (hasSpecial) score += 20;
            
            // Update strength bar and text
            strengthBar.style.width = score + '%';
            
            if (score < 40) {
                strengthBar.style.backgroundColor = '#EF4444';
                strengthText.textContent = 'Password strength: Very weak';
                strengthText.style.color = '#EF4444';
            } else if (score < 60) {
                strengthBar.style.backgroundColor = '#F59E0B';
                strengthText.textContent = 'Password strength: Weak';
                strengthText.style.color = '#F59E0B';
            } else if (score < 80) {
                strengthBar.style.backgroundColor = '#3B82F6';
                strengthText.textContent = 'Password strength: Good';
                strengthText.style.color = '#3B82F6';
            } else if (score < 100) {
                strengthBar.style.backgroundColor = '#10B981';
                strengthText.textContent = 'Password strength: Strong';
                strengthText.style.color = '#10B981';
            } else {
                strengthBar.style.backgroundColor = '#10B981';
                strengthText.textContent = 'Password strength: Very strong';
                strengthText.style.color = '#10B981';
            }
            
            return score;
        }
        
        function updateRequirement(type, isValid) {
            const element = requirements[type];
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                element.innerHTML = '<i class="fas fa-check-circle"></i> ' + element.textContent.replace('• ', '');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.innerHTML = '<i class="fas fa-circle"></i> ' + element.textContent.replace('✓ ', '');
            }
        }
        
        // Check password on input
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            checkPasswordStrength(password);
            
            // Check if passwords match
            checkPasswordMatch();
        });
        
        // Check if passwords match
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    confirmPasswordInput.style.borderColor = '#10B981';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.1)';
                } else {
                    confirmPasswordInput.style.borderColor = '#EF4444';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                }
            }
        }
        
        // Form validation
        const passwordForm = document.getElementById('passwordForm');
        passwordForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if fields are empty
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return false;
            }
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                confirmPasswordInput.focus();
                return false;
            }
            
            // Check password strength
            const strength = checkPasswordStrength(newPassword);
            if (strength < 60) {
                e.preventDefault();
                alert('Please choose a stronger password. Your password should be at least "Good" strength.');
                newPasswordInput.focus();
                return false;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
            submitBtn.disabled = true;
            
            // Re-enable after 3 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Change Password';
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Auto-focus current password field
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('current_password').focus();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                passwordForm.submit();
            }
            
            if (e.key === 'Escape') {
                window.location.href = '<?php echo $_SESSION['role'] == 1 ? "admin-dashboard.php" : "staff-dashboard.php"; ?>';
            }
        });
    </script>
</body>
</html>
