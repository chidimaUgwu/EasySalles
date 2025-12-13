<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasySalles <?php echo isset($page_title) ? ' | ' . htmlspecialchars($page_title) : ''; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <div class="logo">
                <h1><i class="fas fa-cash-register"></i> EasySalles</h1>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="nav">
                <a href="<?php echo $_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'; ?>">
                    Dashboard
                </a>
                <a href="sale-record.php">Record Sale</a>
                <a href="sales-list.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
            <div class="user-info">
                <img src="<?php echo $_SESSION['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="avatar">
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="main-content container">
