<?php
// sale-details.php - MODERN DESIGN
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
    echo '<div class="alert alert-error">Sale not found or no permission.</div>';
    include 'includes/footer.php';
    exit();
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type, p.current_stock
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ? ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Get products for adding
$products_sql = "SELECT product_id, product_name, product_code, unit_price, current_stock 
                 FROM EASYSALLES_PRODUCTS WHERE status = 'active' ORDER BY product_name";
$products = $pdo->query($products_sql)->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    try {
        // Update basic sale info
        $update_sql = "UPDATE EASYSALLES_SALES SET 
                      customer_name = ?,
                      customer_phone = ?,
                      customer_email = ?,
                      payment_method = ?,
                      payment_status = ?,
                      notes = ?
                      WHERE sale_id = ?";
        
        $update_params = [
            $_POST['customer_name'] ?? $sale['customer_name'],
            $_POST['customer_phone'] ?? $sale['customer_phone'],
            $_POST['customer_email'] ?? $sale['customer_email'],
            $_POST['payment_method'] ?? $sale['payment_method'],
            $_POST['payment_status'] ?? $sale['payment_status'],
            $_POST['notes'] ?? $sale['notes'],
            $sale_id
        ];
        
        if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
            $update_sql .= " AND staff_id = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute($update_params);
        
        // Process item updates
        if (isset($_POST['update_items']) && is_array($_POST['update_items'])) {
            foreach ($_POST['update_items'] as $item_id => $data) {
                $update_item_sql = "UPDATE EASYSALLES_SALE_ITEMS 
                                   SET quantity = ?, unit_price = ?, subtotal = ?
                                   WHERE item_id = ? AND sale_id = ?";
                $quantity = floatval($data['quantity'] ?? 1);
                $unit_price = floatval($data['unit_price'] ?? 0);
                $subtotal = $quantity * $unit_price;
                
                $update_item_stmt = $pdo->prepare($update_item_sql);
                $update_item_stmt->execute([$quantity, $unit_price, $subtotal, $item_id, $sale_id]);
            }
        }
        
        // Process item deletions
        if (isset($_POST['delete_items']) && is_array($_POST['delete_items'])) {
            foreach ($_POST['delete_items'] as $item_id) {
                $delete_sql = "DELETE FROM EASYSALLES_SALE_ITEMS WHERE item_id = ? AND sale_id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$item_id, $sale_id]);
            }
        }
        
        // Process new items
        if (isset($_POST['new_items']) && is_array($_POST['new_items'])) {
            foreach ($_POST['new_items'] as $data) {
                if (!empty($data['product_id']) && $data['product_id'] > 0) {
                    $quantity = floatval($data['quantity'] ?? 1);
                    $unit_price = floatval($data['unit_price'] ?? 0);
                    $subtotal = $quantity * $unit_price;
                    
                    $insert_sql = "INSERT INTO EASYSALLES_SALE_ITEMS 
                                  (sale_id, product_id, quantity, unit_price, subtotal)
                                  VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([$sale_id, $data['product_id'], $quantity, $unit_price, $subtotal]);
                }
            }
        }
        
        // Recalculate totals
        $recalc_sql = "SELECT SUM(subtotal) as new_subtotal FROM EASYSALLES_SALE_ITEMS WHERE sale_id = ?";
        $recalc_stmt = $pdo->prepare($recalc_sql);
        $recalc_stmt->execute([$sale_id]);
        $new_subtotal = $recalc_stmt->fetchColumn() ?? 0;
        
        $discount = floatval($_POST['discount_amount'] ?? $sale['discount_amount']);
        $tax = ($new_subtotal - $discount) * 0.01;
        $final_amount = $new_subtotal - $discount + $tax;
        
        $update_totals_sql = "UPDATE EASYSALLES_SALES SET 
                             subtotal_amount = ?,
                             discount_amount = ?,
                             tax_amount = ?,
                             total_amount = ?,
                             final_amount = ?
                             WHERE sale_id = ?";
        $update_totals_stmt = $pdo->prepare($update_totals_sql);
        $update_totals_stmt->execute([
            $new_subtotal,
            $discount,
            $tax,
            $final_amount,
            $final_amount,
            $sale_id
        ]);
        
        $success = "Sale updated successfully!";
        
        // Refresh data
        $stmt->execute($params);
        $sale = $stmt->fetch();
        $items_stmt->execute([$sale_id]);
        $items = $items_stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --sidebar-width: 250px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .sale-id {
            background: var(--primary);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        /* Button Styles */
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--gray);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-icon {
            padding: 10px;
            width: 40px;
            height: 40px;
            justify-content: center;
        }

        /* Alert Styles */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        /* Card Layout */
        .card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 992px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border);
        }

        .card-header i {
            font-size: 22px;
            color: var(--primary);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
            color: var(--dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 46px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Items Table */
        .items-card {
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .table th {
            background: var(--light);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.02) 100%);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .product-name {
            font-weight: 600;
            color: var(--dark);
        }

        .product-code {
            font-size: 13px;
            color: var(--gray);
        }

        .stock-info {
            font-size: 12px;
            color: var(--gray);
        }

        .stock-low {
            color: var(--danger);
            font-weight: 600;
        }

        .stock-ok {
            color: var(--success);
        }

        /* Input Controls in Table */
        .table-input {
            width: 80px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .table-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .table-select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .subtotal-cell {
            font-weight: 600;
            color: var(--dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-table {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-table-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-table-danger:hover {
            background: var(--danger);
            color: white;
        }

        .btn-table-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .btn-table-success:hover {
            background: var(--success);
            color: white;
        }

        /* Totals Section */
        .totals-section {
            background: var(--light);
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border);
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .total-row.grand-total {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            padding-top: 16px;
            margin-top: 16px;
            border-top: 2px solid var(--primary);
        }

        .total-label {
            color: var(--gray);
            font-weight: 500;
        }

        .total-value {
            font-weight: 600;
            color: var(--dark);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            margin-top: 30px;
        }

        /* Sale Info Row */
        .sale-info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 13px;
            color: var(--gray);
            font-weight: 500;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--border);
        }

        .empty-state p {
            font-size: 16px;
        }

        /* Loading Spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }

            .card {
                padding: 20px;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --light: #1e293b;
                --dark: #f1f5f9;
                --gray: #94a3b8;
                --border: #334155;
                --card-bg: #0f172a;
            }

            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            }

            .card, .header, .form-actions {
                background: var(--card-bg);
                border-color: var(--border);
            }

            .form-control {
                background: #1e293b;
                color: var(--dark);
                border-color: var(--border);
            }

            .table th {
                background: #1e293b;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <div>
                    <h1>Sale Details</h1>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                        <span class="sale-id">#<?php echo $sale_id; ?></span>
                        <span class="status-badge <?php echo 'status-' . $sale['payment_status']; ?>">
                            <i class="fas fa-<?php echo $sale['payment_status'] === 'paid' ? 'check-circle' : ($sale['payment_status'] === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                            <?php echo ucfirst($sale['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <a href="sales-list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Sales
                </a>
                <button class="btn btn-primary" onclick="window.open('print-receipt.php?id=<?php echo $sale_id; ?>', '_blank')">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="saleForm">
            <input type="hidden" name="save_changes" value="1">
            
            <!-- Two Column Layout -->
            <div class="card-grid">
                <!-- Customer Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <div class="card-title">Customer Information</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="customer_name" class="form-control" 
                               value="<?php echo htmlspecialchars($sale['customer_name']); ?>" 
                               required placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="customer_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($sale['customer_phone']); ?>"
                                   placeholder="+1234567890">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="customer_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($sale['customer_email']); ?>"
                                   placeholder="customer@example.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Any additional notes about this sale..."><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Sale Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <div class="card-title">Sale Information</div>
                    </div>
                    
                    <div class="sale-info-row">
                        <div class="info-item">
                            <span class="info-label">Transaction Code</span>
                            <span class="info-value"><?php echo htmlspecialchars($sale['transaction_code']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Date & Time</span>
                            <span class="info-value">
                                <?php echo date('F j, Y \a\t h:i A', strtotime($sale['sale_date'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Processed By</span>
                            <span class="info-value"><?php echo htmlspecialchars($sale['staff_name'] ?? $sale['staff_username']); ?></span>
                        </div>
                    </div>
                    
                    <div class="form-row">
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
                                <option value="card" <?php echo $sale['payment_method'] === 'card' ? 'selected' : ''; ?>>Credit/Debit Card</option>
                                <option value="mobile_money" <?php echo $sale['payment_method'] === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="credit" <?php echo $sale['payment_method'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Discount Amount ($)</label>
                        <input type="number" name="discount_amount" class="form-control" 
                               value="<?php echo number_format($sale['discount_amount'], 2); ?>"
                               step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="card items-card">
                <div class="table-header">
                    <div class="card-header" style="border: none; padding: 0; margin: 0;">
                        <i class="fas fa-shopping-cart"></i>
                        <div class="card-title">Sale Items</div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addNewItem()">
                        <i class="fas fa-plus"></i> Add New Item
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Existing Items -->
                            <?php foreach ($items as $item): ?>
                                <tr data-item-id="<?php echo $item['item_id']; ?>">
                                    <td>
                                        <div class="product-info">
                                            <span class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            <span class="product-code"><?php echo htmlspecialchars($item['product_code']); ?></span>
                                            <span class="stock-info">
                                                Stock: <span class="<?php echo $item['current_stock'] <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                                    <?php echo $item['current_stock']; ?> available
                                                </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="update_items[<?php echo $item['item_id']; ?>][quantity]" 
                                               class="table-input" value="<?php echo $item['quantity']; ?>" min="1"
                                               onchange="updateRowTotal(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="update_items[<?php echo $item['item_id']; ?>][unit_price]" 
                                               class="table-input" value="<?php echo $item['unit_price']; ?>" step="0.01" min="0.01"
                                               onchange="updateRowTotal(this)">
                                    </td>
                                    <td class="subtotal-cell">
                                        $<span class="row-subtotal"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-table btn-table-danger" 
                                                onclick="deleteItem(this, <?php echo $item['item_id']; ?>)"
                                                title="Remove Item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fas fa-shopping-cart"></i>
                                        <p>No items in this sale. Click "Add New Item" to add products.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Totals Section -->
                <div class="totals-section">
                    <div class="total-row">
                        <span class="total-label">Subtotal:</span>
                        <span class="total-value" id="totalSubtotal">$<?php echo number_format($sale['subtotal_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Discount:</span>
                        <span class="total-value" id="totalDiscount">$<?php echo number_format($sale['discount_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Tax (1%):</span>
                        <span class="total-value" id="totalTax">$<?php echo number_format($sale['tax_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row grand-total">
                        <span class="total-label">Final Amount:</span>
                        <span class="total-value" id="totalFinal">$<?php echo number_format($sale['final_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Hidden container for deleted items and new items -->
            <div id="deletedItemsContainer" style="display: none;"></div>
            <div id="newItemsContainer" style="display: none;"></div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save All Changes
                </button>
                
                <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                
                <button type="button" class="btn btn-secondary" onclick="calculateTotals()">
                    <i class="fas fa-calculator"></i> Recalculate
                </button>
            </div>
        </form>
    </div>

    <script>
        let newItemCount = 0;
        let deletedItems = [];
        
        // Product data for JavaScript
        const products = <?php echo json_encode($products); ?>;
        
        function addNewItem() {
            const tbody = document.getElementById('itemsBody');
            
            // Remove empty state if present
            const emptyState = tbody.querySelector('.empty-state');
            if (emptyState) {
                emptyState.closest('tr').remove();
            }
            
            const row = document.createElement('tr');
            row.className = 'new-item-row';
            row.innerHTML = `
                <td>
                    <select name="new_items[${newItemCount}][product_id]" class="table-select" onchange="updateNewItemPrice(this)">
                        <option value="">Select Product</option>
                        ${products.map(p => `
                            <option value="${p.product_id}" data-price="${p.unit_price}" data-stock="${p.current_stock}">
                                ${p.product_name} (${p.product_code}) - $${p.unit_price}
                            </option>
                        `).join('')}
                    </select>
                    <div class="stock-info" style="margin-top: 5px;"></div>
                </td>
                <td>
                    <input type="number" name="new_items[${newItemCount}][quantity]" 
                           class="table-input" value="1" min="1" onchange="updateNewItemStock(this); updateRowTotal(this);">
                </td>
                <td>
                    <input type="number" name="new_items[${newItemCount}][unit_price]" 
                           class="table-input" value="0" step="0.01" min="0.01" onchange="updateRowTotal(this)">
                </td>
                <td class="subtotal-cell">
                    $<span class="row-subtotal">0.00</span>
                </td>
                <td>
                    <button type="button" class="btn-table btn-table-danger" onclick="removeNewItem(this)" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            newItemCount++;
            
            // Initialize the select
            updateNewItemPrice(row.querySelector('select[name*="[product_id]"]'));
        }
        
        function updateNewItemPrice(select) {
            const row = select.closest('tr');
            const priceInput = row.querySelector('input[name*="[unit_price]"]');
            const stockInfo = row.querySelector('.stock-info');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price') || '0';
                const stock = selectedOption.getAttribute('data-stock') || '0';
                
                priceInput.value = parseFloat(price).toFixed(2);
                
                // Update stock info
                stockInfo.innerHTML = `Stock: <span class="${stock <= 10 ? 'stock-low' : 'stock-ok'}">${stock} available</span>`;
                
                // Update quantity max
                const quantityInput = row.querySelector('input[name*="[quantity]"]');
                quantityInput.max = stock;
                if (parseInt(quantityInput.value) > parseInt(stock)) {
                    quantityInput.value = stock;
                }
            } else {
                priceInput.value = '0.00';
                stockInfo.innerHTML = '';
            }
            
            updateRowTotal(priceInput);
        }
        
        function updateNewItemStock(input) {
            const row = input.closest('tr');
            const select = row.querySelector('select[name*="[product_id]"]');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                const quantity = parseInt(input.value) || 1;
                
                if (quantity > stock) {
                    alert(`⚠️ Only ${stock} items in stock!`);
                    input.value = stock;
                }
            }
            
            updateRowTotal(input);
        }
        
        function removeNewItem(button) {
            const row = button.closest('tr');
            if (confirm('Remove this item?')) {
                row.remove();
                calculateTotals();
                
                // Show empty state if no items left
                const tbody = document.getElementById('itemsBody');
                if (tbody.children.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No items in this sale. Click "Add New Item" to add products.</p>
                            </td>
                        </tr>
                    `;
                }
            }
        }
        
        function updateRowTotal(input) {
            const row = input.closest('tr');
            const quantityInput = row.querySelector('input[name*="[quantity]"]');
            const priceInput = row.querySelector('input[name*="[unit_price]"]');
            const subtotalSpan = row.querySelector('.row-subtotal');
            
            const quantity = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const subtotal = quantity * price;
            
            subtotalSpan.textContent = subtotal.toFixed(2);
            calculateTotals();
        }
        
        function deleteItem(button, itemId) {
            if (confirm('Are you sure you want to delete this item from the sale?')) {
                const row = button.closest('tr');
                row.style.opacity = '0.5';
                row.style.pointerEvents = 'none';
                
                // Add to deleted items
                if (!deletedItems.includes(itemId)) {
                    deletedItems.push(itemId);
                    const container = document.getElementById('deletedItemsContainer');
                    
                    // Create hidden input for deleted item
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `delete_items[${itemId}]`;
                    input.value = itemId;
                    container.appendChild(input);
                }
                
                calculateTotals();
            }
        }
        
        function calculateTotals() {
            let subtotal = 0;
            
            // Calculate from existing and new items (not deleted)
            document.querySelectorAll('#itemsBody tr').forEach(row => {
                if (row.style.opacity !== '0.5') { // Not deleted
                    const subtotalText = row.querySelector('.row-subtotal').textContent;
                    const rowSubtotal = parseFloat(subtotalText) || 0;
                    subtotal += rowSubtotal;
                }
            });
            
            const discountInput = document.querySelector('input[name="discount_amount"]');
            const discount = parseFloat(discountInput.value) || 0;
            const tax = (subtotal - discount) * 0.01;
            const total = subtotal - discount + tax;
            
            document.getElementById('totalSubtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('totalDiscount').textContent = '$' + discount.toFixed(2);
            document.getElementById('totalTax').textContent = '$' + tax.toFixed(2);
            document.getElementById('totalFinal').textContent = '$' + total.toFixed(2);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener to discount input
            const discountInput = document.querySelector('input[name="discount_amount"]');
            if (discountInput) {
                discountInput.addEventListener('input', calculateTotals);
            }
            
            // Add event listeners to existing quantity and price inputs
            document.querySelectorAll('.table-input').forEach(input => {
                input.addEventListener('input', function() {
                    updateRowTotal(this);
                });
            });
            
            // Form validation
            document.getElementById('saleForm').addEventListener('submit', function(e) {
                // Check customer name
                const customerName = this.querySelector('input[name="customer_name"]');
                if (!customerName.value.trim()) {
                    e.preventDefault();
                    alert('❌ Customer name is required!');
                    customerName.focus();
                    return false;
                }
                
                // Check if there's at least one item
                const hasItems = document.querySelectorAll('#itemsBody tr').length > 1 || 
                               (document.querySelectorAll('#itemsBody tr').length === 1 && 
                                !document.querySelector('#itemsBody .empty-state'));
                
                if (!hasItems) {
                    e.preventDefault();
                    alert('❌ Sale must have at least one item!');
                    return false;
                }
                
                // Validate new items
                let valid = true;
                document.querySelectorAll('.new-item-row').forEach(row => {
                    const productSelect = row.querySelector('select[name*="[product_id]"]');
                    const quantity = parseInt(row.querySelector('input[name*="[quantity]"]').value) || 0;
                    const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                    
                    if (!productSelect.value) {
                        alert('❌ Please select a product for all new items.');
                        valid = false;
                        productSelect.focus();
                    } else if (quantity <= 0) {
                        alert('❌ Please enter a valid quantity (greater than 0).');
                        valid = false;
                        row.querySelector('input[name*="[quantity]"]').focus();
                    } else if (price <= 0) {
                        alert('❌ Please enter a valid price (greater than 0).');
                        valid = false;
                        row.querySelector('input[name*="[unit_price]"]').focus();
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                
                // Confirm save
                if (!confirm('💾 Save all changes?\n\nThis will update the sale and adjust inventory.')) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Initial calculation
            calculateTotals();
        });
    </script>
</body>
</html>