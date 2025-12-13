<?php
// reset_password.php - ONE TIME USE ONLY - DELETE AFTER USE!
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];
    
    // Hash the new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        Database::query(
            "UPDATE EASYSALLES_USERS SET password_hash = ?, force_password_change = 1 WHERE username = ?",
            [$password_hash, $username]
        );
        
        echo "<div style='color: green; padding: 10px; background-color: #d4edda;'>";
        echo "✅ Password reset successfully for user: $username";
        echo "<br>New password hash: " . substr($password_hash, 0, 30) . "...";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>⚠️ Reset User Password</h2>
    <p><strong>WARNING:</strong> Only use this in development. Delete this file after use!</p>
    
    <form method="POST">
        <div class="form-group">
            <label>Username:</label><br>
            <input type="text" name="username" value="admin" required>
        </div>
        
        <div class="form-group">
            <label>New Password:</label><br>
            <input type="password" name="new_password" value="admin123" required>
        </div>
        
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
