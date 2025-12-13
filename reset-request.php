<?php
// reset-request.php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id, username FROM EASYSALLES_USERS WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($user = $stmt->fetch()) {
                // Generate secure token
                $token = bin2hex(random_bytes(50));
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store token in database
                $updateStmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                    reset_token = ?, 
                    reset_expires = ?,
                    reset_attempts = COALESCE(reset_attempts, 0) + 1
                    WHERE user_id = ?");
                $updateStmt->execute([hash('sha256', $token), $expires, $user['user_id']]);
                
                // Send reset email
                $reset_link = "https://yourdomain.com/reset-password.php?token=$token";
                
                // Email content
                $subject = "Password Reset Request - EasySalles";
                $message = "
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['username']},</p>
                    <p>You requested a password reset. Click the link below to reset your password:</p>
                    <p><a href='$reset_link' style='padding: 10px 20px; background: #7C3AED; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>This link expires in 15 minutes.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <hr>
                    <p><small>This is an automated message from EasySalles</small></p>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: EasySalles <noreply@easysalles.com>" . "\r\n";
                
                // Send email
                mail($email, $subject, $message, $headers);
                
                // Don't reveal if email exists (security best practice)
                $success = 'If an account exists with that email, you will receive a password reset link shortly.';
                
                // Log the attempt
                error_log("Password reset requested for email: $email - IP: " . $_SERVER['REMOTE_ADDR']);
            } else {
                // Still show success for security (don't reveal if email exists)
                $success = 'If an account exists with that email, you will receive a password reset link shortly.';
            }
        } catch (PDOException $e) {
            $error = 'System error. Please try again later.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - EasySalles</title>
    <!-- Include your CSS styles here -->
</head>
<body>
    <div class="password-container">
        <div class="password-card">
            <h2>Reset Your Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required class="form-input" 
                           placeholder="Enter your registered email">
                </div>
                <button type="submit" class="btn btn-submit">Send Reset Link</button>
            </form>
            <p><a href="login.php">← Back to Login</a></p>
        </div>
    </div>
</body>
</html>
