<?php
// admin/users/index.php
$page_title = "Manage Staff";
require_once '../includes/header.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$query = "SELECT * FROM EASYSALLES_USERS WHERE role = 2";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($status) && in_array($status, ['active', 'inactive', 'suspended'])) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY user_id DESC";

// Get users
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2>Manage Staff</h2>
        <p>View and manage all sales staff accounts</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New Staff
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Filters</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-4">
                <div class="form-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search by name, username, or email"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
            </div>
            <div class="col-2">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
            <div class="col-2">
                <a href="index.php" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Staff Members (<?php echo count($users); ?>)</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Member</th>
                    <th>Contact</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem;">
                            <i class="fas fa-users" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                            <p>No staff members found</p>
                            <a href="add.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-user-plus"></i> Add Your First Staff Member
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?php echo $user['user_id']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="user-avatar" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?: 'Not set'); ?></small><br>
                                <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></small>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['shift_start'] && $user['shift_end']): ?>
                                <small><?php echo date('h:i A', strtotime($user['shift_start'])); ?> - <?php echo date('h:i A', strtotime($user['shift_end'])); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($user['shift_days'] ?: 'Daily'); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $badge_class = 'badge-success';
                            if ($user['status'] == 'inactive') $badge_class = 'badge-warning';
                            if ($user['status'] == 'suspended') $badge_class = 'badge-error';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="view.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmAction('Are you sure you want to delete this user?', () => {
                                    window.location.href='delete.php?id=<?php echo $user['user_id']; ?>'
                                })" class="btn btn-outline" style="padding: 0.4rem 0.8rem; color: var(--error);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
