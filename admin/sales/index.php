<?php
// admin/sales/index.php
$page_title = "Sales History";
require_once '../includes/header.php';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$staff_id = $_GET['staff_id'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';

// Get all staff for filter
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS WHERE role = 2 ORDER BY username");
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue if table doesn't exist
}

// Build query for sales
$query = "SELECT s.*, u.username, u.full_name 
          FROM EASYSALLES_SALES s 
          LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id 
          WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if (!empty($staff_id)) {
    $query .= " AND s.staff_id = ?";
    $params[] = $staff_id;
}

if (!empty($payment_method)) {
    $query .= " AND s.payment_method = ?";
    $params[] = $payment_method;
}

if (!empty($payment_status)) {
    $query .= " AND s.payment_status = ?";
    $params[] = $payment_status;
}

$query .= " ORDER BY s.sale_date DESC";

// Get sales
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
} catch (PDOException $e) {
    $sales = [];
}

// Get total stats
$total_sales = count($sales);
$total_revenue = array_sum(array_column($sales, 'final_amount'));
$total_transactions = count($sales);

// Calculate daily averages
$date_range_days = max(1, (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1);
$daily_average = $total_revenue / $date_range_days;
?>

<style>
    .sales-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .page-title h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 0.5rem 0;
    }
    
    .page-title p {
        color: var(--text-muted);
        margin: 0;
    }
    
    .page-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text);
        border: 2px solid var(--border);
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Filters */
    .filters-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
    }
    
    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .filters-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-group {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.9rem;
    }
    
    .filter-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--input-bg);
        color: var(--text);
    }
    
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin: 0.25rem 0 0 0;
    }
    
    /* Sales Cards (Grid View) */
    .sales-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .sales-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .view-toggle {
        display: flex;
        gap: 0.5rem;
        background: var(--card-bg);
        border-radius: 8px;
        border: 1px solid var(--border);
        padding: 0.25rem;
    }
    
    .view-btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        border: none;
        background: none;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .view-btn.active {
        background: var(--primary);
        color: white;
    }
    
    .sales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .sales-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .sale-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .sale-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .sale-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(to bottom, var(--primary), var(--secondary));
    }
    
    .sale-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .sale-id {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .sale-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .status-paid {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #F59E0B;
    }
    
    .status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
    }
    
    .sale-details {
        margin-bottom: 1rem;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border-light);
    }
    
    .detail-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .detail-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    
    .detail-value {
        font-size: 0.95rem;
        color: var(--text);
        font-weight: 600;
        text-align: right;
    }
    
    .customer-info {
        background: rgba(124, 58, 237, 0.05);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 3px solid var(--primary);
    }
    
    .customer-name {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
    }
    
    .customer-phone {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    .payment-method {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .method-cash {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .method-card {
        background: rgba(59, 130, 246, 0.1);
        color: #3B82F6;
    }
    
    .method-mobile-money {
        background: rgba(139, 92, 246, 0.1);
        color: #8B5CF6;
    }
    
    .method-credit {
        background: rgba(245, 158, 11, 0.1);
        color: #F59E0B;
    }
    
    .sale-amount {
        text-align: center;
        margin: 1rem 0;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(16, 185, 129, 0.02));
        border-radius: 8px;
        border: 2px solid rgba(16, 185, 129, 0.1);
    }
    
    .amount-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }
    
    .amount-value {
        font-family: 'Poppins', sans-serif;
        font-size: 1.8rem;
        font-weight: 700;
        color: #10B981;
    }
    
    .sale-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .action-btn {
        padding: 0.75rem;
        border-radius: 8px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .action-view {
        background: rgba(59, 130, 246, 0.1);
        color: #3B82F6;
        border-color: rgba(59, 130, 246, 0.2);
    }
    
    .action-view:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: #3B82F6;
        transform: translateY(-2px);
    }
    
    .action-receipt {
        background: rgba(124, 58, 237, 0.1);
        color: #7C3AED;
        border-color: rgba(124, 58, 237, 0.2);
    }
    
    .action-receipt:hover {
        background: rgba(124, 58, 237, 0.2);
        border-color: #7C3AED;
        transform: translateY(-2px);
    }
    
    /* Table View (Hidden by default) */
    #tableView {
        display: none;
        margin-bottom: 2rem;
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--card-bg);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .table th {
        background: var(--table-header-bg);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text);
        border-bottom: 2px solid var(--border);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-light);
        color: var(--text);
        vertical-align: middle;
    }
    
    .table tr:hover {
        background: var(--table-hover-bg);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border-radius: 12px;
        border: 2px dashed var(--border);
    }
    
    .empty-state i {
        font-size: 3.5rem;
        color: var(--border);
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Date Range Display */
    .date-range-display {
        background: rgba(124, 58, 237, 0.05);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        text-align: center;
        border: 1px solid rgba(124, 58, 237, 0.1);
    }
    
    .date-range-text {
        font-size: 0.95rem;
        color: var(--text);
        font-weight: 500;
    }
    
    .date-range-dates {
        font-weight: 600;
        color: var(--primary);
    }
</style>

<div class="sales-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h2>📊 Sales History</h2>
            <p>View all sales transactions with detailed information</p>
        </div>
        <div class="page-actions">
            <a href="reports.php" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> Sales Reports
            </a>
        </div>
    </div>

    <!-- Date Range Display -->
    <div class="date-range-display">
        <span class="date-range-text">
            Showing sales from <span class="date-range-dates">
                <?php echo date('M d, Y', strtotime($date_from)); ?>
            </span> to <span class="date-range-dates">
                <?php echo date('M d, Y', strtotime($date_to)); ?>
            </span>
            (<?php echo $date_range_days; ?> days)
        </span>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Filter Sales
            </div>
            <a href="index.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
        
        <form method="GET" action="" id="salesFilter">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           class="filter-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           class="filter-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Staff Member</label>
                    <select name="staff_id" class="filter-control">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['user_id']; ?>" 
                                <?php echo $staff_id == $staff['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Payment Method</label>
                    <select name="payment_method" class="filter-control">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="mobile_money" <?php echo $payment_method == 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="credit" <?php echo $payment_method == 'credit' ? 'selected' : ''; ?>>Credit</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Payment Status</label>
                    <select name="payment_status" class="filter-control">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="cancelled" <?php echo $payment_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px;">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo $total_transactions; ?></h3>
                <p class="stat-label">Total Transactions</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">$<?php echo number_format($total_revenue, 2); ?></h3>
                <p class="stat-label">Total Revenue</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">$<?php echo number_format($daily_average, 2); ?></h3>
                <p class="stat-label">Daily Average</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo count($staff_members); ?></h3>
                <p class="stat-label">Active Staff</p>
            </div>
        </div>
    </div>

    <!-- Sales Content -->
    <div class="sales-header">
        <div class="sales-title">
            <i class="fas fa-shopping-cart"></i>
            Sales Transactions (<?php echo $total_sales; ?>)
        </div>
        
        <div class="view-toggle">
            <button class="view-btn active" onclick="setViewMode('grid')">
                <i class="fas fa-th-large"></i> Grid
            </button>
            <button class="view-btn" onclick="setViewMode('list')">
                <i class="fas fa-list"></i> Table
            </button>
        </div>
    </div>

    <!-- Grid View -->
    <div id="gridView" class="sales-grid">
        <?php if (empty($sales)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-shopping-cart"></i>
                <h3>No Sales Found</h3>
                <p>No sales transactions match your filter criteria. Try adjusting your filters or check back later.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Clear Filters
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($sales as $sale): 
                // Get item count for this sale
                $item_count = 0;
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM EASYSALLES_SALE_ITEMS WHERE sale_id = ?");
                    $stmt->execute([$sale['sale_id']]);
                    $item_count = $stmt->fetch()['count'];
                } catch (PDOException $e) {
                    // Table might not exist
                }
                
                // Status class
                $status_class = 'status-' . $sale['payment_status'];
                
                // Payment method class
                $method_class = 'method-' . str_replace('_', '-', $sale['payment_method']);
            ?>
                <div class="sale-card">
                    <!-- Sale Header -->
                    <div class="sale-header">
                        <div class="sale-id"><?php echo htmlspecialchars($sale['transaction_code']); ?></div>
                        <div class="sale-status <?php echo $status_class; ?>">
                            <?php echo ucfirst($sale['payment_status']); ?>
                        </div>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="customer-info">
                        <div class="customer-name"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                        <?php if ($sale['customer_phone']): ?>
                            <div class="customer-phone"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sale Details -->
                    <div class="sale-details">
                        <div class="detail-row">
                            <div class="detail-label">Date & Time</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                <small style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value">
                                <span class="payment-method <?php echo $method_class; ?>">
                                    <i class="fas fa-<?php echo $sale['payment_method'] == 'cash' ? 'money-bill' : ($sale['payment_method'] == 'card' ? 'credit-card' : ($sale['payment_method'] == 'mobile_money' ? 'mobile-alt' : 'hand-holding-usd')); ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Items Sold</div>
                            <div class="detail-value">
                                <span style="font-weight: 700; color: var(--primary);"><?php echo $item_count; ?> items</span>
                            </div>
                        </div>
                        
                        <?php if ($sale['discount_amount'] > 0): ?>
                        <div class="detail-row">
                            <div class="detail-label">Discount Applied</div>
                            <div class="detail-value" style="color: var(--success);">
                                $<?php echo number_format($sale['discount_amount'], 2); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sale Amount -->
                    <div class="sale-amount">
                        <div class="amount-label">Total Amount</div>
                        <div class="amount-value">$<?php echo number_format($sale['final_amount'], 2); ?></div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="sale-actions">
                        <a href="view.php?id=<?php echo $sale['sale_id']; ?>" 
                           class="action-btn action-view"
                           title="View Sale Details">
                            <i class="fas fa-eye"></i> View Details
                        </a>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Table View (Hidden by default) -->
    <div id="tableView">
        <?php if (empty($sales)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>No Sales Found</h3>
                <p>No sales transactions match your filter criteria. Try adjusting your filters or check back later.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Clear Filters
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table" id="salesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Staff</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): 
                            // Get item count for this sale
                            $item_count = 0;
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM EASYSALLES_SALE_ITEMS WHERE sale_id = ?");
                                $stmt->execute([$sale['sale_id']]);
                                $item_count = $stmt->fetch()['count'];
                            } catch (PDOException $e) {
                                // Table might not exist
                            }
                            
                            // Status class
                            $status_class = 'status-' . $sale['payment_status'];
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary);"><?php echo htmlspecialchars($sale['transaction_code']); ?></strong>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></small>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                <?php if ($sale['customer_phone']): ?>
                                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 0.25rem 0.75rem; background: rgba(124, 58, 237, 0.1); color: #7C3AED; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo $item_count; ?> items
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #10B981; font-size: 1.1rem;">
                                    $<?php echo number_format($sale['final_amount'], 2); ?>
                                </div>
                                <?php if ($sale['discount_amount'] > 0): ?>
                                    <small style="color: var(--text-muted);">
                                        Discount: $<?php echo number_format($sale['discount_amount'], 2); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $method_icons = [
                                    'cash' => 'money-bill',
                                    'card' => 'credit-card',
                                    'mobile_money' => 'mobile-alt',
                                    'credit' => 'hand-holding-usd'
                                ];
                                $method_colors = [
                                    'cash' => '#10B981',
                                    'card' => '#3B82F6',
                                    'mobile_money' => '#8B5CF6',
                                    'credit' => '#F59E0B'
                                ];
                                ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.5rem; color: <?php echo $method_colors[$sale['payment_method']] ?? 'var(--text)'; ?>;">
                                    <i class="fas fa-<?php echo $method_icons[$sale['payment_method']] ?? 'money-bill'; ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="sale-status <?php echo $status_class; ?>" style="font-size: 0.85rem;">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="view.php?id=<?php echo $sale['sale_id']; ?>" 
                                       class="action-btn action-view" 
                                       style="padding: 0.5rem; width: 40px; height: 40px;"
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Export sales to CSV
    function exportSales() {
        const table = document.getElementById('salesTable');
        if (!table) {
            showToast('No sales data to export', 'error');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            for (const col of cols) {
                // Remove buttons from actions column
                if (col.querySelector('.action-btn')) {
                    rowData.push('');
                } else {
                    rowData.push(col.innerText.replace(/,/g, ''));
                }
            }
            
            csv.push(rowData.join(','));
        }
        
        // Create download link
        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sales_history_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Sales data exported successfully', 'success');
    }
    
    // Print sales
    function printSales() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Sales History - EasySalles</title>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                    body { 
                        font-family: 'Inter', Arial, sans-serif; 
                        margin: 40px; 
                        color: #2d3748;
                        line-height: 1.6;
                    }
                    .header { 
                        text-align: center; 
                        margin-bottom: 30px; 
                        padding-bottom: 20px;
                        border-bottom: 2px solid #e2e8f0;
                    }
                    h1 { 
                        color: #2d3748; 
                        font-size: 28px;
                        font-weight: 700;
                        margin: 0;
                    }
                    .subtitle { 
                        color: #718096; 
                        margin: 5px 0 20px 0;
                    }
                    .meta { 
                        color: #a0aec0; 
                        font-size: 14px;
                        margin-bottom: 30px;
                    }
                    .stats { 
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 1rem;
                        margin-bottom: 30px;
                    }
                    .stat-box { 
                        padding: 15px;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        text-align: center;
                    }
                    .stat-value { 
                        font-size: 1.5rem;
                        font-weight: 700;
                        color: #2d3748;
                        margin: 0;
                    }
                    .stat-label { 
                        font-size: 0.9rem;
                        color: #718096;
                        margin: 5px 0 0 0;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin-top: 20px;
                        font-size: 14px;
                    }
                    th { 
                        background: #f7fafc; 
                        padding: 12px 15px;
                        text-align: left;
                        font-weight: 600;
                        color: #4a5568;
                        border-bottom: 2px solid #e2e8f0;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }
                    td { 
                        padding: 12px 15px;
                        border-bottom: 1px solid #edf2f7;
                    }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                    .footer {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 2px solid #e2e8f0;
                        text-align: center;
                        color: #718096;
                        font-size: 14px;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>EasySalles - Sales History</h1>
                    <div class="subtitle">Comprehensive sales transaction report</div>
                    <div class="meta">
                        Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?><br>
                        Generated on: ${new Date().toLocaleString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                    </div>
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $total_transactions; ?></div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">$<?php echo number_format($daily_average, 2); ?></div>
                        <div class="stat-label">Daily Average</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo count($staff_members); ?></div>
                        <div class="stat-label">Active Staff</div>
                    </div>
                </div>
                
                <h2>Sales Transactions (<?php echo count($sales); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Staff</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['transaction_code']); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></td>
                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($sale['full_name'] ?: $sale['username']); ?></td>
                            <td class="text-right">$<?php echo number_format($sale['final_amount'], 2); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></td>
                            <td><?php echo ucfirst($sale['payment_status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="footer">
                    <p>EasySalles Sales Management System</p>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
    
    // View mode toggle
    function setViewMode(mode) {
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');
        const gridBtn = document.querySelector('.view-btn:nth-child(1)');
        const listBtn = document.querySelector('.view-btn:nth-child(2)');
        
        if (mode === 'grid') {
            gridView.style.display = 'grid';
            tableView.style.display = 'none';
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        } else {
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            gridBtn.classList.remove('active');
            listBtn.classList.add('active');
        }
        
        // Save preference to localStorage
        localStorage.setItem('salesViewMode', mode);
    }
    
    // Load saved view mode
    document.addEventListener('DOMContentLoaded', function() {
        const savedMode = localStorage.getItem('salesViewMode') || 'grid';
        setViewMode(savedMode);
        
        // Set max dates for date inputs
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.max = today;
        });
    });
    
    // Auto-submit form on date change
    document.querySelectorAll('input[name="date_from"], input[name="date_to"]').forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('salesFilter').submit();
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>