<?php
// create_admin.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Default admin credentials
$username = 'admin';
$password = 'admin123'; // Change this!
$user_id = 'ADMIN-001';
$full_name = 'System Administrator';
$email = 'admin@easysalles.com';

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin exists
    $check = Database::query("SELECT id FROM easysalles_users WHERE username = ? OR user_id = ?", 
        [$username, $user_id])->fetch();
    
    if ($check) {
        // Update existing admin
        Database::query(
            "UPDATE easysalles_users SET 
                password_hash = ?,
                full_name = ?,
                email = ?,
                is_active = 1,
                force_password_change = 0
             WHERE id = ?",
            [$password_hash, $full_name, $email, $check['id']]
        );
        
        echo "✅ Admin password updated!<br>";
        echo "Username: $username<br>";
        echo "Password: $password<br>";
        echo "User ID: $user_id";
    } else {
        // Create new admin
        Database::query(
            "INSERT INTO easysalles_users 
            (user_id, username, password_hash, role, full_name, email, is_active) 
            VALUES (?, ?, ?, 'admin', ?, ?, 1)",
            [$user_id, $username, $password_hash, $full_name, $email]
        );
        
        echo "✅ Admin created successfully!<br>";
        echo "Username: $username<br>";
        echo "Password: $password<br>";
        echo "User ID: $user_id";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
