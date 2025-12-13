<?php
// admin/users/view.php
$page_title = "Staff Details";
require_once '../includes/header.php';

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    header('Location: index.php');
    exit();
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_USERS WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get user's recent sales
$recent_sales = [];
try {
    $stmt = $pdo->prepare("SELECT s.* FROM EASYSALLES_SALES s 
                          WHERE s.staff_id = ? 
                          ORDER BY s.sale_date DESC 
                          LIMIT 10");
    $stmt->execute([$user_id]);
    $recent_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get total sales count and amount
$sales_stats = ['count' => 0, 'amount' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(final_amount) as amount 
                          FROM EASYSALLES_SALES 
                          WHERE staff_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    $sales_stats = [
        'count' => $stats['count'] ?? 0,
        'amount' => $stats['amount'] ?? 0
    ];
} catch (PDOException $e) {
    // Continue if table doesn't exist
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Staff Details</h2>
        <p>Complete profile and performance overview</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Staff List
        </a>
        <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary" style="margin-left: 0.5rem;">
            <i class="fas fa-edit"></i> Edit Profile
        </a>
    </div>
</div>

<div class="row">
    <div class="col-4">
        <!-- Profile Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="padding: 2rem; text-align: center;">
                <div class="user-avatar" style="width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); font-size: 3rem; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.3);">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h3>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                
                <?php 
                $status_badge = 'badge-success';
                if ($user['status'] == 'inactive') $status_badge = 'badge-warning';
                if ($user['status'] == 'suspended') $status_badge = 'badge-error';
                ?>
                <span class="badge <?php echo $status_badge; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                    <?php echo ucfirst($user['status']); ?>
                </span>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-address-card"></i> Contact Information
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-envelope" style="color: var(--primary); width: 20px;"></i>
                        <strong>Email:</strong><br>
                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--text);">
                            <?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>
                        </a>
                    </p>
                    
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-phone" style="color: var(--primary); width: 20px;"></i>
                        <strong>Phone:</strong><br>
                        <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>
                    </p>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px;">
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-id-card" style="color: var(--accent); width: 20px;"></i>
                        <strong>Staff ID:</strong> #<?php echo $user['user_id']; ?>
                    </p>
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-calendar-alt" style="color: var(--accent); width: 20px;"></i>
                        <strong>Joined:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                    </p>
                    <?php if ($user['last_login']): ?>
                        <p style="margin: 0.5rem 0;">
                            <i class="fas fa-sign-in-alt" style="color: var(--accent); width: 20px;"></i>
                            <strong>Last Login:</strong> <?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-8">
        <!-- Stats Cards -->
        <div class="row" style="margin-bottom: 1.5rem;">
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $user['shift_start'] && $user['shift_end'] ? 'Assigned' : 'Not Set'; ?></h3>
                        <p>Shift Status</p>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $sales_stats['count']; ?></h3>
                        <p>Total Sales</p>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>$<?php echo number_format($sales_stats['amount'], 2); ?></h3>
                        <p>Revenue Generated</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Shift Information -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i> Shift Schedule
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if ($user['shift_start'] && $user['shift_end']): ?>
                    <div class="row">
                        <div class="col-4">
                            <div style="text-align: center; padding: 1rem; background: var(--primary-light); border-radius: 10px;">
                                <h4 style="color: var(--primary); margin-bottom: 0.5rem;">Start Time</h4>
                                <p style="font-size: 1.5rem; font-weight: 600;">
                                    <?php echo date('h:i A', strtotime($user['shift_start'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="text-align: center; padding: 1rem; background: var(--accent-light); border-radius: 10px;">
                                <h4 style="color: var(--accent); margin-bottom: 0.5rem;">End Time</h4>
                                <p style="font-size: 1.5rem; font-weight: 600;">
                                    <?php echo date('h:i A', strtotime($user['shift_end'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="text-align: center; padding: 1rem; background: var(--secondary-light); border-radius: 10px;">
                                <h4 style="color: var(--secondary); margin-bottom: 0.5rem;">Working Days</h4>
                                <p style="font-size: 1.5rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($user['shift_days'] ?: 'Daily'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['salary']): ?>
                        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--success-light); border-radius: 10px;">
                            <h4 style="color: var(--success); margin-bottom: 0.5rem;">
                                <i class="fas fa-money-bill"></i> Salary Information
                            </h4>
                            <p style="margin: 0; font-size: 1.2rem;">
                                <strong>Monthly Salary:</strong> $<?php echo number_format($user['salary'], 2); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-clock" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--warning);">No Shift Assigned</h4>
                        <p>This staff member doesn't have a shift schedule assigned yet.</p>
                        <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Assign Shift Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i> Recent Sales
                </h3>
            </div>
            <div class="table-container">
                <?php if (!empty($recent_sales)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['transaction_code']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></td>
                                <td>$<?php echo number_format($sale['final_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $method = ucfirst(str_replace('_', ' ', $sale['payment_method']));
                                    echo $method;
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_badge = 'badge-success';
                                    if ($sale['payment_status'] == 'pending') $status_badge = 'badge-warning';
                                    if ($sale['payment_status'] == 'cancelled') $status_badge = 'badge-error';
                                    ?>
                                    <span class="badge <?php echo $status_badge; ?>">
                                        <?php echo ucfirst($sale['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem;">
                        <i class="fas fa-receipt" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4>No Sales Recorded</h4>
                        <p>This staff member hasn't made any sales yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notes Section -->
        <?php if (!empty($user['notes'])): ?>
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sticky-note"></i> Notes
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <p><?php echo nl2br(htmlspecialchars($user['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
