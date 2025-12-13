<?php
// login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'));
    exit();
}

require 'includes/db.php';  // Remove or change path on server

$page_title = 'Login';
include 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Fetch user
        $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, avatar_url, first_login FROM EASYSALLES_USERS WHERE username = ? OR user_id = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Successful login
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar_url'] ?? 'assets/images/default-avatar.png';

            // Mark first login if needed (we'll set this flag on user creation)
            if ($user['first_login'] == 1) {
                $_SESSION['first_login'] = true;
                header('Location: profile.php?force_change=1');
                exit();
            }

            // Update last login
            $pdo->prepare("UPDATE EASYSALLES_USERS SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

            // Redirect based on role
            header('Location: ' . ($user['role'] == 1 ? 'admin-dashboard.php' : 'staff-dashboard.php'));
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
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

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username or Staff ID</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       placeholder="e.g., admin or EMP-2025-001" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
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
