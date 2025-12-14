<?php
// logout.php - Logout with confirmation page
session_start();

// Check if logout is confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Store user info for message
    $username = $_SESSION['username'] ?? 'User';
    $role = $_SESSION['role'] ?? 2;
    
    // Log the logout (optional)
    error_log("User logout: $username (Role: $role) - IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Redirect to login with success message
    header('Location: login.php?logout=1&user=' . urlencode($username));
    exit();
}

// If not confirmed, show logout confirmation page
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout | EasySalles</title>
    
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
        
        .logout-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logout-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.6);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .logout-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), #6D28D9);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.25);
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .logout-icon i {
            color: white;
            font-size: 3rem;
        }
        
        .logout-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 1rem;
        }
        
        .logout-message {
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .user-info {
            background: var(--bg);
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin: 1.5rem 0;
            display: inline-block;
        }
        
        .user-info strong {
            color: var(--primary);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 1rem 2rem;
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
            border: none;
            flex: 1;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, var(--error), #DC2626);
            color: white;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        
        .btn-confirm:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #DC2626, var(--error));
        }
        
        .btn-cancel {
            background: var(--bg);
            color: var(--text);
            border: 2px solid var(--border);
        }
        
        .btn-cancel:hover {
            background: var(--border);
            transform: translateY(-3px);
        }
        
        .security-notice {
            margin-top: 2rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(6, 182, 212, 0.03));
            border-radius: 16px;
            border-left: 4px solid var(--accent);
            text-align: left;
        }
        
        .security-notice h4 {
            color: var(--accent);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .security-notice ul {
            color: var(--text);
            padding-left: 1.5rem;
            font-size: 0.9rem;
        }
        
        .security-notice li {
            margin: 0.3rem 0;
        }
        
        .auto-redirect {
            margin-top: 1.5rem;
            color: var(--text);
            font-size: 0.9rem;
        }
        
        .countdown {
            color: var(--primary);
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .logout-container {
                padding: 1rem;
            }
            
            .logout-card {
                padding: 2rem;
            }
            
            .logout-title {
                font-size: 2rem;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
            }
            
            .logout-icon i {
                font-size: 2.5rem;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .logout-card {
                padding: 1.5rem;
                border-radius: 20px;
            }
            
            .logout-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Logout</h1>
            
            <p class="logout-message">
                Are you sure you want to log out of your account?
            </p>
            
            <div class="user-info">
                <i class="fas fa-user"></i> 
                Currently logged in as: <strong><?php echo htmlspecialchars($username); ?></strong>
                <?php if ($role == 1): ?>
                    <span style="color: var(--primary); margin-left: 0.5rem;">
                        <i class="fas fa-crown"></i> Administrator
                    </span>
                <?php else: ?>
                    <span style="color: var(--accent); margin-left: 0.5rem;">
                        <i class="fas fa-user-tie"></i> Staff Member
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <a href="?confirm=yes" class="btn btn-confirm">
                    <i class="fas fa-check"></i> Yes, Log Me Out
                </a>
                
                <?php if ($role == 1): ?>
                    <a href="admin/index.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> No, Go Back to Admin
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> No, Go Back
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="security-notice">
                <h4>
                    <i class="fas fa-shield-alt"></i>
                    Security Notice
                </h4>
                <ul>
                    <li>Your session will be completely terminated</li>
                    <li>You'll need to log in again to access your account</li>
                    <li>For security, close your browser after logging out</li>
                </ul>
            </div>
            
            <div class="auto-redirect">
                You will be automatically redirected to login in 
                <span class="countdown" id="countdown">30</span> seconds
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect countdown
        let seconds = 30;
        const countdownElement = document.getElementById('countdown');
        const countdownInterval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '?confirm=yes';
            }
        }, 1000);
        
        // Cancel auto-redirect if user interacts
        document.addEventListener('click', () => {
            clearInterval(countdownInterval);
            document.querySelector('.auto-redirect').style.display = 'none';
        });
        
        document.addEventListener('keydown', () => {
            clearInterval(countdownInterval);
            document.querySelector('.auto-redirect').style.display = 'none';
        });
        
        // Prevent back button after logout
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
