<?php
// login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'));
    exit();
}

$page_title = 'Login';
include 'includes/header.php';

// Placeholder for login logic (we'll add real auth soon)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Temporary mock success
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Admin';
    $_SESSION['role'] = 1; // 1 = admin, 2 = staff
    $_SESSION['avatar'] = 'assets/images/default-avatar.png';
    header('Location: admin-dashboard.php');
    exit();
}
?>

<div class="login-container">
    <div class="login-card card">
        <div class="text-center mb-2">
            <i class="fas fa-cash-register login-icon"></i>
            <h2>Welcome Back</h2>
            <p>Sign in to your EasySalles account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username or Staff ID</label>
                <input type="text" id="username" name="username" class="form-control" required placeholder="Enter your username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="text-center mt-2">
            <small>First time? Contact your admin for credentials.</small>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
