<?php
// admin/users/delete.php
require_once '../includes/header.php';

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    header('Location: index.php');
    exit();
}

// Check if user exists and is not admin
try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] == 1) {
        $_SESSION['error'] = "Cannot delete admin user or user not found";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Check if user has any sales
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM EASYSALLES_SALES WHERE staff_id = ?");
    $stmt->execute([$user_id]);
    $sales_count = $stmt->fetch()['count'];
    
    if ($sales_count > 0) {
        // Instead of deleting, deactivate the user
        $stmt = $pdo->prepare("UPDATE EASYSALLES_USERS SET status = 'inactive' WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "Staff member deactivated successfully (has $sales_count sales records)";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    // If sales table doesn't exist, continue with deletion
}

// Delete user
try {
    $stmt = $pdo->prepare("DELETE FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $_SESSION['success'] = "Staff member deleted successfully";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting staff member: " . $e->getMessage();
}

header('Location: index.php');
exit();
?>
