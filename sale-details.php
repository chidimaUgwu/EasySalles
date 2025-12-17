<?php
// sale-details.php - COMPLETE UPDATED VERSION WITH ITEM EDITING
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
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type, p.current_stock
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ?
              ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Get all products for adding new items
$products_sql = "SELECT product_id, product_name, product_code, unit_price, current_stock, unit_type 
                 FROM EASYSALLES_PRODUCTS 
                 WHERE status = 'active' 
                 ORDER BY product_name";
$products = $pdo->query($products_sql)->fetchAll();

// Handle form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    
    try {
        // Save original sale items for logging
        $original_items = $items;
        
        // Update sale information
        $customer_name = $_POST['customer_name'] ?? $sale['customer_name'];
        $customer_phone = $_POST['customer_phone'] ?? $sale['customer_phone'];
        $customer_email = $_POST['customer_email'] ?? $sale['customer_email'];
        $payment_method = $_POST['payment_method'] ?? $sale['payment_method'];
        $payment_status = $_POST['payment_status'] ?? $sale['payment_status'];
        $notes = $_POST['notes'] ?? $sale['notes'];
        
        // Update sale items if provided
        $updated_items = [];
        $new_subtotal = 0;
        
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item_data) {
                $item_id = $item_data['item_id'] ?? 0;
                $product_id = $item_data['product_id'] ?? 0;
                $quantity = intval($item_data['quantity'] ?? 0);
                $unit_price = floatval($item_data['unit_price'] ?? 0);
                $action = $item_data['action'] ?? 'keep'; // keep, update, remove, add
                
                if ($action === 'remove' && $item_id > 0) {
                    // Remove item from sale
                    $remove_sql = "DELETE FROM EASYSALLES_SALE_ITEMS WHERE item_id = ?";
                    $remove_stmt = $pdo->prepare($remove_sql);
                    $remove_stmt->execute([$item_id]);
                    
                    // Get original item for stock restoration
                    $original_item = null;
                    foreach ($original_items as $oi) {
                        if ($oi['item_id'] == $item_id) {
                            $original_item = $oi;
                            break;
                        }
                    }
                    
                    if ($original_item) {
                        // Restore stock
                        $restore_sql = "UPDATE EASYSALLES_PRODUCTS 
                                       SET current_stock = current_stock + ? 
                                       WHERE product_id = ?";
                        $restore_stmt = $pdo->prepare($restore_sql);
                        $restore_stmt->execute([$original_item['quantity'], $original_item['product_id']]);
                        
                        // Log stock restoration
                        $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                                   (product_id, change_type, quantity_change, previous_stock, 
                                    new_stock, reference_id, reference_type, created_by, notes)
                                   VALUES (?, 'stock_in', ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                        $log_stmt = $pdo->prepare($log_sql);
                        
                        // Get current stock after update
                        $current_sql = "SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?";
                        $current_stmt = $pdo->prepare($current_sql);
                        $current_stmt->execute([$original_item['product_id']]);
                        $current_stock = $current_stmt->fetchColumn();
                        $new_stock = $current_stock + $original_item['quantity'];
                        
                        $log_stmt->execute([
                            $original_item['product_id'],
                            $original_item['quantity'],
                            $current_stock,
                            $new_stock,
                            $sale_id,
                            $_SESSION['user_id'],
                            "Item removed from sale #$sale_id"
                        ]);
                    }
                    
                    continue; // Skip to next item
                }
                
                if ($quantity <= 0 || $unit_price <= 0) {
                    continue; // Skip invalid items
                }
                
                if ($action === 'add' && $product_id > 0) {
                    // Add new item to sale
                    $add_sql = "INSERT INTO EASYSALLES_SALE_ITEMS 
                               (sale_id, product_id, quantity, unit_price, subtotal)
                               VALUES (?, ?, ?, ?, ?)";
                    $add_stmt = $pdo->prepare($add_sql);
                    $subtotal = $quantity * $unit_price;
                    $add_stmt->execute([$sale_id, $product_id, $quantity, $unit_price, $subtotal]);
                    
                    // Update stock
                    $update_stock_sql = "UPDATE EASYSALLES_PRODUCTS 
                                        SET current_stock = current_stock - ? 
                                        WHERE product_id = ?";
                    $update_stock_stmt = $pdo->prepare($update_stock_sql);
                    $update_stock_stmt->execute([$quantity, $product_id]);
                    
                    // Log stock change
                    $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                               (product_id, change_type, quantity_change, previous_stock, 
                                new_stock, reference_id, reference_type, created_by, notes)
                               VALUES (?, 'stock_out', ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                    $log_stmt = $pdo->prepare($log_sql);
                    
                    $current_sql = "SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?";
                    $current_stmt = $pdo->prepare($current_sql);
                    $current_stmt->execute([$product_id]);
                    $current_stock = $current_stmt->fetchColumn();
                    $new_stock = $current_stock - $quantity;
                    
                    $log_stmt->execute([
                        $product_id,
                        $quantity,
                        $current_stock,
                        $new_stock,
                        $sale_id,
                        $_SESSION['user_id'],
                        "Item added to sale #$sale_id"
                    ]);
                    
                    $new_subtotal += $subtotal;
                    
                } elseif ($action === 'update' && $item_id > 0) {
                    // Update existing item
                    $original_item = null;
                    foreach ($original_items as $oi) {
                        if ($oi['item_id'] == $item_id) {
                            $original_item = $oi;
                            break;
                        }
                    }
                    
                    if ($original_item) {
                        $quantity_diff = $quantity - $original_item['quantity'];
                        
                        // Update sale item
                        $update_sql = "UPDATE EASYSALLES_SALE_ITEMS 
                                      SET quantity = ?, unit_price = ?, subtotal = ?
                                      WHERE item_id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $subtotal = $quantity * $unit_price;
                        $update_stmt->execute([$quantity, $unit_price, $subtotal, $item_id]);
                        
                        // Update stock if quantity changed
                        if ($quantity_diff != 0) {
                            $stock_change = abs($quantity_diff);
                            $stock_action = $quantity_diff > 0 ? 'stock_out' : 'stock_in';
                            
                            $update_stock_sql = "UPDATE EASYSALLES_PRODUCTS 
                                                SET current_stock = current_stock - ? 
                                                WHERE product_id = ?";
                            $update_stock_stmt = $pdo->prepare($update_stock_sql);
                            $update_stock_stmt->execute([$quantity_diff, $original_item['product_id']]);
                            
                            // Log stock change
                            $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                                       (product_id, change_type, quantity_change, previous_stock, 
                                        new_stock, reference_id, reference_type, created_by, notes)
                                       VALUES (?, ?, ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                            $log_stmt = $pdo->prepare($log_sql);
                            
                            $current_sql = "SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?";
                            $current_stmt = $pdo->prepare($current_sql);
                            $current_stmt->execute([$original_item['product_id']]);
                            $current_stock = $current_stmt->fetchColumn();
                            $new_stock = $current_stock - $quantity_diff;
                            
                            $log_stmt->execute([
                                $original_item['product_id'],
                                $stock_action,
                                $stock_change,
                                $current_stock,
                                $new_stock,
                                $sale_id,
                                $_SESSION['user_id'],
                                "Item quantity updated in sale #$sale_id"
                            ]);
                        }
                        
                        $new_subtotal += $subtotal;
                    }
                    
                } elseif ($action === 'keep' && $item_id > 0) {
                    // Keep existing item as is
                    $original_item = null;
                    foreach ($original_items as $oi) {
                        if ($oi['item_id'] == $item_id) {
                            $original_item = $oi;
                            break;
                        }
                    }
                    
                    if ($original_item) {
                        $new_subtotal += $original_item['quantity'] * $original_item['unit_price'];
                    }
                }
            }
        }
        
        // Calculate new totals
        $subtotal_amount = $new_subtotal;
        $discount_amount = floatval($_POST['discount_amount'] ?? $sale['discount_amount']);
        $tax_rate = 0.01; // 1% tax
        $tax_amount = round(($subtotal_amount - $discount_amount) * $tax_rate, 2);
        $total_amount = $subtotal_amount;
        $final_amount = round($subtotal_amount - $discount_amount + $tax_amount, 2);
        
        // Update sale totals
        $update_sale_sql = "UPDATE EASYSALLES_SALES SET 
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
            $total_amount,
            $final_amount,
            $sale_id
        ];
        
        // Add security check for staff
        if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
            $update_sale_sql .= " AND staff_id = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        
        $update_sale_stmt = $pdo->prepare($update_sale_sql);
        $update_sale_stmt->execute($update_params);
        
        $pdo->commit();
        
        $success = "Sale updated successfully!";
        
        // Refresh data
        $stmt->execute($params);
        $sale = $stmt->fetch();
        
        $items_stmt->execute([$sale_id]);
        $items = $items_stmt->fetchAll();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update sale: " . $e->getMessage();
    }
}
?>

<style>
    .sale-details-container {
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
    
    /* Items Table Styles */
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
    
    .product-select {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .quantity-input {
        width: 80px;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        text-align: center;
    }
    
    .price-input {
        width: 100px;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        text-align: right;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .remove-btn {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
    }
    
    .remove-btn:hover {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
    
    .add-btn {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: #10B981;
    }
    
    .add-btn:hover {
        background: linear-gradient(135deg, #10B981, #059669);
        color: white;
    }
    
    .add-item-row {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .stock-info {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .stock-low {
        color: #EF4444;
        font-weight: 600;
    }
    
    .stock-ok {
        color: #10B981;
    }
    
    .totals-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px dashed var(--border);
    }
    
    .total-row.grand-total {
        font-weight: 700;
        font-size: 1.1rem;
        border-bottom: 2px solid var(--primary);
        color: var(--primary);
    }
    
    .total-label {
        color: #64748b;
    }
    
    .total-value {
        font-weight: 600;
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
    
    <form method="POST" action="" id="saleForm" onsubmit="return validateForm()">
        <div class="sale-details-grid">
            <!-- Customer Information -->
            <div class="detail-card">
                <div class="card-title">
                    <i class="fas fa-user"></i>
                    <span>Customer Information</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
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
                </div>
                
                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="paid" <?php echo $sale['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo $sale['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="cancelled" <?php echo $sale['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash" <?php echo $sale['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo $sale['payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="mobile_money" <?php echo $sale['payment_method'] === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="credit" <?php echo $sale['payment_method'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sale Items -->
        <div class="items-table-container">
            <div class="card-title">
                <i class="fas fa-box"></i>
                <span>Items Purchased</span>
                <button type="button" class="btn btn-primary" onclick="addNewItemRow()" style="margin-left: auto; padding: 0.5rem 1rem; font-size: 0.9rem;">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <?php if (empty($items) && empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No items found for this sale and no products available</p>
                </div>
            <?php else: ?>
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php foreach ($items as $index => $item): ?>
                            <tr data-item-id="<?php echo $item['item_id']; ?>">
                                <td>
                                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="product-code"><?php echo htmlspecialchars($item['product_code']); ?></div>
                                    <div class="stock-info">
                                        Stock: <span class="<?php echo $item['current_stock'] <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                            <?php echo $item['current_stock']; ?> available
                                        </span>
                                    </div>
                                    <input type="hidden" name="items[<?php echo $index; ?>][item_id]" value="<?php echo $item['item_id']; ?>">
                                    <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo $item['product_id']; ?>">
                                    <input type="hidden" name="items[<?php echo $index; ?>][action]" value="keep">
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                           class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['current_stock'] + $item['quantity']; ?>"
                                           onchange="updateItemAction(this, 'update')">
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][unit_price]" 
                                           class="price-input" value="<?php echo $item['unit_price']; ?>" 
                                           step="0.01" min="0.01"
                                           onchange="updateItemAction(this, 'update'); calculateRowTotal(this);">
                                </td>
                                <td class="item-subtotal">
                                    $<span class="subtotal-value"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="action-btn remove-btn" 
                                                onclick="removeItem(this, <?php echo $item['item_id']; ?>)"
                                                title="Remove Item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- New item row template (hidden) -->
                        <tr id="newItemTemplate" style="display: none;" class="add-item-row">
                            <td>
                                <select name="items[__INDEX__][product_id]" class="product-select" 
                                        onchange="updateNewItemPrice(this)" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>" 
                                                data-price="<?php echo $product['unit_price']; ?>"
                                                data-stock="<?php echo $product['current_stock']; ?>">
                                            <?php echo htmlspecialchars($product['product_name']); ?> 
                                            (<?php echo htmlspecialchars($product['product_code']); ?>)
                                            - $<?php echo number_format($product['unit_price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="items[__INDEX__][item_id]" value="0">
                                <input type="hidden" name="items[__INDEX__][action]" value="add">
                                <div class="stock-info new-item-stock" style="margin-top: 0.25rem;"></div>
                            </td>
                            <td>
                                <input type="number" name="items[__INDEX__][quantity]" 
                                       class="quantity-input" value="1" min="1" 
                                       onchange="updateNewItemStock(this); calculateRowTotal(this);">
                            </td>
                            <td>
                                <input type="number" name="items[__INDEX__][unit_price]" 
                                       class="price-input" value="0" step="0.01" min="0.01"
                                       onchange="calculateRowTotal(this);">
                            </td>
                            <td class="item-subtotal">
                                $<span class="subtotal-value">0.00</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn remove-btn" 
                                            onclick="removeNewItem(this)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">
                                <div class="totals-section">
                                    <div>
                                        <div class="total-row">
                                            <span class="total-label">Subtotal:</span>
                                            <span class="total-value" id="calculatedSubtotal">$<?php echo number_format($sale['subtotal_amount'], 2); ?></span>
                                        </div>
                                        
                                        <div class="total-row">
                                            <span class="total-label">Discount:</span>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <input type="number" name="discount_amount" 
                                                       id="discountAmount" class="form-control" 
                                                       value="<?php echo number_format($sale['discount_amount'], 2); ?>"
                                                       step="0.01" min="0" style="width: 120px;"
                                                       onchange="calculateTotals()">
                                                <span>$</span>
                                            </div>
                                        </div>
                                        
                                        <div class="total-row">
                                            <span class="total-label">Tax (1%):</span>
                                            <span class="total-value" id="calculatedTax">$<?php echo number_format($sale['tax_amount'], 2); ?></span>
                                        </div>
                                        
                                        <div class="total-row grand-total">
                                            <span class="total-label">Final Amount:</span>
                                            <span class="total-value" id="calculatedTotal">$<?php echo number_format($sale['final_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; justify-content: center;">
                                        <div style="text-align: center;">
                                            <div style="font-size: 0.9rem; color: #64748b; margin-bottom: 0.5rem;">
                                                Changes will automatically update inventory
                                            </div>
                                            <div style="font-size: 0.8rem; color: #EF4444;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Warning: Editing will affect product stock levels
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Form Actions -->
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            
            <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn cancel-btn">
                <i class="fas fa-times"></i> Cancel
            </a>
            
            <button type="button" class="btn btn-secondary" onclick="recalculateTotals()">
                <i class="fas fa-calculator"></i> Recalculate
            </button>
        </div>
    </form>
</div>

<script>
    let itemIndex = <?php echo count($items); ?>;
    
    function addNewItemRow() {
        const template = document.getElementById('newItemTemplate');
        const newRow = template.cloneNode(true);
        newRow.id = '';
        newRow.style.display = '';
        
        // Update all indices in the new row
        newRow.innerHTML = newRow.innerHTML.replace(/__INDEX__/g, itemIndex);
        
        document.getElementById('itemsTableBody').appendChild(newRow);
        itemIndex++;
        
        // Initialize the new row
        const priceInput = newRow.querySelector('input[name*="[unit_price]"]');
        const productSelect = newRow.querySelector('select[name*="[product_id]"]');
        
        if (productSelect) {
            productSelect.selectedIndex = 0;
            updateNewItemPrice(productSelect);
        }
        
        if (priceInput) {
            priceInput.value = '0.00';
            calculateRowTotal(priceInput);
        }
    }
    
    function updateNewItemPrice(select) {
        const row = select.closest('tr');
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption.getAttribute('data-price') || '0';
        const stock = selectedOption.getAttribute('data-stock') || '0';
        
        priceInput.value = parseFloat(price).toFixed(2);
        
        // Update stock info
        const stockInfo = row.querySelector('.new-item-stock');
        stockInfo.innerHTML = `Stock: <span class="${stock <= 10 ? 'stock-low' : 'stock-ok'}">${stock} available</span>`;
        
        // Update quantity max
        const quantityInput = row.querySelector('input[name*="[quantity]"]');
        quantityInput.max = stock;
        
        calculateRowTotal(priceInput);
    }
    
    function updateNewItemStock(input) {
        const row = input.closest('tr');
        const select = row.querySelector('select[name*="[product_id]"]');
        const selectedOption = select.options[select.selectedIndex];
        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        const quantity = parseInt(input.value) || 1;
        
        if (quantity > stock) {
            alert(`Only ${stock} items in stock!`);
            input.value = stock;
        }
        
        calculateRowTotal(input);
    }
    
    function removeItem(button, itemId) {
        const row = button.closest('tr');
        const actionInput = row.querySelector('input[name*="[action]"]');
        
        if (confirm('Are you sure you want to remove this item? This will restore stock.')) {
            actionInput.value = 'remove';
            row.style.display = 'none';
            calculateTotals();
        }
    }
    
    function removeNewItem(button) {
        const row = button.closest('tr');
        row.remove();
        calculateTotals();
    }
    
    function updateItemAction(input, action) {
        const row = input.closest('tr');
        const actionInput = row.querySelector('input[name*="[action]"]');
        actionInput.value = action;
    }
    
    function calculateRowTotal(input) {
        const row = input.closest('tr');
        const quantityInput = row.querySelector('input[name*="[quantity]"]');
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        const subtotalSpan = row.querySelector('.subtotal-value');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const subtotal = quantity * price;
        
        subtotalSpan.textContent = subtotal.toFixed(2);
        
        // Mark as update if it's an existing item
        if (row.dataset.itemId) {
            updateItemAction(input, 'update');
        }
        
        calculateTotals();
    }
    
    function calculateTotals() {
        let subtotal = 0;
        
        // Calculate from existing items
        document.querySelectorAll('tr[data-item-id]').forEach(row => {
            if (row.style.display !== 'none') {
                const actionInput = row.querySelector('input[name*="[action]"]');
                if (actionInput.value !== 'remove') {
                    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                    const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                    subtotal += quantity * price;
                }
            }
        });
        
        // Calculate from new items
        document.querySelectorAll('tr.add-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                subtotal += quantity * price;
            }
        });
        
        const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        const tax = (subtotal - discount) * 0.01;
        const total = subtotal - discount + tax;
        
        document.getElementById('calculatedSubtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('calculatedTax').textContent = '$' + tax.toFixed(2);
        document.getElementById('calculatedTotal').textContent = '$' + total.toFixed(2);
    }
    
    function recalculateTotals() {
        calculateTotals();
        alert('Totals recalculated!');
    }
    
    function validateForm() {
        let hasItems = false;
        
        // Check existing items
        document.querySelectorAll('tr[data-item-id]').forEach(row => {
            if (row.style.display !== 'none') {
                const actionInput = row.querySelector('input[name*="[action]"]');
                if (actionInput.value !== 'remove') {
                    hasItems = true;
                }
            }
        });
        
        // Check new items
        document.querySelectorAll('tr.add-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                hasItems = true;
            }
        });
        
        if (!hasItems) {
            alert('Please add at least one item to the sale.');
            return false;
        }
        
        // Validate new items
        let valid = true;
        document.querySelectorAll('tr.add-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                const quantity = parseInt(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                
                if (!productSelect.value) {
                    alert('Please select a product for all new items.');
                    valid = false;
                } else if (quantity <= 0) {
                    alert('Please enter a valid quantity (greater than 0).');
                    valid = false;
                } else if (price <= 0) {
                    alert('Please enter a valid price (greater than 0).');
                    valid = false;
                }
            }
        });
        
        if (!valid) {
            return false;
        }
        
        return confirm('Are you sure you want to save all changes? This will update product stock levels.');
    }
    
    // Initialize calculations on page load
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotals();
        
        // Add event listeners to existing quantity and price inputs
        document.querySelectorAll('.quantity-input, .price-input').forEach(input => {
            input.addEventListener('input', function() {
                calculateRowTotal(this);
            });
        });
        
        document.getElementById('discountAmount').addEventListener('input', calculateTotals);
    });
</script>

<?php include 'includes/footer.php'; ?>