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
?>

<div class="page-header">
    <div class="page-title">
        <h2>Sales History</h2>
        <p>View and manage all sales transactions</p>
    </div>
    <div class="page-actions">
        <a href="reports.php" class="btn btn-primary">
            <i class="fas fa-chart-line"></i> Sales Reports
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Filter Sales</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-2">
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
            </div>
            
            <div class="col-2">
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
            </div>
            
            <div class="col-2">
                <div class="form-group">
                    <label class="form-label">Staff Member</label>
                    <select name="staff_id" class="form-control">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['user_id']; ?>" 
                                <?php echo $staff_id == $staff['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="col-2">
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="mobile_money" <?php echo $payment_method == 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="credit" <?php echo $payment_method == 'credit' ? 'selected' : ''; ?>>Credit</option>
                    </select>
                </div>
            </div>
            
            <div class="col-2">
                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="cancelled" <?php echo $payment_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="col-2" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sales Summary -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_transactions; ?></h3>
            <p>Total Transactions</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
            <p>Total Revenue</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count($staff_members); ?></h3>
            <p>Active Staff</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--secondary-light); color: var(--secondary);">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)); ?></h3>
            <p>Date Range</p>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Sales Transactions (<?php echo $total_sales; ?>)</h3>
        <div class="btn-group">
            <button onclick="exportSales()" class="btn btn-outline">
                <i class="fas fa-download"></i> Export
            </button>
            <button onclick="printSales()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <div class="table-container">
        <?php if (empty($sales)): ?>
            <div style="text-align: center; padding: 4rem;">
                <div style="width: 100px; height: 100px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--border);"></i>
                </div>
                <h3>No Sales Found</h3>
                <p class="text-muted">No sales transactions match your filters</p>
                <div style="margin-top: 1rem;">
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create First Sale
                    </a>
                    <a href="index.php" class="btn btn-outline" style="margin-left: 0.5rem;">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                </div>
            </div>
        <?php else: ?>
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
                        
                        // Status badge colors
                        $status_badge = 'badge-success';
                        if ($sale['payment_status'] == 'pending') $status_badge = 'badge-warning';
                        if ($sale['payment_status'] == 'cancelled') $status_badge = 'badge-error';
                        
                        // Payment method colors
                        $method_colors = [
                            'cash' => 'var(--success)',
                            'card' => 'var(--primary)',
                            'mobile_money' => 'var(--accent)',
                            'credit' => 'var(--warning)'
                        ];
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
                            <?php echo htmlspecialchars($sale['customer_name']); ?><br>
                            <?php if ($sale['customer_phone']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sale['full_name']): ?>
                                <?php echo htmlspecialchars($sale['full_name']); ?><br>
                                <small class="text-muted">@<?php echo htmlspecialchars($sale['username']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Unknown Staff</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?php echo $item_count; ?> items</span>
                        </td>
                        <td>
                            <strong style="color: var(--success);">$<?php echo number_format($sale['final_amount'], 2); ?></strong><br>
                            <small class="text-muted">
                                <?php if ($sale['discount_amount'] > 0): ?>
                                    Discount: $<?php echo number_format($sale['discount_amount'], 2); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span style="color: <?php echo $method_colors[$sale['payment_method']] ?? 'var(--text)'; ?>;">
                                <i class="fas fa-<?php echo $sale['payment_method'] == 'cash' ? 'money-bill' : ($sale['payment_method'] == 'card' ? 'credit-card' : 'mobile-alt'); ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $status_badge; ?>">
                                <?php echo ucfirst($sale['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="view.php?id=<?php echo $sale['sale_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $sale['sale_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="Edit Sale">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="receipt.php?id=<?php echo $sale['sale_id']; ?>" 
                                   target="_blank"
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="Print Receipt">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Summary Footer -->
            <div style="padding: 1rem; background: var(--bg); border-top: 1px solid var(--border);">
                <div class="row">
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Total Sales</small>
                        <h4 style="margin: 0;"><?php echo $total_sales; ?></h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Total Revenue</small>
                        <h4 style="margin: 0; color: var(--success);">$<?php echo number_format($total_revenue, 2); ?></h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Average Sale</small>
                        <h4 style="margin: 0; color: var(--primary);">
                            $<?php echo $total_sales > 0 ? number_format($total_revenue / $total_sales, 2) : '0.00'; ?>
                        </h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Date Range</small>
                        <h4 style="margin: 0; color: var(--accent);">
                            <?php echo date('M d', strtotime($date_from)) . ' - ' . date('M d', strtotime($date_to)); ?>
                        </h4>
                    </div>
                </div>
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
                if (col.querySelector('.btn')) {
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
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    .summary { display: flex; justify-content: space-between; margin: 20px 0; }
                    .summary-box { padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; width: 23%; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                </style>
            </head>
            <body>
                <h1>Sales History - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                <p>Date Range: <?php echo date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to)); ?></p>
                
                <div class="summary">
                    <div class="summary-box">
                        <h3><?php echo $total_transactions; ?></h3>
                        <p>Total Transactions</p>
                    </div>
                    <div class="summary-box">
                        <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="summary-box">
                        <h3>$<?php echo $total_sales > 0 ? number_format($total_revenue / $total_sales, 2) : '0.00'; ?></h3>
                        <p>Average Sale</p>
                    </div>
                    <div class="summary-box">
                        <h3><?php echo count($staff_members); ?></h3>
                        <p>Active Staff</p>
                    </div>
                </div>
                
                <h2>Sales Transactions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
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
                            <td>$<?php echo number_format($sale['final_amount'], 2); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></td>
                            <td><?php echo ucfirst($sale['payment_status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Auto-update date fields
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const firstOfMonth = today.substring(0, 8) + '01';
        
        document.querySelector('input[name="date_to"]').max = today;
        document.querySelector('input[name="date_from"]').max = today;
    });
</script>

<?php require_once '../includes/footer.php'; ?>
