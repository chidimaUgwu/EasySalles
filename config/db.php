<?php
// config/db.php

$host = 'localhost';
$dbname = 'webtech_2025A_chidima_ugwu'; // Change this
$username = 'chidima.ugwu'; // Change this
$password = '66071288'; // Change this

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
