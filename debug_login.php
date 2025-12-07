<?php
// debug_login.php
$servername = "localhost:3306";
$username = "chidima.ugwu";
$password = "YOUR_MYSQL_PASSWORD"; // Your actual MySQL password
$dbname = "webtech_2025A_chidima_ugwu";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Debug Login Information</h2>";
    
    // Check users table
    $stmt = $conn->query("SELECT id, user_id, username, password_hash, role, is_active FROM easysalles_users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Current Users in Database:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Password Hash</th><th>Hash Length</th><th>Role</th><th>Active</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td style='font-family: monospace; font-size: 12px;'>" . substr($user['password_hash'], 0, 30) . "...</td>";
        echo "<td>" . strlen($user['password_hash']) . " chars</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>🔑 Test Password Verification:</h3>";
    echo "<form method='POST'>";
    echo "Username/User ID: <input type='text' name='test_username' value='admin'><br>";
    echo "Password to test: <input type='password' name='test_password'><br>";
    echo "<input type='submit' value='Test Login'>";
    echo "</form>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_username = $_POST['test_username'] ?? '';
        $test_password = $_POST['test_password'] ?? '';
        
        $stmt = $conn->prepare("SELECT password_hash FROM easysalles_users WHERE username = ? OR user_id = ?");
        $stmt->execute([$test_username, $test_username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $hash = $user['password_hash'];
            $verify = password_verify($test_password, $hash);
            
            echo "<div style='padding: 10px; margin: 10px 0; background-color: " . ($verify ? '#d4edda' : '#f8d7da') . ";'>";
            echo "<strong>Password Verification:</strong> ";
            echo $verify ? "✅ SUCCESS - Password matches!" : "❌ FAILED - Password doesn't match!";
            echo "<br>";
            echo "Hash used: " . substr($hash, 0, 30) . "...";
            echo "</div>";
            
            // Show what password_hash would create
            echo "<div style='padding: 10px; margin: 10px 0; background-color: #e2e3e5;'>";
            echo "<strong>If you wanted to reset password to 'admin123':</strong><br>";
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            echo "password_hash('admin123'): " . $new_hash;
            echo "</div>";
        } else {
            echo "<div style='padding: 10px; margin: 10px 0; background-color: #f8d7da;'>";
            echo "❌ User not found in database!";
            echo "</div>";
        }
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
