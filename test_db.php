<?php
echo "Testing Database Connection...<br>";

// Test database connection
try {
    $host = 'localhost:3306';
    $dbname = 'webtech_2025A_chidima_ugwu';
    $username = 'chidima.ugwu';
    $password = 'your_password_here'; // Your MySQL password
    
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "✅ Database connection successful!<br>";
    
    // Test if users table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'easysalles_users'")->fetch();
    
    if ($tables) {
        echo "✅ Users table exists!<br>";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) as count FROM easysalles_users")->fetch();
        echo "📊 Total users: " . $count['count'] . "<br>";
        
        // List all users
        echo "<h3>User List:</h3>";
        $users = $pdo->query("SELECT id, user_id, username, full_name, role FROM easysalles_users")->fetchAll();
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['user_id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Users table not found!<br>";
        
        // Try to create the table
        echo "Attempting to create users table...<br>";
        
        $sql = "CREATE TABLE easysalles_users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id VARCHAR(20) UNIQUE NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'salesperson') DEFAULT 'salesperson',
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            avatar_url VARCHAR(255),
            shift_start TIME,
            shift_end TIME,
            shift_days VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            force_password_change BOOLEAN DEFAULT TRUE,
            last_login DATETIME,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        echo "✅ Users table created!<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}
?>
