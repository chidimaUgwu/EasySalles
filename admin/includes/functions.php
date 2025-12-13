<?php
// admin/includes/functions.php
session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../../config/db.php';

// Function to get user data
function getUserData($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting user data: " . $e->getMessage());
        return null;
    }
}

// Function to get all users
function getAllUsers($limit = 100) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS ORDER BY user_id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting all users: " . $e->getMessage());
        return [];
    }
}

// Function to get dashboard stats - SINGLE DEFINITION
function getDashboardStats() {
    global $pdo;
    
    $stats = [
        'total_staff' => 0,
        'total_products' => 0,
        'today_sales' => 0,
        'today_revenue' => 0,
        'low_stock' => 0
    ];
    
    try {
        // Get total staff (role = 2 or != 'admin')
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_USERS WHERE role != 1");
        $result = $stmt->fetch();
        $stats['total_staff'] = $result['count'] ?? 0;
        
        // Get total products - check if table exists first
        $stmt = $pdo->query("SHOW TABLES LIKE 'EASYSALLES_PRODUCTS'");
        if ($stmt->fetch()) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_PRODUCTS");
            $result = $stmt->fetch();
            $stats['total_products'] = $result['count'] ?? 0;
            
            // Low stock products
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_PRODUCTS WHERE current_stock <= min_stock");
            $result = $stmt->fetch();
            $stats['low_stock'] = $result['count'] ?? 0;
        }
        
        // Get today's sales - check if table exists first
        $stmt = $pdo->query("SHOW TABLES LIKE 'EASYSALLES_SALES'");
        if ($stmt->fetch()) {
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as revenue 
                                  FROM EASYSALLES_SALES 
                                  WHERE DATE(sale_date) = :today");
            $stmt->execute(['today' => $today]);
            $result = $stmt->fetch();
            $stats['today_sales'] = $result['count'] ?? 0;
            $stats['today_revenue'] = $result['revenue'] ?? 0;
        }
        
    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// Function to generate employee ID
function generateEmployeeId() {
    global $pdo;
    try {
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_USERS WHERE YEAR(created_at) = $year AND role = 2");
        $count = $stmt->fetch()['count'] + 1;
        return "EMP-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return "EMP-" . date('Y') . "-001";
    }
}

// Function to generate transaction code
function generateTransactionCode() {
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid()), 0, 6));
    return "TXN-" . $date . "-" . $random;
}
?>
