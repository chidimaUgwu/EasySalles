<?php
// reset-request.php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username FROM EASYSALLES_USERS WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($user = $stmt->fetch()) {
                $token = bin2hex(random_bytes(50));
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    reset_token = ?, 
                    reset_expires = ?,
                    reset_attempts = COALESCE(reset_attempts, 0) + 1
                    WHERE user_id = ?");
                $updateStmt->execute([hash('sha256', $token), $expires, $user['user_id']]);
                
                $reset_link = "https://yourdomain.com/reset-password.php?token=$token";
                
                $success = 'If an account exists with that email, you will receive a password reset link shortly.';
                $email_sent = true;
            } else {
                $success = 'If an account exists with that email, you will receive a password reset link shortly.';
                $email_sent = true;
            }
        } catch (PDOException $e) {
            $error = 'System error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles | Reset Password</title>
    
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
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3.5rem 3rem;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }
        
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .reset-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary), #6D28D9);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.8rem;
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.25);
            transform: rotate(-5deg);
            transition: transform 0.3s ease;
        }
        
        .reset-icon:hover {
            transform: rotate(0deg);
        }
        
        .reset-icon i {
            color: white;
            font-size: 2.8rem;
        }
        
        .reset-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .reset-subtitle {
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
            margin-bottom: 2rem;
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
            color: var(--accent);
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
            border-color: var(--primary);
            box-shadow: 0 0 0 5px rgba(124, 58, 237, 0.15);
            transform: translateY(-1px);
        }
        
        .form-input:hover {
            border-color: var(--gray-500);
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
            background: linear-gradient(135deg, var(--primary), #6D28D9);
            color: white;
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.3);
            margin-top: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.4);
            background: linear-gradient(135deg, #6D28D9, var(--primary));
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }
        
        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(6, 182, 212, 0.03));
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2.5rem;
            border-left: 4px solid var(--accent);
        }
        
        .info-title {
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .info-text {
            color: var(--gray-500);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Links */
        .reset-links {
            text-align: center;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-300);
        }
        
        .reset-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .reset-link:hover {
            color: var(--secondary);
            transform: translateX(3px);
        }
        
        /* Success State */
        .success-state {
            text-align: center;
            padding: 2rem 0;
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1.5rem;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        .success-state h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            color: var(--text);
            margin-bottom: 1rem;
        }
        
        .success-state p {
            color: var(--gray-500);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        /* Email Preview */
        .email-preview {
            background: var(--gray-100);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px dashed var(--gray-300);
        }
        
        .email-preview-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .email-preview-text {
            color: var(--gray-500);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1.5rem;
            }
            
            .reset-card {
                padding: 2.5rem 2rem;
            }
            
            .reset-title {
                font-size: 2rem;
            }
            
            .reset-icon {
                width: 75px;
                height: 75px;
            }
            
            .reset-icon i {
                font-size: 2.2rem;
            }
            
            .btn {
                padding: 1.1rem 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .reset-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
            
            .reset-title {
                font-size: 1.8rem;
            }
            
            .reset-subtitle {
                font-size: 1rem;
            }
            
            .form-input {
                padding: 1rem 1.3rem;
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
        
        /* Floating Animation */
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon floating">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="reset-title">Reset Password</h1>
                <p class="reset-subtitle">Enter your email address and we'll send you a secure link to reset your password.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($email_sent)): ?>
                <div class="success-state">
                    <div class="success-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <h3>Check Your Email!</h3>
                    <p><?php echo htmlspecialchars($success); ?></p>
                    
                    <div class="info-box">
                        <div class="info-title">
                            <i class="fas fa-info-circle"></i>
                            <span>What happens next?</span>
                        </div>
                        <p class="info-text">
                            1. Check your inbox for an email from EasySalles<br>
                            2. Click the secure reset link in the email<br>
                            3. Create a new strong password<br>
                            4. Return to login with your new credentials
                        </p>
                    </div>
                    
                    <div class="reset-links">
                        <a href="login.php" class="reset-link">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label class="form-label" for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="Enter your registered email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               autofocus>
                    </div>
                    
                    <button type="submit" class="btn btn-submit btn-block" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
                
                <div class="info-box">
                    <div class="info-title">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security Notice</span>
                    </div>
                    <p class="info-text">
                        For your security, the password reset link will expire in 15 minutes. 
                        If you don't receive the email, check your spam folder or try again.
                    </p>
                </div>
                
                <div class="reset-links">
                    <a href="login.php" class="reset-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                    <span style="margin: 0 1rem; color: var(--gray-300);">|</span>
                    <a href="register.php" class="reset-link">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form submission animation
        const resetForm = document.getElementById('resetForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                // Basic validation
                const email = document.getElementById('email').value;
                if (!email || !email.includes('@')) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<span class="loading"></span> Sending...';
                submitBtn.disabled = true;
            });
        }
        
        // Add floating animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.01)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // Auto-focus email field if not on success state
            if (document.getElementById('email')) {
                setTimeout(() => {
                    document.getElementById('email').focus();
                }, 300);
            }
        });
        
        // Add hover effects to card
        const card = document.querySelector('.reset-card');
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 35px 80px rgba(0, 0, 0, 0.12)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 25px 70px rgba(0, 0, 0, 0.08)';
        });
    </script>
</body>
</html>
