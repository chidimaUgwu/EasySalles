<?php
// update-password-direct.php
// WARNING: This is for emergency use only!

session_start();

// Only allow if logged in as admin (role = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    die('Access denied. Admin only.');
}

require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($username) || empty($new_password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            // Hash the new password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update directly in database
            $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET 
                password_hash = ?, 
                last_password_change = NOW() 
                WHERE username = ?");
            
            if ($stmt->execute([$password_hash, $username])) {
                if ($stmt->rowCount() > 0) {
                    $success = "Password updated for user: $username";
                } else {
                    $error = "User not found: $username";
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin: Update Password Directly</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f0f0f0; }
        .container { max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 10px; }
        input, button { width: 100%; padding: 10px; margin: 10px 0; }
        .success { color: green; background: #d4edda; padding: 10px; }
        .error { color: red; background: #f8d7da; padding: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔧 Direct Password Update (Admin Only)</h2>
        <p><strong>Warning:</strong> This bypasses current password verification.</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username to update" required>
            <input type="password" name="new_password" placeholder="New password" required>
            <button type="submit">Update Password</button>
        </form>
        
        <p><a href="change-password-simple.php">← Back to normal password change</a></p>
    </div>
</body>
</html>
