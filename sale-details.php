<?php
// sale-details.php - FIXED VERSION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();
require_staff();

$page_title = 'Sale Details';
include 'includes/header.php';
require 'config/db.php';

// Get sale ID from URL
$sale_id = $_GET['id'] ?? 0;
$sale_id = (int)$sale_id;

if (!$sale_id) {
    header('Location: sales-list.php');
    exit();
}

// Get sale details
$sql = "SELECT s.*, u.full_name as staff_name, u.username as staff_username
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE s.sale_id = ?";
        
// Add security check for staff
if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
    $sql .= " AND s.staff_id = ?";
    $params = [$sale_id, $_SESSION['user_id']];
} else {
    $params = [$sale_id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sale = $stmt->fetch();

if (!$sale) {
    echo '<div class="alert alert-error">Sale not found or you don\'t have permission to view it.</div>';
    include 'includes/footer.php';
    exit();
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ?
              ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Handle form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $customer_name = $_POST['customer_name'] ?? $sale['customer_name'];
        $customer_phone = $_POST['customer_phone'] ?? $sale['customer_phone'];
        $customer_email = $_POST['customer_email'] ?? $sale['customer_email'];
        $payment_method = $_POST['payment_method'] ?? $sale['payment_method'];
        $payment_status = $_POST['payment_status'] ?? $sale['payment_status'];
        $notes = $_POST['notes'] ?? $sale['notes'];
        
        // For non-admin users, use original amounts
        if (isset($_SESSION['role']) && $_SESSION['role'] == 1) {
            // Admin can edit amounts
            $subtotal_amount = floatval($_POST['subtotal_amount'] ?? $sale['subtotal_amount']);
            $discount_amount = floatval($_POST['discount_amount'] ?? $sale['discount_amount']);
            $tax_amount = floatval($_POST['tax_amount'] ?? $sale['tax_amount']);
            $final_amount = floatval($_POST['final_amount'] ?? $sale['final_amount']);
            $total_amount = $final_amount; // Set total_amount to final_amount
        } else {
            // Staff cannot edit amounts
            $subtotal_amount = $sale['subtotal_amount'];
            $discount_amount = $sale['discount_amount'];
            $tax_amount = $sale['tax_amount'];
            $final_amount = $sale['final_amount'];
            $total_amount = $sale['total_amount'];
        }
        
        $update_sql = "UPDATE EASYSALLES_SALES SET 
                      customer_name = ?,
                      customer_phone = ?,
                      customer_email = ?,
                      payment_method = ?,
                      payment_status = ?,
                      notes = ?,
                      subtotal_amount = ?,
                      discount_amount = ?,
                      tax_amount = ?,
                      total_amount = ?,
                      final_amount = ?
                      WHERE sale_id = ?";
        
        $update_params = [
            $customer_name,
            $customer_phone,
            $customer_email,
            $payment_method,
            $payment_status,
            $notes,
            $subtotal_amount,
            $discount_amount,
            $tax_amount,
            $total_amount, // Added this line
            $final_amount,
            $sale_id
        ];
        
        // Add security check for staff
        if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
            $update_sql .= " AND staff_id = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute($update_params);
        
        if ($result) {
            $success = "Sale updated successfully!";
            
            // Refresh sale data
            $stmt->execute($params);
            $sale = $stmt->fetch();
        } else {
            $error = "Failed to update sale. Please try again.";
        }
        
    } catch (Exception $e) {
        $error = "Failed to update sale: " . $e->getMessage();
        error_log("Sale update error: " . $e->getMessage());
    }
}
?>

<style>
    /* Keep your CSS styles from the earlier version - they're simpler and work better */
    .sale-details-container {
        max-width: 1200px;
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
    
    .header-actions {
        display: flex;
        gap: 1rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
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
    
    .sale-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 1024px) {
        .sale-details-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .detail-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .card-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .card-title i {
        color: var(--primary);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .info-item {
        margin-bottom: 1rem;
    }
    
    .info-label {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }
    
    .info-value {
        font-weight: 600;
        color: var(--text);
        font-size: 1.1rem;
    }
    
    .amount-display {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.9rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: #10B981;
        border-left-color: #10B981;
    }
    
    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border-left-color: #EF4444;
    }
    
    .items-table-container {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        margin-bottom: 2rem;
        overflow-x: auto;
    }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .items-table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--text);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    
    .items-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }
    
    .items-table tbody tr:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .product-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .product-code {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--border);
    }
    
    .edit-form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    
    .cancel-btn {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border: 2px solid #EF4444;
    }
    
    .cancel-btn:hover {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
</style>

<div class="sale-details-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-receipt"></i> Sale Details
        </h1>
        
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <button class="btn btn-primary" onclick="window.open('print-receipt.php?id=<?php echo $sale_id; ?>', '_blank')">
                <i class="fas fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- SIMPLIFIED FORM - Works like the earlier version -->
    <form method="POST" action="" id="saleForm">
        <div class="sale-details-grid">
            <!-- Customer Information -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-user"></i>
                    <span>Customer Information</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Customer Name *</label>
                    <input type="text" name="customer_name" class="form-control" 
                           value="<?php echo htmlspecialchars($sale['customer_name']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="customer_phone" class="form-control" 
                               value="<?php echo htmlspecialchars($sale['customer_phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="customer_email" class="form-control" 
                               value="<?php echo htmlspecialchars($sale['customer_email']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Sale Information -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Sale Information</span>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Transaction Code</div>
                        <div class="info-value"><?php echo htmlspecialchars($sale['transaction_code']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Date & Time</div>
                        <div class="info-value">
                            <?php echo date('M j, Y h:i A', strtotime($sale['sale_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Staff</div>
                        <div class="info-value"><?php echo htmlspecialchars($sale['staff_name'] ?? $sale['staff_username']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Payment Status</div>
                        <div class="info-value">
                            <select name="payment_status" class="form-control">
                                <option value="paid" <?php echo $sale['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $sale['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="cancelled" <?php echo $sale['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Payment Method</div>
                        <div class="info-value">
                            <select name="payment_method" class="form-control">
                                <option value="cash" <?php echo $sale['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="card" <?php echo $sale['payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                                <option value="mobile_money" <?php echo $sale['payment_method'] === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="credit" <?php echo $sale['payment_method'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
                    <!-- Admin can edit amounts -->
                    <div class="card-title" style="margin-top: 2rem;">
                        <i class="fas fa-calculator"></i>
                        <span>Amounts (Admin Only)</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Subtotal</label>
                            <input type="number" name="subtotal_amount" class="form-control" step="0.01" min="0"
                                   value="<?php echo number_format($sale['subtotal_amount'], 2); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Discount</label>
                            <input type="number" name="discount_amount" class="form-control" step="0.01" min="0"
                                   value="<?php echo number_format($sale['discount_amount'], 2); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tax</label>
                            <input type="number" name="tax_amount" class="form-control" step="0.01" min="0"
                                   value="<?php echo number_format($sale['tax_amount'], 2); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Final Amount</label>
                            <input type="number" name="final_amount" class="form-control" step="0.01" min="0"
                                   value="<?php echo number_format($sale['final_amount'], 2); ?>">
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Staff can only view amounts -->
                    <div class="card-title" style="margin-top: 2rem;">
                        <i class="fas fa-calculator"></i>
                        <span>Amount Summary</span>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Subtotal</div>
                            <div class="amount-display">$<?php echo number_format($sale['subtotal_amount'], 2); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Discount</div>
                            <div class="amount-display">$<?php echo number_format($sale['discount_amount'], 2); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Tax</div>
                            <div class="amount-display">$<?php echo number_format($sale['tax_amount'], 2); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Final Amount</div>
                            <div class="amount-display">$<?php echo number_format($sale['final_amount'], 2); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sale Items (View Only) -->
        <div class="items-table-container">
            <div class="card-title">
                <i class="fas fa-box"></i>
                <span>Items Purchased</span>
            </div>
            
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No items found for this sale</p>
                </div>
            <?php else: ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="product-code"><?php echo htmlspecialchars($item['product_code']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit_type']); ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01)); font-weight: 600;">
                            <td colspan="4" style="text-align: right; border-top: 2px solid var(--border);">Total:</td>
                            <td style="border-top: 2px solid var(--border); color: var(--primary);">
                                $<?php echo number_format($sale['final_amount'], 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Form Actions -->
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary" name="save_changes">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            
            <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn cancel-btn">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
    // Simple form validation
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        const customerName = this.querySelector('input[name="customer_name"]');
        if (!customerName.value.trim()) {
            e.preventDefault();
            alert('Customer name is required!');
            customerName.focus();
            return false;
        }
        
        // Ask for confirmation
        if (!confirm('Are you sure you want to save changes?')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
</script>

<?php include 'includes/footer.php'; ?>