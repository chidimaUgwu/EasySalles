<?php
// config/db.php - OUTSIDE web root - NEVER commit!

define('DB_HOST', 'localhost');
define('DB_USER', 'chidima.ugwu');
define('DB_PASS', '66071288');  // Change this!
define('DB_NAME', 'webtech_2025A_chidima_ugwu');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed.");
}
?>
