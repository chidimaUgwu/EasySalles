<?php
// admin/includes/functions.php
//session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once ROOT_PATH . 'config/db.php';

// Function to get user data
function getUserData($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
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
        return [];
    }
}

// Function to get dashboard stats
function getDashboardStats() {
    global $pdo;
    $stats = [];
    
    try {
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM EASYSALLES_USERS WHERE role = 2");
        $stats['total_staff'] = $stmt->fetch()['total'];
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM EASYSALLES_PRODUCTS");
        $stats['total_products'] = $stmt->fetch()['total'];
        
        // Today's sales
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM EASYSALLES_SALES WHERE DATE(sale_date) = CURDATE()");
        $stats['today_sales'] = $stmt->fetch()['total'];
        
        // Today's revenue
        $stmt = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) as total FROM EASYSALLES_SALES WHERE DATE(sale_date) = CURDATE()");
        $stats['today_revenue'] = $stmt->fetch()['total'];
        
        // Low stock products
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM EASYSALLES_PRODUCTS WHERE current_stock <= min_stock");
        $stats['low_stock'] = $stmt->fetch()['total'];
        
    } catch (PDOException $e) {
        // Return empty stats if tables don't exist yet
        $stats = [
            'total_staff' => 0,
            'total_products' => 0,
            'today_sales' => 0,
            'today_revenue' => 0,
            'low_stock' => 0
        ];
    }
    
    return $stats;
}

// Function to generate employee ID
// Function to generate employee ID
function generateEmployeeId() {
    global $pdo;
    try {
        $year = date('Y');
        // FIX: Use proper parameter binding instead of direct variable injection
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM EASYSALLES_USERS WHERE YEAR(created_at) = ? AND role = 2");
        $stmt->execute([$year]);
        $count = $stmt->fetch()['count'] + 1;
        return "EMP-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return "EMP-" . date('Y') . "-001";
    }
}
// function generateEmployeeId() {
//     global $pdo;
//     try {
//         $year = date('Y');
//         $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_USERS WHERE YEAR(created_at) = $year AND role = 2");
//         $count = $stmt->fetch()['count'] + 1;
//         return "EMP-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
//     } catch (PDOException $e) {
//         return "EMP-" . date('Y') . "-001";
//     }
// }

// Function to generate transaction code
function generateTransactionCode() {
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid()), 0, 6));
    return "TXN-" . $date . "-" . $random;
}
?>
