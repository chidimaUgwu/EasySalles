<?php
// admin/sales/view.php
$page_title = "Sale Details";
require_once '../includes/header.php';

$sale_id = $_GET['id'] ?? 0;

if (!$sale_id) {
    header('Location: index.php');
    exit();
}

// Get sale details
try {
    $stmt = $pdo->prepare("SELECT s.*, u.username, u.full_name 
                          FROM EASYSALLES_SALES s 
                          LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id 
                          WHERE s.sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get sale items
$sale_items = [];
try {
    $stmt = $pdo->prepare("SELECT si.*, p.product_name, p.product_code, p.unit_type 
                          FROM EASYSALLES_SALE_ITEMS si 
                          LEFT JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id 
                          WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Get payment method display
$payment_methods = [
    'cash' => ['icon' => 'money-bill', 'color' => 'var(--success)', 'text' => 'Cash'],
    'card' => ['icon' => 'credit-card', 'color' => 'var(--primary)', 'text' => 'Card'],
    'mobile_money' => ['icon' => 'mobile-alt', 'color' => 'var(--accent)', 'text' => 'Mobile Money'],
    'credit' => ['icon' => 'hand-holding-usd', 'color' => 'var(--warning)', 'text' => 'Credit']
];

$payment_info = $payment_methods[$sale['payment_method']] ?? 
                ['icon' => 'question-circle', 'color' => 'var(--text)', 'text' => 'Unknown'];
?>

<div class="page-header">
    <div class="page-title">
        <h2>Sale Details</h2>
        <p>View complete transaction information</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Sales
        </a>
    </div>
</div>

<div class="row">
    <!-- Left Column: Sale Information -->
    <div class="col-4">
        <!-- Sale Summary Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Sale Summary</h3>
                <span class="badge badge-primary">
                    <i class="fas fa-receipt"></i> <?php echo htmlspecialchars($sale['transaction_code']); ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 2.5rem; color: var(--success); font-weight: 700; margin-bottom: 0.5rem;">
                        $<?php echo number_format($sale['final_amount'], 2); ?>
                    </div>
                    <small class="text-muted">Final Amount</small>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Subtotal:</span>
                        <span>$<?php echo number_format($sale['total_amount'], 2); ?></span>
                    </div>
                    
                    <?php if ($sale['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: var(--error);">Discount:</span>
                        <span style="color: var(--error);">-$<?php echo number_format($sale['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sale['tax_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--accent);">Tax:</span>
                        <span style="color: var(--accent);">+$<?php echo number_format($sale['tax_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment Information -->
                <div style="margin-bottom: 1.5rem;">
                    <h5 style="color: var(--primary); margin-bottom: 0.8rem;">
                        <i class="fas fa-credit-card"></i> Payment Information
                    </h5>
                    <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                        <div style="width: 40px; height: 40px; background: <?php echo $payment_info['color']; ?>20; color: <?php echo $payment_info['color']; ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-<?php echo $payment_info['icon']; ?>"></i>
                        </div>
                        <div>
                            <strong><?php echo $payment_info['text']; ?></strong><br>
                            <small class="text-muted">Payment Method</small>
                        </div>
                    </div>
                    
                    <div style="margin-top: 0.8rem;">
                        <?php 
                        $status_badge = 'badge-success';
                        if ($sale['payment_status'] == 'pending') $status_badge = 'badge-warning';
                        if ($sale['payment_status'] == 'cancelled') $status_badge = 'badge-error';
                        ?>
                        <span class="badge <?php echo $status_badge; ?>" style="font-size: 0.9rem;">
                            <?php echo ucfirst($sale['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user"></i> Customer Information
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-user-tag" style="color: var(--primary); width: 20px;"></i>
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($sale['customer_name']); ?>
                    </p>
                    
                    <?php if ($sale['customer_phone']): ?>
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-phone" style="color: var(--primary); width: 20px;"></i>
                        <strong>Phone:</strong><br>
                        <?php echo htmlspecialchars($sale['customer_phone']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($sale['customer_email']): ?>
                    <p style="margin: 0.5rem 0;">
                        <i class="fas fa-envelope" style="color: var(--primary); width: 20px;"></i>
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($sale['customer_email']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div style="background: var(--primary-light); padding: 1rem; border-radius: 8px;">
                    <p style="margin: 0; font-size: 0.9rem; color: var(--primary);">
                        <i class="fas fa-info-circle"></i>
                        Customer information can be edited from the Edit Sale page.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Staff Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-tie"></i> Staff Information
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="user-avatar" style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--secondary));">
                        <?php echo strtoupper(substr($sale['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($sale['full_name'] ?: $sale['username']); ?></strong><br>
                        <small class="text-muted">@<?php echo htmlspecialchars($sale['username']); ?></small>
                    </div>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 8px;">
                    <p style="margin: 0.3rem 0;">
                        <i class="fas fa-calendar" style="color: var(--accent); width: 20px;"></i>
                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?>
                    </p>
                    <p style="margin: 0.3rem 0;">
                        <i class="fas fa-clock" style="color: var(--accent); width: 20px;"></i>
                        <strong>Time:</strong> <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Sale Items -->
    <div class="col-8">
        <!-- Sale Items Table -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Sale Items (<?php echo count($sale_items); ?>)</h3>
                <div class="btn-group">
                    <button onclick="printItems()" class="btn btn-outline">
                        <i class="fas fa-print"></i> Print Items
                    </button>
                </div>
            </div>
            <div class="table-container">
                <?php if (empty($sale_items)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4>No Items Found</h4>
                        <p class="text-muted">This sale doesn't have any items recorded.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Stock After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_quantity = 0;
                            foreach ($sale_items as $item): 
                                $total_quantity += $item['quantity'];
                                
                                // Get current stock for this product
                                $current_stock = 0;
                                try {
                                    $stmt = $pdo->prepare("SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
                                    $stmt->execute([$item['product_id']]);
                                    $product = $stmt->fetch();
                                    $current_stock = $product['current_stock'] ?? 0;
                                } catch (PDOException $e) {
                                    $current_stock = 0;
                                }
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <div style="width: 40px; height: 40px; background: var(--primary-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-box" style="color: var(--primary);"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                            <small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--success);">
                                        $<?php echo number_format($item['unit_price'], 2); ?>
                                    </strong><br>
                                    <small class="text-muted">per <?php echo htmlspecialchars($item['unit_type']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 1rem;">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: var(--primary);">
                                        $<?php echo number_format($item['subtotal'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php 
                                    $stock_color = 'var(--success)';
                                    if ($current_stock < 10) $stock_color = 'var(--warning)';
                                    if ($current_stock == 0) $stock_color = 'var(--error)';
                                    ?>
                                    <span style="color: <?php echo $stock_color; ?>; font-weight: 600;">
                                        <?php echo $current_stock; ?>
                                    </span><br>
                                    <small class="text-muted">Current stock</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <tfoot>
                            <tr style="background: var(--bg);">
                                <td colspan="2" style="text-align: right; font-weight: 600;">Totals:</td>
                                <td>
                                    <strong><?php echo $total_quantity; ?></strong><br>
                                    <small>Total Items</small>
                                </td>
                                <td>
                                    <strong style="color: var(--success);">
                                        $<?php echo number_format($sale['total_amount'], 2); ?>
                                    </strong><br>
                                    <small>Subtotal</small>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Financial Breakdown -->
        <div class="row" style="margin-bottom: 1.5rem;">
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Subtotal</h4>
                    </div>
                    <div style="padding: 1.5rem; text-align: center;">
                        <h3 style="color: var(--primary); margin: 0;">
                            $<?php echo number_format($sale['total_amount'], 2); ?>
                        </h3>
                        <small>Before adjustments</small>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Adjustments</h4>
                    </div>
                    <div style="padding: 1.5rem; text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 0.5rem;">
                            <div>
                                <small style="color: var(--error);">Discount</small><br>
                                <span style="color: var(--error); font-weight: 600;">
                                    -$<?php echo number_format($sale['discount_amount'], 2); ?>
                                </span>
                            </div>
                            <div>
                                <small style="color: var(--accent);">Tax</small><br>
                                <span style="color: var(--accent); font-weight: 600;">
                                    +$<?php echo number_format($sale['tax_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                        <small>Applied to subtotal</small>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Final Amount</h4>
                    </div>
                    <div style="padding: 1.5rem; text-align: center;">
                        <h3 style="color: var(--success); margin: 0;">
                            $<?php echo number_format($sale['final_amount'], 2); ?>
                        </h3>
                        <small>Amount received</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes Section -->
        <?php if (!empty($sale['notes'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sticky-note"></i> Sale Notes
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <p style="margin: 0; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Print sale items
    function printItems() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Sale Items - <?php echo htmlspecialchars($sale['transaction_code']); ?></title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .total { font-weight: bold; }
                </style>
            </head>
            <body>
                <h1>Sale Items - <?php echo htmlspecialchars($sale['transaction_code']); ?></h1>
                <p>Date: <?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></p>
                <p>Customer: <?php echo htmlspecialchars($sale['customer_name']); ?></p>
                <p>Staff: <?php echo htmlspecialchars($sale['full_name'] ?: $sale['username']); ?></p>
                
                <h2>Items List</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total">
                            <td colspan="2">Total</td>
                            <td><?php echo $total_quantity; ?></td>
                            <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                        </tr>
                        <?php if ($sale['discount_amount'] > 0): ?>
                        <tr>
                            <td colspan="3">Discount</td>
                            <td>-$<?php echo number_format($sale['discount_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($sale['tax_amount'] > 0): ?>
                        <tr>
                            <td colspan="3">Tax</td>
                            <td>+$<?php echo number_format($sale['tax_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total">
                            <td colspan="3">Final Amount</td>
                            <td>$<?php echo number_format($sale['final_amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <p style="margin-top: 30px; font-size: 12px; color: #666;">
                    Printed on: ${new Date().toLocaleString()}
                </p>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Duplicate sale
    function duplicateSale() {
        if (confirm('Duplicate this sale? This will create a new sale with the same items.')) {
            // In production, this would make an AJAX call to duplicate the sale
            showToast('Duplicating sale...', 'info');
            
            // Redirect to create page with duplicate data
            setTimeout(() => {
                window.location.href = 'create.php?duplicate=<?php echo $sale_id; ?>';
            }, 1000);
        }
    }
    
    // Confirm refund
    function confirmRefund() {
        if (confirm('Process refund for this sale? This will create a refund transaction and restore stock.')) {
            // In production, this would redirect to refund page
            showToast('Redirecting to refund page...', 'info');
            window.location.href = 'refund.php?id=<?php echo $sale_id; ?>';
        }
    }
    
    // Calculate profit margin (if cost prices are available)
    function calculateProfit() {
        let totalCost = 0;
        let totalRevenue = <?php echo $sale['total_amount']; ?>;
        
        // This would require an AJAX call to get cost prices
        // For now, show a placeholder
        if (totalRevenue > 0) {
            const estimatedCost = totalRevenue * 0.6; // Assuming 40% margin
            const profit = totalRevenue - estimatedCost;
            const margin = (profit / totalRevenue) * 100;
            
            return {
                cost: estimatedCost.toFixed(2),
                profit: profit.toFixed(2),
                margin: margin.toFixed(1)
            };
        }
        
        return null;
    }
    
    // Show profit info on click
    document.addEventListener('DOMContentLoaded', function() {
        const totalElement = document.querySelector('.card-header h3 .badge');
        if (totalElement) {
            totalElement.addEventListener('click', function() {
                const profit = calculateProfit();
                if (profit) {
                    showToast(`Estimated profit: $${profit.profit} (${profit.margin}% margin)`, 'info');
                }
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
