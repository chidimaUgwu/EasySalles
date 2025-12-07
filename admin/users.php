<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $shift_start = $_POST['shift_start'];
        $shift_end = $_POST['shift_end'];
        $shift_days = implode(',', $_POST['shift_days'] ?? []);
        
        // Generate user ID
        $user_id = generateUserId();
        
        // Default password (user will change on first login)
        $temp_password = 'Welcome123';
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        
        try {
            Database::query(
                "INSERT INTO easysalles_users 
                (user_id, username, password_hash, full_name, email, role, shift_start, shift_end, shift_days, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$user_id, $username, $password_hash, $full_name, $email, $role, $shift_start, $shift_end, $shift_days, $_SESSION['user_id']]
            );
            
            $_SESSION['success'] = "User created successfully! User ID: $user_id";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating user: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = $_POST['user_id'];
        $is_active = $_POST['is_active'];
        
        Database::query(
            "UPDATE easysalles_users SET is_active = ? WHERE id = ?",
            [$is_active, $user_id]
        );
        
        $_SESSION['success'] = "User status updated!";
    }
}

// Get all users
$users = Database::query("SELECT * FROM easysalles_users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-layout">
    <!-- Include Sidebar -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div class="search-bar">
                <svg class="icon search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" placeholder="Search users...">
            </div>
            <?php include '../includes/user_menu.php'; ?>
        </header>
        
        <div class="content-area">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>User Management</h1>
                <button class="btn btn-primary" onclick="openModal('createUserModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Create New User
                </button>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>All Users</h2>
                    <div class="table-actions">
                        <select class="btn btn-secondary">
                            <option>All Roles</option>
                            <option>Admin</option>
                            <option>Salesperson</option>
                        </select>
                        <select class="btn btn-secondary">
                            <option>All Status</option>
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Shift Hours</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['user_id']); ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="avatar-circle <?php echo getAvatarColor($user['full_name']); ?>">
                                        <?php echo getInitials($user['full_name']); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">@<?php echo htmlspecialchars($user['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['role'] === 'admin' ? 'status-active' : 'status-pending'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['shift_start'] && $user['shift_end']): ?>
                                    <?php echo date('h:i A', strtotime($user['shift_start'])); ?> - 
                                    <?php echo date('h:i A', strtotime($user['shift_end'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--text-lighter);">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $user['last_login'] ? date('M d, h:i A', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? '0' : '1'; ?>">
                                    <button type="submit" name="toggle_status" class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="table-actions-cell">
                                <button class="btn btn-secondary btn-sm">Edit</button>
                                <button class="btn btn-danger btn-sm">Reset Pass</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Create User Modal -->
    <div class="modal" id="createUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New User</h3>
                <button class="modal-close" onclick="closeModal('createUserModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required placeholder="johndoe">
                    </div>
                    
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="john@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <option value="salesperson">Salesperson</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Shift Start *</label>
                            <input type="time" name="shift_start" required value="08:00">
                        </div>
                        
                        <div class="form-group">
                            <label>Shift End *</label>
                            <input type="time" name="shift_end" required value="17:00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Working Days *</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="1" checked> Mon
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="2" checked> Tue
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="3" checked> Wed
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="4" checked> Thu
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="5" checked> Fri
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="6"> Sat
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="shift_days[]" value="7"> Sun
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> User will receive a temporary password and must change it on first login.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
