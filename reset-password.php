<?php
// reset-password.php
require_once 'config/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$invalid_token = false;

// Validate token
if (empty($token)) {
    header('Location: reset-request.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM EASYSALLES_USERS 
                          WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([hash('sha256', $token)]);
    
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'This reset link has expired or is invalid. Please request a new password reset link.';
        $invalid_token = true;
    }
} catch (PDOException $e) {
    $error = 'System error. Please try again later.';
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
            password_hash = ?,
            reset_token = NULL,
            reset_expires = NULL,
            last_password_change = NOW()
            WHERE user_id = ?");
        
        if ($updateStmt->execute([$new_hash, $user['user_id']])) {
            $success = 'Password reset successfully! Redirecting to login...';
            $password_reset = true;
            
            // Auto-redirect to login after 3 seconds
            header('Refresh: 3; URL=login.php');
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles | Set New Password</title>
    
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
            --gray-100: #F1F5F9;
            --gray-300: #CBD5E1;
            --gray-500: #64748B;
            --gray-700: #334155;
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
            line-height: 1.6;
        }
        
        .newpass-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .newpass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3.5rem 3rem;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }
        
        .newpass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--accent), var(--primary));
        }
        
        .newpass-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .newpass-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--accent), #0891B2);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.8rem;
            box-shadow: 0 15px 35px rgba(6, 182, 212, 0.25);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .newpass-icon i {
            color: white;
            font-size: 2.8rem;
        }
        
        .newpass-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .newpass-subtitle {
            color: var(--gray-500);
            font-size: 1.1rem;
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Message Styles */
        .message {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            font-weight: 500;
            animation: slideIn 0.4s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid transparent;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(220, 38, 38, 0.05));
            color: #DC2626;
            border-left-color: #EF4444;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1);
        }
        
        .success-message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(5, 150, 105, 0.05));
            color: var(--success);
            border-left-color: var(--success);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1);
        }
        
        .message i {
            font-size: 1.3rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            color: var(--gray-700);
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1.1rem 1.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 16px;
            font-size: 1.05rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            color: var(--text);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 5px rgba(6, 182, 212, 0.15);
            transform: translateY(-1px);
        }
        
        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 2.8rem;
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 8px;
        }
        
        .password-toggle:hover {
            color: var(--primary);
            background: var(--gray-100);
        }
        
        /* Password Strength Indicator */
        .password-strength {
            height: 8px;
            background: var(--gray-300);
            border-radius: 4px;
            margin-top: 0.8rem;
            overflow: hidden;
            position: relative;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.4s ease;
            border-radius: 4px;
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .strength-text {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
            text-align: right;
            font-weight: 500;
        }
        
        /* Password Requirements */
        .requirements {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.03));
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
            border-left: 4px solid var(--secondary);
        }
        
        .requirements-title {
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .requirement {
            color: var(--gray-500);
            font-size: 0.95rem;
            margin: 0.6rem 0;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .requirement.valid {
            color: var(--success);
        }
        
        .requirement.invalid {
            color: var(--gray-500);
        }
        
        .requirement i {
            font-size: 0.9rem;
            width: 16px;
        }
        
        /* Button Styles */
        .btn {
            padding: 1.2rem 2.5rem;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            text-decoration: none;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--accent), #0891B2);
            color: white;
            box-shadow: 0 10px 30px rgba(6, 182, 212, 0.3);
            margin-top: 1.5rem;
        }
        
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(6, 182, 212, 0.4);
            background: linear-gradient(135deg, #0891B2, var(--accent));
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Success State */
        .success-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .success-icon {
            font-size: 5rem;
            color: var(--success);
            margin-bottom: 1.5rem;
            animation: bounceIn 1s ease;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0); }
            60% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .success-state h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            color: var(--text);
            margin-bottom: 1rem;
        }
        
        .success-state p {
            color: var(--gray-500);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .redirect-text {
            color: var(--primary);
            font-size: 0.95rem;
            margin-top: 2rem;
            font-weight: 500;
        }
        
        /* Links */
        .newpass-links {
            text-align: center;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-300);
        }
        
        .newpass-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .newpass-link:hover {
            color: var(--secondary);
            transform: translateX(3px);
        }
        
        /* Progress Bar */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: var(--gray-300);
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--gray-300);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            font-weight: 600;
        }
        
        .step.active .step-number {
            background: var(--primary);
            box-shadow: 0 0 0 5px rgba(124, 58, 237, 0.2);
        }
        
        .step.completed .step-number {
            background: var(--success);
        }
        
        .step-label {
            color: var(--gray-500);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1.5rem;
            }
            
            .newpass-card {
                padding: 2.5rem 2rem;
            }
            
            .newpass-title {
                font-size: 2rem;
            }
            
            .newpass-icon {
                width: 75px;
                height: 75px;
            }
            
            .newpass-icon i {
                font-size: 2.2rem;
            }
        }
        
        @media (max-width: 480px) {
            .newpass-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
            
            .newpass-title {
                font-size: 1.8rem;
            }
            
            .form-input {
                padding: 1rem 1.3rem;
            }
            
            .progress-steps {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
            }
            
            .progress-steps::before {
                display: none;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="newpass-container">
        <div class="newpass-card">
            <?php if ($invalid_token): ?>
                <div class="newpass-header">
                    <div class="newpass-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1 class="newpass-title">Invalid Link</h1>
                    <p class="newpass-subtitle">This password reset link has expired or is invalid.</p>
                </div>
                
                <div class="error-message message">
                    <i class="fas fa-times-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                
                <div class="newpass-links">
                    <a href="reset-request.php" class="btn btn-submit btn-block">
                        <i class="fas fa-redo"></i> Request New Reset Link
                    </a>
                    <div style="margin-top: 1.5rem;">
                        <a href="login.php" class="newpass-link">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
                
            <?php elseif (isset($password_reset)): ?>
                <div class="success-state">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Password Reset Successfully!</h3>
                    <p>Your password has been updated successfully. You will be redirected to login shortly.</p>
                    
                    <div class="redirect-text">
                        <i class="fas fa-spinner fa-spin"></i> Redirecting in 3 seconds...
                    </div>
                    
                    <div class="newpass-links">
                        <a href="login.php" class="newpass-link">
                            <i class="fas fa-sign-in-alt"></i> Login Now
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="progress-steps">
                    <div class="step completed">
                        <div class="step-number">1</div>
                        <div class="step-label">Request</div>
                    </div>
                    <div class="step active">
                        <div class="step-number">2</div>
                        <div class="step-label">New Password</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
                
                <div class="newpass-header">
                    <div class="newpass-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h1 class="newpass-title">Set New Password</h1>
                    <p class="newpass-subtitle">Create a strong, secure password for your account.</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="message error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="passwordForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password">
                            <i class="fas fa-key"></i> New Password
                        </label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="Enter new password" 
                               required
                               autofocus>
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
                            <i class="fas fa-check-double"></i> Confirm Password
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Confirm new password" 
                               required>
                        <button type="button" class="password-toggle" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="requirements">
                        <div class="requirements-title">
                            <i class="fas fa-shield-alt"></i>
                            <span>Password Requirements</span>
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
                            <i class="fas fa-circle"></i> At least one special character
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-submit btn-block" id="submitBtn">
                        <i class="fas fa-lock"></i> Reset Password
                    </button>
                </form>
                
                <div class="newpass-links">
                    <a href="reset-request.php" class="newpass-link">
                        <i class="fas fa-redo"></i> Request New Link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
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
                strengthBar.style.backgroundColor = '#06B6D4';
                strengthText.textContent = 'Password strength: Good';
                strengthText.style.color = '#06B6D4';
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
                element.innerHTML = '<i class="fas fa-check"></i> ' + element.textContent.replace('• ', '');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.innerHTML = '<i class="fas fa-circle"></i> ' + element.textContent.replace('✓ ', '');
            }
        }
        
        // Check password on input
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                checkPasswordStrength(password);
                checkPasswordMatch();
            });
        }
        
        // Check if passwords match
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            if (!newPasswordInput || !confirmPasswordInput) return;
            
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    confirmPasswordInput.style.borderColor = '#10B981';
                    confirmPasswordInput.style.boxShadow = '0 0 0 5px rgba(16, 185, 129, 0.15)';
                } else {
                    confirmPasswordInput.style.borderColor = '#EF4444';
                    confirmPasswordInput.style.boxShadow = '0 0 0 5px rgba(239, 68, 68, 0.15)';
                }
            } else {
                confirmPasswordInput.style.borderColor = '';
                confirmPasswordInput.style.boxShadow = '';
            }
        }
        
        // Form validation
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const currentPassword = document.getElementById('new_password')?.value;
                const confirmPassword = document.getElementById('confirm_password')?.value;
                
                if (!currentPassword || !confirmPassword) {
                    e.preventDefault();
                    alert('Please fill in all fields.');
                    return false;
                }
                
                if (currentPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    confirmPasswordInput.focus();
                    return false;
                }
                
                const strength = checkPasswordStrength(currentPassword);
                if (strength < 60) {
                    e.preventDefault();
                    alert('Please choose a stronger password. Your password should be at least "Good" strength.');
                    newPasswordInput.focus();
                    return false;
                }
                
                // Show loading state
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="loading"></span> Resetting Password...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        // Auto-focus password field
        if (newPasswordInput) {
            setTimeout(() => {
                newPasswordInput.focus();
            }, 400);
        }
        
        // Add card hover effect
        const card = document.querySelector('.newpass-card');
        if (card) {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 35px 80px rgba(0, 0, 0, 0.12)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 25px 70px rgba(0, 0, 0, 0.08)';
            });
        }
    </script>
</body>
</html>
