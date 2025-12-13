<?php
// index.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// If already logged in, redirect to appropriate page
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';

// First check if database tables exist
if (!checkEasySallesTables()) {
    $error = "System setup incomplete. Please contact administrator.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];
    
    try {
        // Find user by user_id or username - check both tables starting with easysalles
        $user = Database::query(
            "SELECT * FROM easysalles_users WHERE (user_id = ? OR username = ?) AND is_active = 1",
            [$user_id, $user_id]
        )->fetch();
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_code'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                Database::query(
                    "UPDATE easysalles_users SET last_login = NOW() WHERE id = ?",
                    [$user['id']]
                );
                
                // Check if password change is required
                if ($user['force_password_change']) {
                    $_SESSION['force_password_change'] = true;
                    header('Location: profile/change_password.php');
                    exit();
                }
                
                // Log session
                Database::query(
                    "INSERT INTO easysalles_sessions (user_id, login_time, ip_address, user_agent) 
                     VALUES (?, NOW(), ?, ?)",
                    [$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]
                );
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = "Invalid login credentials";
            }
        } else {
            $error = "Invalid login credentials";
        }
    } catch (Exception $e) {
        $error = "Login error. Please try again.";
        error_log("Login Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary: <?php echo PRIMARY_COLOR; ?>;
            --secondary: <?php echo SECONDARY_COLOR; ?>;
            --accent: <?php echo ACCENT_COLOR; ?>;
            --bg: <?php echo BG_COLOR; ?>;
            --text: <?php echo TEXT_COLOR; ?>;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-circle">
                    <span class="logo-text">ES</span>
                </div>
                <h1><?php echo APP_NAME; ?></h1>
                <p class="tagline">Sales Management Made Simple</p>
            </div>
            
            <!-- Login Form -->
            <form method="POST" class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="user_id">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        User ID or Username
                    </label>
                    <input type="text" id="user_id" name="user_id" required 
                           placeholder="ADMIN-001 or username"
                           value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    Sign In
                </button>
                
                <div class="login-footer">
                    <p>Need help? Contact your administrator</p>
                    <p>Default Admin: ADMIN-001 / password: admin123</p>
                </div>
            </form>
        </div>
        
        <div class="login-features">
            <h2>Welcome to <?php echo APP_NAME; ?></h2>
            <div class="features-grid">
                <div class="feature">
                    <div class="feature-icon purple">
                        <svg viewBox="0 0 24 24"><path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                    </div>
                    <h3>Quick Sales</h3>
                    <p>Process transactions in seconds</p>
                </div>
                <div class="feature">
                    <div class="feature-icon pink">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                    </div>
                    <h3>Real-time Reports</h3>
                    <p>Track performance instantly</p>
                </div>
                <div class="feature">
                    <div class="feature-icon cyan">
                        <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </div>
                    <h3>Smart Analytics</h3>
                    <p>Make data-driven decisions</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
