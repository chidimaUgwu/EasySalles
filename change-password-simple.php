<?php
// change-password-simple.php
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
$username = '';

// Get current username
try {
    $stmt = $pdo->prepare("SELECT username FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $username = $user['username'];
} catch (PDOException $e) {
    $error = 'Database error.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Get current user's password hash
            $stmt = $pdo->prepare("SELECT password_hash FROM EASYSALLES_USERS WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                // Current password is correct, update to new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    password_hash = ?, 
                    last_password_change = NOW() 
                    WHERE user_id = ?");
                
                if ($updateStmt->execute([$new_password_hash, $_SESSION['user_id']])) {
                    $success = 'Password changed successfully!';
                    
                    // Clear form
                    $_POST = array();
                    
                    // Log the action
                    error_log("Password changed for user_id: {$_SESSION['user_id']} ({$username})");
                    
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | EasySalles</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .header .user-info {
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 15px;
            font-weight: 500;
        }
        
        .content {
            padding: 30px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        .input-group {
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #7C3AED;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
        }
        
        .toggle-password:hover {
            color: #7C3AED;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 3px;
        }
        
        .strength-text {
            font-size: 13px;
            color: #666;
            text-align: right;
        }
        
        .requirements {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #7C3AED;
        }
        
        .requirements h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .requirement.valid {
            color: #10b981;
        }
        
        .requirement i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer a {
            color: #7C3AED;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .container {
                border-radius: 15px;
            }
            
            .header, .content {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Change Password</h1>
            <p>Secure your account with a new password</p>
            <?php if ($username): ?>
                <div class="user-info">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'dashboard.php';
                        }, 2000);
                    </script>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">
                        <i class="fas fa-lock"></i> Current Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               placeholder="Enter your current password" 
                               required
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">
                        <i class="fas fa-key"></i> New Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               placeholder="Enter new password (min 8 characters)" 
                               required
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Strength: Very weak</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-check-double"></i> Confirm New Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="Re-enter new password" 
                               required
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="requirements">
                    <h4><i class="fas fa-info-circle"></i> Password Requirements:</h4>
                    <div class="requirement" id="reqLength">
                        <i class="fas fa-circle"></i> Minimum 8 characters
                    </div>
                    <div class="requirement" id="reqUpper">
                        <i class="fas fa-circle"></i> At least one uppercase letter
                    </div>
                    <div class="requirement" id="reqLower">
                        <i class="fas fa-circle"></i> At least one lowercase letter
                    </div>
                    <div class="requirement" id="reqNumber">
                        <i class="fas fa-circle"></i> At least one number
                    </div>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-save"></i> Change Password
                </button>
            </form>
            
            <div class="footer">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const confirmInput = document.getElementById('confirm_password');
        
        const requirements = {
            length: document.getElementById('reqLength'),
            upper: document.getElementById('reqUpper'),
            lower: document.getElementById('reqLower'),
            number: document.getElementById('reqNumber')
        };
        
        function checkPasswordStrength(password) {
            let score = 0;
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement('length', hasLength);
            updateRequirement('upper', hasUpper);
            updateRequirement('lower', hasLower);
            updateRequirement('number', hasNumber);
            
            // Calculate score
            if (hasLength) score += 25;
            if (hasUpper) score += 25;
            if (hasLower) score += 25;
            if (hasNumber) score += 25;
            
            // Update strength bar
            strengthFill.style.width = score + '%';
            
            if (score < 50) {
                strengthFill.style.backgroundColor = '#ef4444';
                strengthText.textContent = 'Strength: Very weak';
                strengthText.style.color = '#ef4444';
            } else if (score < 75) {
                strengthFill.style.backgroundColor = '#f59e0b';
                strengthText.textContent = 'Strength: Fair';
                strengthText.style.color = '#f59e0b';
            } else if (score < 100) {
                strengthFill.style.backgroundColor = '#10b981';
                strengthText.textContent = 'Strength: Good';
                strengthText.style.color = '#10b981';
            } else {
                strengthFill.style.backgroundColor = '#10b981';
                strengthText.textContent = 'Strength: Excellent';
                strengthText.style.color = '#10b981';
            }
            
            return score;
        }
        
        function updateRequirement(type, isValid) {
            const element = requirements[type];
            if (isValid) {
                element.classList.add('valid');
                element.innerHTML = '<i class="fas fa-check"></i> ' + element.textContent.replace('• ', '');
            } else {
                element.classList.remove('valid');
                element.innerHTML = '<i class="fas fa-circle"></i> ' + element.textContent.replace('✓ ', '');
            }
        }
        
        // Check password strength on input
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        // Check password match
        confirmInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    confirmInput.style.borderColor = '#10b981';
                } else {
                    confirmInput.style.borderColor = '#ef4444';
                }
            }
        }
        
        // Form validation
        const form = document.getElementById('passwordForm');
        form.addEventListener('submit', function(e) {
            const currentPass = document.getElementById('current_password').value;
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (!currentPass || !newPass || !confirmPass) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New passwords do not match.');
                confirmInput.focus();
                return;
            }
            
            if (newPass.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                newPasswordInput.focus();
                return;
            }
            
            // Show loading
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
            btn.disabled = true;
        });
        
        // Auto-focus current password field
        document.getElementById('current_password').focus();
    </script>
</body>
</html>
