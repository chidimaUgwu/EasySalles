<?php
// reset_password.php - ONE FILE FIX
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];
    
    // Hash the new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        // DIRECT DATABASE CONNECTION - NO EXTERNAL FILES
        $host = 'localhost';
        $dbname = 'webtech_2025A_chidima_ugwu';
        $user = 'chidima.ugwu';  // Your NetBenefit username
        $pass = '66071288';  // Your MySQL password
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Update the password (remove force_password_change if column doesn't exist)
        $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET password_hash = ? WHERE username = ?");
        $stmt->execute([$password_hash, $username]);
        
        echo "<div style='color: green; padding: 10px; background-color: #d4edda;'>";
        echo "✅ Password reset successfully for user: $username";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 10px; background-color: #f8d7da;'>";
        echo "❌ Error: " . $e->getMessage();
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { padding: 10px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 12px 24px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #c82333; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <div class="warning">
        <strong>⚠️ WARNING:</strong> Only use this in development. Delete this file after use!
    </div>
    
    <h2>Reset User Password</h2>
    
    <form method="POST">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" value="admin" required>
        </div>
        
        <div class="form-group">
            <label>New Password:</label>
            <input type="password" name="new_password" value="admin123" required>
        </div>
        
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
