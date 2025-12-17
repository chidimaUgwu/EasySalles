<?php
// sales-list.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();

$page_title = 'Sales History';
include 'includes/header.php';
require 'config/db.php';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';

// Build query
$sql = "SELECT s.*, u.full_name as staff_name 
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

// Add role-specific filter
if (isset($_SESSION['role']) && $_SESSION['role'] == 2) { // Staff
    $sql .= " AND s.staff_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($status) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $status;
}

if ($payment_method) {
    $sql .= " AND s.payment_method = ?";
    $params[] = $payment_method;
}

$sql .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get totals
$total_sql = "SELECT COUNT(*) as count, SUM(final_amount) as total 
              FROM EASYSALLES_SALES 
              WHERE DATE(sale_date) BETWEEN ? AND ?";
$total_params = [$date_from, $date_to];

if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
    $total_sql .= " AND staff_id = ?";
    $total_params[] = $_SESSION['user_id'];
}

$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($total_params);
$totals = $total_stmt->fetch();
?>

<style>
    .sales-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .page-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-summary-card {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .stat-summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.1);
    }
    
    .stat-summary-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }
    
    .stat-summary-label {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .filters-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .filters-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .filters-title i {
        color: var(--primary);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .filter-group {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
    }
    
    .filter-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .filter-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }
    
    .btn-secondary {
        background: var(--card-bg);
        color: var(--text);
        border: 2px solid var(--border);
    }
    
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .sales-table-container {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        overflow-x: auto;
    }
    
    .sales-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .sales-table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--text);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    
    .sales-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }
    
    .sales-table tbody tr {
        transition: all 0.3s ease;
    }
    
    .sales-table tbody tr:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .transaction-code {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text);
    }
    
    .customer-info h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .customer-info .phone {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .amount {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-paid {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: #10B981;
    }
    
    .status-pending {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
        color: #F59E0B;
    }
    
    .status-cancelled {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
    }
    
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        color: var(--primary);
    }
    
    .action-btns {
        display: flex;
        gap: 0.5rem;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }
    
    .view-btn {
        background: linear-gradient(135deg, #3B82F6, #2563EB);
    }
    
    .view-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }
    
    .print-btn {
        background: linear-gradient(135deg, #10B981, #059669);
    }
    
    .print-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: var(--border);
        margin-bottom: 1.5rem;
    }
    
    .empty-state h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state p {
        color: #64748b;
        margin-bottom: 1.5rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    
    .pagination-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--border);
        background: var(--card-bg);
        color: var(--text);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .pagination-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .pagination-btn.active {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border-color: transparent;
    }
    
    @media (max-width: 768px) {
        .sales-table {
            min-width: 800px;
        }
        
        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="sales-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-list"></i> Sales History
        </h1>
    </div>
    
    <!-- Stats Summary -->
    <div class="stats-summary">
        <div class="stat-summary-card">
            <div class="stat-summary-value"><?php echo $totals['count'] ?? 0; ?></div>
            <div class="stat-summary-label">Total Sales</div>
        </div>
        
        <div class="stat-summary-card">
            <div class="stat-summary-value">$<?php echo number_format($totals['total'] ?? 0, 2); ?></div>
            <div class="stat-summary-label">Total Revenue</div>
        </div>
        
        <div class="stat-summary-card">
            <div class="stat-summary-value">
                <?php echo $totals['count'] > 0 ? '$' . number_format($totals['total'] / $totals['count'], 2) : '$0.00'; ?>
            </div>
            <div class="stat-summary-label">Average Sale</div>
        </div>
        
        <div class="stat-summary-card">
            <div class="stat-summary-value">
                <?php 
                $today = date('Y-m-d');
                $today_sql = "SELECT COUNT(*) FROM EASYSALLES_SALES WHERE DATE(sale_date) = ?";
                $today_params = [$today];
                if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
                    $today_sql .= " AND staff_id = ?";
                    $today_params[] = $_SESSION['user_id'];
                }
                $today_stmt = $pdo->prepare($today_sql);
                $today_stmt->execute($today_params);
                echo $today_stmt->fetchColumn();
                ?>
            </div>
            <div class="stat-summary-label">Today's Sales</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <div class="filters-title">
            <i class="fas fa-filter"></i>
            <span>Filter Sales</span>
        </div>
        
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="date_from" class="filter-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="date_to" class="filter-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Payment Status</label>
                    <select name="status" class="filter-control">
                        <option value="">All Statuses</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Payment Method</label>
                    <select name="payment_method" class="filter-control">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="mobile_money" <?php echo $payment_method === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="credit" <?php echo $payment_method === 'credit' ? 'selected' : ''; ?>>Credit</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                
                <a href="sales-list.php" class="filter-btn btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
    
    <!-- Sales Table -->
    <div class="sales-table-container">
        <?php if (empty($sales)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No Sales Found</h3>
                <p>No sales records match your filters. Try adjusting your search criteria.</p>
                <a href="sale-record.php" class="filter-btn btn-primary" style="text-decoration: none;">
                    <i class="fas fa-cash-register"></i> Record Your First Sale
                </a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Transaction Code</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
                                <th>Staff</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    <div class="transaction-code"><?php echo htmlspecialchars($sale['transaction_code']); ?></div>
                                </td>
                                <td>
                                    <div class="customer-info">
                                        <h4><?php echo htmlspecialchars($sale['customer_name']); ?></h4>
                                        <?php if ($sale['customer_phone']): ?>
                                            <div class="phone"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($sale['sale_date'])); ?><br>
                                    <small style="color: #64748b;"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></small>
                                </td>
                                <td class="amount">$<?php echo number_format($sale['final_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $sale['payment_status']; ?>">
                                        <?php echo ucfirst($sale['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-method-badge">
                                        <i class="fas fa-<?php echo $sale['payment_method'] === 'cash' ? 'money-bill-wave' : ($sale['payment_method'] === 'card' ? 'credit-card' : ($sale['payment_method'] === 'mobile_money' ? 'mobile-alt' : 'handshake')); ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
                                    </span>
                                </td>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
                                    <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'Unknown'); ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view-btn" title="View Details" onclick="viewSale(<?php echo $sale['sale_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn print-btn" title="Print Receipt" onclick="printReceipt(<?php echo $sale['sale_id']; ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="pagination-btn"><i class="fas fa-chevron-left"></i></button>
                <button class="pagination-btn active">1</button>
                <button class="pagination-btn">2</button>
                <button class="pagination-btn">3</button>
                <button class="pagination-btn"><i class="fas fa-chevron-right"></i></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // View sale details
    function viewSale(saleId) {
        // In a real app, you would show a modal or redirect to a details page
        window.location.href = `sale-details.php?id=${saleId}`;
    }
    
    // Print receipt
    function printReceipt(saleId) {
        const printWindow = window.open(`print-receipt.php?id=${saleId}`, '_blank');
        printWindow.focus();
    }
    
    // Export to CSV
    function exportToCSV() {
        // Get table data and convert to CSV
        const table = document.querySelector('.sales-table');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('th, td');
            
            cells.forEach(cell => {
                // Remove action buttons from export
                if (!cell.closest('.action-btns')) {
                    let text = cell.textContent.trim();
                    // Remove extra whitespace and line breaks
                    text = text.replace(/\s+/g, ' ').replace(/\n/g, ' ');
                    // Wrap in quotes if contains comma
                    if (text.includes(',')) {
                        text = `"${text}"`;
                    }
                    rowData.push(text);
                }
            });
            
            if (rowData.length > 0) {
                csv.push(rowData.join(','));
            }
        });
        
        // Download CSV
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `sales-export-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    
    // Initialize date pickers
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
            .toISOString().split('T')[0];
        
        // Set max date for date_to
        document.querySelector('input[name="date_to"]').max = today;
        document.querySelector('input[name="date_from"]').max = today;
        
        // Add export button
        const pageHeader = document.querySelector('.page-header');
        if (pageHeader) {
            const exportBtn = document.createElement('button');
            exportBtn.className = 'filter-btn btn-secondary';
            exportBtn.innerHTML = '<i class="fas fa-file-export"></i> Export CSV';
            exportBtn.onclick = exportToCSV;
            exportBtn.style.marginLeft = 'auto';
            pageHeader.appendChild(exportBtn);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>