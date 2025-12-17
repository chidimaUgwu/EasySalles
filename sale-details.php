<?php
// sale-details.php - FIXED WITH ITEM EDITING
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction for safety
    $pdo->beginTransaction();
    
    try {
        // Get basic sale info
        $customer_name = $_POST['customer_name'] ?? $sale['customer_name'];
        $customer_phone = $_POST['customer_phone'] ?? $sale['customer_phone'];
        $customer_email = $_POST['customer_email'] ?? $sale['customer_email'];
        $payment_method = $_POST['payment_method'] ?? $sale['payment_method'];
        $payment_status = $_POST['payment_status'] ?? $sale['payment_status'];
        $notes = $_POST['notes'] ?? $sale['notes'];
        $discount_amount = floatval($_POST['discount_amount'] ?? $sale['discount_amount']);
        
        // Initialize variables
        $new_subtotal = 0;
        $items_to_update = [];
        $items_to_delete = [];
        $items_to_add = [];
        
        // Process existing items
        if (isset($_POST['existing_items']) && is_array($_POST['existing_items'])) {
            foreach ($_POST['existing_items'] as $item_id => $item_data) {
                if (isset($item_data['delete']) && $item_data['delete'] == '1') {
                    // Mark for deletion
                    $items_to_delete[] = $item_id;
                } else {
                    // Mark for update
                    $items_to_update[$item_id] = [
                        'quantity' => floatval($item_data['quantity'] ?? 1),
                        'unit_price' => floatval($item_data['unit_price'] ?? 0)
                    ];
                }
            }
        }
        
        // Process new items
        if (isset($_POST['new_items']) && is_array($_POST['new_items'])) {
            foreach ($_POST['new_items'] as $index => $item_data) {
                if (!empty($item_data['product_id']) && $item_data['product_id'] > 0) {
                    $items_to_add[] = [
                        'product_id' => intval($item_data['product_id']),
                        'quantity' => floatval($item_data['quantity'] ?? 1),
                        'unit_price' => floatval($item_data['unit_price'] ?? 0)
                    ];
                }
            }
        }
        
        // 1. Delete items first (to restore stock)
        foreach ($items_to_delete as $item_id) {
            // Find the item
            $item_to_delete = null;
            foreach ($items as $item) {
                if ($item['item_id'] == $item_id) {
                    $item_to_delete = $item;
                    break;
                }
            }
            
            if ($item_to_delete) {
                // Delete from sale_items
                $delete_sql = "DELETE FROM EASYSALLES_SALE_ITEMS WHERE item_id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$item_id]);
                
                // Restore stock
                $restore_sql = "UPDATE EASYSALLES_PRODUCTS 
                               SET current_stock = current_stock + ? 
                               WHERE product_id = ?";
                $restore_stmt = $pdo->prepare($restore_sql);
                $restore_stmt->execute([$item_to_delete['quantity'], $item_to_delete['product_id']]);
                
                // Log the change
                $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                           (product_id, change_type, quantity_change, previous_stock, 
                            new_stock, reference_id, reference_type, created_by, notes)
                           VALUES (?, 'stock_in', ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    $item_to_delete['product_id'],
                    $item_to_delete['quantity'],
                    $item_to_delete['current_stock'],
                    $item_to_delete['current_stock'] + $item_to_delete['quantity'],
                    $sale_id,
                    $_SESSION['user_id'],
                    "Item removed from sale #$sale_id"
                ]);
            }
        }
        
        // 2. Update existing items
        foreach ($items_to_update as $item_id => $item_data) {
            // Find original item
            $original_item = null;
            foreach ($items as $item) {
                if ($item['item_id'] == $item_id) {
                    $original_item = $item;
                    break;
                }
            }
            
            if ($original_item) {
                $quantity_diff = $item_data['quantity'] - $original_item['quantity'];
                $new_subtotal += $item_data['quantity'] * $item_data['unit_price'];
                
                // Update sale item
                $update_sql = "UPDATE EASYSALLES_SALE_ITEMS 
                              SET quantity = ?, unit_price = ?, subtotal = ?
                              WHERE item_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $new_subtotal_row = $item_data['quantity'] * $item_data['unit_price'];
                $update_stmt->execute([
                    $item_data['quantity'],
                    $item_data['unit_price'],
                    $new_subtotal_row,
                    $item_id
                ]);
                
                // Update stock if quantity changed
                if ($quantity_diff != 0) {
                    $stock_change_sql = "UPDATE EASYSALLES_PRODUCTS 
                                        SET current_stock = current_stock - ? 
                                        WHERE product_id = ?";
                    $stock_stmt = $pdo->prepare($stock_change_sql);
                    $stock_stmt->execute([$quantity_diff, $original_item['product_id']]);
                    
                    // Log stock change
                    $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                               (product_id, change_type, quantity_change, previous_stock, 
                                new_stock, reference_id, reference_type, created_by, notes)
                               VALUES (?, ?, ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                    $log_stmt = $pdo->prepare($log_sql);
                    
                    $change_type = $quantity_diff > 0 ? 'stock_out' : 'stock_in';
                    $quantity_change = abs($quantity_diff);
                    
                    $log_stmt->execute([
                        $original_item['product_id'],
                        $change_type,
                        $quantity_change,
                        $original_item['current_stock'],
                        $original_item['current_stock'] - $quantity_diff,
                        $sale_id,
                        $_SESSION['user_id'],
                        "Item quantity updated in sale #$sale_id"
                    ]);
                }
            }
        }
        
        // 3. Add new items
        foreach ($items_to_add as $new_item) {
            // Check product exists and has stock
            $product_check = $pdo->prepare("SELECT product_id, current_stock, unit_price FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
            $product_check->execute([$new_item['product_id']]);
            $product = $product_check->fetch();
            
            if ($product) {
                $item_subtotal = $new_item['quantity'] * $new_item['unit_price'];
                $new_subtotal += $item_subtotal;
                
                // Add to sale_items
                $add_sql = "INSERT INTO EASYSALLES_SALE_ITEMS 
                           (sale_id, product_id, quantity, unit_price, subtotal)
                           VALUES (?, ?, ?, ?, ?)";
                $add_stmt = $pdo->prepare($add_sql);
                $add_stmt->execute([
                    $sale_id,
                    $new_item['product_id'],
                    $new_item['quantity'],
                    $new_item['unit_price'],
                    $item_subtotal
                ]);
                
                // Update stock
                $stock_sql = "UPDATE EASYSALLES_PRODUCTS 
                             SET current_stock = current_stock - ? 
                             WHERE product_id = ?";
                $stock_stmt = $pdo->prepare($stock_sql);
                $stock_stmt->execute([$new_item['quantity'], $new_item['product_id']]);
                
                // Log stock change
                $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                           (product_id, change_type, quantity_change, previous_stock, 
                            new_stock, reference_id, reference_type, created_by, notes)
                           VALUES (?, 'stock_out', ?, ?, ?, ?, 'sale_adjustment', ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    $new_item['product_id'],
                    $new_item['quantity'],
                    $product['current_stock'],
                    $product['current_stock'] - $new_item['quantity'],
                    $sale_id,
                    $_SESSION['user_id'],
                    "New item added to sale #$sale_id"
                ]);
            }
        }
        
        // Calculate new totals
        $tax_rate = 0.01; // 1% tax
        $tax_amount = round(($new_subtotal - $discount_amount) * $tax_rate, 2);
        $final_amount = round($new_subtotal - $discount_amount + $tax_amount, 2);
        
        // Update sale record
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
            $new_subtotal,
            $discount_amount,
            $tax_amount,
            $final_amount,
            $final_amount,
            $sale_id
        ];
        
        // Add security check for staff
        if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
            $update_sale_sql .= " AND staff_id = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        
        $update_stmt = $pdo->prepare($update_sale_sql);
        $update_stmt->execute($update_params);
        
        // Commit transaction
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
        error_log("Sale update error: " . $e->getMessage());
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
    
    .delete-btn {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
    }
    
    .delete-btn:hover {
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
    
    .new-item-row {
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
        gap: 2rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--border);
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
    }
    
    .total-row.grand-total {
        font-weight: 700;
        font-size: 1.2rem;
        border-top: 2px solid var(--primary);
        margin-top: 0.5rem;
        padding-top: 1rem;
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
    
    .checkbox-delete {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .delete-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #EF4444;
        cursor: pointer;
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
                
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Discount Amount ($)</label>
                    <input type="number" name="discount_amount" class="form-control" 
                           value="<?php echo number_format($sale['discount_amount'], 2); ?>" 
                           step="0.01" min="0">
                </div>
            </div>
        </div>
        
        <!-- Sale Items -->
        <div class="items-table-container">
            <div class="card-title">
                <i class="fas fa-box"></i>
                <span>Items Purchased</span>
                <button type="button" class="btn btn-primary" onclick="addNewItem()" style="margin-left: auto; padding: 0.5rem 1rem; font-size: 0.9rem;">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>
            
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price ($)</th>
                        <th>Subtotal ($)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <!-- Existing Items -->
                    <?php foreach ($items as $item): ?>
                        <tr class="existing-item" data-item-id="<?php echo $item['item_id']; ?>">
                            <td>
                                <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="product-code"><?php echo htmlspecialchars($item['product_code']); ?></div>
                                <div class="stock-info">
                                    Stock: <span class="<?php echo $item['current_stock'] <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                        <?php echo $item['current_stock']; ?> available
                                    </span>
                                </div>
                            </td>
                            <td>
                                <input type="number" name="existing_items[<?php echo $item['item_id']; ?>][quantity]" 
                                       class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                       min="1" onchange="updateCalculations()">
                            </td>
                            <td>
                                <input type="number" name="existing_items[<?php echo $item['item_id']; ?>][unit_price]" 
                                       class="price-input" value="<?php echo $item['unit_price']; ?>" 
                                       step="0.01" min="0.01" onchange="updateCalculations()">
                            </td>
                            <td class="item-subtotal">
                                $<span class="subtotal-value"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></span>
                            </td>
                            <td>
                                <label class="delete-label">
                                    <input type="checkbox" name="existing_items[<?php echo $item['item_id']; ?>][delete]" 
                                           value="1" class="checkbox-delete" onchange="toggleDeleteRow(this)">
                                    <span>Delete</span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- New Items Template (hidden) -->
                    <tr id="newItemTemplate" style="display: none;" class="new-item-row">
                        <td>
                            <select name="new_items[__INDEX__][product_id]" class="product-select" onchange="updateNewItemPrice(this)">
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
                            <div class="stock-info new-item-stock"></div>
                        </td>
                        <td>
                            <input type="number" name="new_items[__INDEX__][quantity]" 
                                   class="quantity-input" value="1" min="1" 
                                   onchange="updateNewItemStock(this); updateCalculations()">
                        </td>
                        <td>
                            <input type="number" name="new_items[__INDEX__][unit_price]" 
                                   class="price-input" value="0" step="0.01" min="0.01"
                                   onchange="updateCalculations()">
                        </td>
                        <td class="item-subtotal">
                            $<span class="subtotal-value">0.00</span>
                        </td>
                        <td>
                            <button type="button" class="action-btn delete-btn" onclick="removeNewItem(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Totals Section -->
            <div class="totals-section">
                <div>
                    <div class="total-row">
                        <span class="total-label">Subtotal:</span>
                        <span class="total-value" id="calculatedSubtotal">$<?php echo number_format($sale['subtotal_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Discount:</span>
                        <span class="total-value" id="calculatedDiscount">$<?php echo number_format($sale['discount_amount'], 2); ?></span>
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
                            <i class="fas fa-info-circle"></i> Changes update inventory automatically
                        </div>
                        <div style="font-size: 0.85rem; color: #EF4444;">
                            <i class="fas fa-exclamation-triangle"></i>
                            Item deletions will restore stock
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            
            <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn cancel-btn">
                <i class="fas fa-times"></i> Cancel
            </a>
            
            <button type="button" class="btn btn-secondary" onclick="recalculateAll()">
                <i class="fas fa-calculator"></i> Recalculate
            </button>
        </div>
    </form>
</div>

<script>
    let newItemIndex = 0;
    
    function addNewItem() {
        const template = document.getElementById('newItemTemplate');
        const newRow = template.cloneNode(true);
        newRow.id = '';
        newRow.style.display = '';
        
        // Update all indices in the new row
        newRow.innerHTML = newRow.innerHTML.replace(/__INDEX__/g, newItemIndex);
        
        document.getElementById('itemsTableBody').appendChild(newRow);
        newItemIndex++;
        
        // Initialize the new row
        updateNewItemPrice(newRow.querySelector('select[name*="[product_id]"]'));
    }
    
    function updateNewItemPrice(select) {
        const row = select.closest('tr');
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const price = selectedOption.getAttribute('data-price') || '0';
            const stock = selectedOption.getAttribute('data-stock') || '0';
            
            priceInput.value = parseFloat(price).toFixed(2);
            
            // Update stock info
            const stockInfo = row.querySelector('.new-item-stock');
            stockInfo.innerHTML = `Stock: <span class="${stock <= 10 ? 'stock-low' : 'stock-ok'}">${stock} available</span>`;
            
            // Update quantity max
            const quantityInput = row.querySelector('input[name*="[quantity]"]');
            quantityInput.max = stock;
            if (parseInt(quantityInput.value) > parseInt(stock)) {
                quantityInput.value = stock;
            }
        } else {
            priceInput.value = '0.00';
            const stockInfo = row.querySelector('.new-item-stock');
            stockInfo.innerHTML = '';
        }
        
        updateCalculations();
    }
    
    function updateNewItemStock(input) {
        const row = input.closest('tr');
        const select = row.querySelector('select[name*="[product_id]"]');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const quantity = parseInt(input.value) || 1;
            
            if (quantity > stock) {
                alert(`Only ${stock} items in stock!`);
                input.value = stock;
            }
        }
        
        updateCalculations();
    }
    
    function removeNewItem(button) {
        const row = button.closest('tr');
        row.remove();
        updateCalculations();
    }
    
    function toggleDeleteRow(checkbox) {
        const row = checkbox.closest('tr');
        if (checkbox.checked) {
            row.style.opacity = '0.6';
            row.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
        } else {
            row.style.opacity = '1';
            row.style.backgroundColor = '';
        }
        updateCalculations();
    }
    
    function updateCalculations() {
        let subtotal = 0;
        
        // Calculate existing items (not marked for deletion)
        document.querySelectorAll('.existing-item').forEach(row => {
            const deleteCheckbox = row.querySelector('input[type="checkbox"]');
            if (!deleteCheckbox || !deleteCheckbox.checked) {
                const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                const subtotalValue = quantity * price;
                
                // Update row subtotal display
                const subtotalSpan = row.querySelector('.subtotal-value');
                if (subtotalSpan) {
                    subtotalSpan.textContent = subtotalValue.toFixed(2);
                }
                
                subtotal += subtotalValue;
            }
        });
        
        // Calculate new items
        document.querySelectorAll('.new-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                const subtotalValue = quantity * price;
                
                // Update row subtotal display
                const subtotalSpan = row.querySelector('.subtotal-value');
                if (subtotalSpan) {
                    subtotalSpan.textContent = subtotalValue.toFixed(2);
                }
                
                subtotal += subtotalValue;
            }
        });
        
        // Get discount
        const discountInput = document.querySelector('input[name="discount_amount"]');
        const discount = parseFloat(discountInput.value) || 0;
        
        // Calculate tax (1%)
        const tax = (subtotal - discount) * 0.01;
        
        // Calculate total
        const total = subtotal - discount + tax;
        
        // Update display
        document.getElementById('calculatedSubtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('calculatedDiscount').textContent = '$' + discount.toFixed(2);
        document.getElementById('calculatedTax').textContent = '$' + tax.toFixed(2);
        document.getElementById('calculatedTotal').textContent = '$' + total.toFixed(2);
    }
    
    function recalculateAll() {
        updateCalculations();
        alert('All calculations updated!');
    }
    
    // Form validation
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        // Check customer name
        const customerName = this.querySelector('input[name="customer_name"]');
        if (!customerName.value.trim()) {
            e.preventDefault();
            alert('Customer name is required!');
            customerName.focus();
            return false;
        }
        
        // Check if there's at least one item
        let hasItems = false;
        document.querySelectorAll('.existing-item, .new-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                const deleteCheckbox = row.querySelector('input[type="checkbox"]');
                if (!deleteCheckbox || !deleteCheckbox.checked) {
                    hasItems = true;
                }
            }
        });
        
        if (!hasItems) {
            e.preventDefault();
            alert('Sale must have at least one item!');
            return false;
        }
        
        // Validate new items
        let valid = true;
        document.querySelectorAll('.new-item-row').forEach(row => {
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
            e.preventDefault();
            return false;
        }
        
        // Confirm save
        if (!confirm('Are you sure you want to save all changes?\n\nThis will:' +
                    '\n• Update sale details' +
                    '\n• Modify product stock levels' +
                    '\n• Log inventory changes')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Initialize calculations on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCalculations();
        
        // Add event listeners for inputs
        document.querySelectorAll('.quantity-input, .price-input').forEach(input => {
            input.addEventListener('input', updateCalculations);
        });
        
        // Discount input listener
        const discountInput = document.querySelector('input[name="discount_amount"]');
        if (discountInput) {
            discountInput.addEventListener('input', updateCalculations);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>