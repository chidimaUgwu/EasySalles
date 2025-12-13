<?php
// admin/products/view.php
$page_title = "Product Details";
require_once '../../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = intval($_GET['id']);

// Get product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, s.supplier_name, s.contact_person, s.phone as supplier_phone
        FROM EASYSALLES_PRODUCTS p
        LEFT JOIN EASYSALLES_SUPPLIERS s ON p.supplier_id = s.supplier_id
        WHERE p.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo '<div class="alert alert-error">Product not found!</div>';
        echo '<a href="index.php" class="btn btn-primary">Back to Products</a>';
        require_once '../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error: ' . $e->getMessage() . '</div>';
    require_once '../../includes/footer.php';
    exit;
}

// Get inventory history
$inventory_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM EASYSALLES_INVENTORY_LOG 
        WHERE product_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$product_id]);
    $inventory_history = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get recent sales
$recent_sales = [];
try {
    $stmt = $pdo->prepare("
        SELECT si.*, s.sale_date, s.invoice_number, s.customer_name
        FROM EASYSALLES_SALES_ITEMS si
        JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
        WHERE si.product_id = ? 
        ORDER BY s.sale_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$product_id]);
    $recent_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Calculate stock percentage
$stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
$stock_status = 'success';
if ($product['current_stock'] <= $product['min_stock']) {
    $stock_status = 'error';
} elseif ($stock_percent < 30) {
    $stock_status = 'warning';
}

// Get total sales stats
$sales_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(si.quantity) as total_sold,
            SUM(si.total_amount) as total_revenue,
            AVG(si.unit_price) as avg_sale_price,
            COUNT(DISTINCT s.sale_id) as sale_count
        FROM EASYSALLES_SALES_ITEMS si
        JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
        WHERE si.product_id = ? AND s.status = 'completed'
    ");
    $stmt->execute([$product_id]);
    $sales_stats = $stmt->fetch();
} catch (PDOException $e) {
    $sales_stats = ['total_sold' => 0, 'total_revenue' => 0, 'avg_sale_price' => 0, 'sale_count' => 0];
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Product Details</h2>
        <p>View complete product information and history</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary" style="margin-left: 0.5rem;">
            <i class="fas fa-edit"></i> Edit Product
        </a>
        <button onclick="window.print()" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <!-- Product Information Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-box"></i> Product Information
                </h3>
                <div class="btn-group">
                    <span class="badge <?php echo $product['status'] == 'active' ? 'badge-success' : ($product['status'] == 'inactive' ? 'badge-warning' : 'badge-error'); ?>">
                        <?php echo ucfirst($product['status']); ?>
                    </span>
                </div>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-4">
                        <div style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--primary-light), var(--accent-light)); 
                                    border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-box" style="font-size: 4rem; color: var(--primary);"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-8">
                        <h2 style="margin-top: 0;"><?php echo htmlspecialchars($product['product_name']); ?></h2>
                        <p class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></p>
                        
                        <div style="display: flex; gap: 1rem; margin: 1rem 0;">
                            <?php if ($product['category']): ?>
                                <span class="badge badge-primary"><?php echo htmlspecialchars($product['category']); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-secondary"><?php echo ucfirst($product['unit_type']); ?></span>
                            <?php if ($product['barcode']): ?>
                                <span class="badge badge-info">Barcode: <?php echo htmlspecialchars($product['barcode']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['description']): ?>
                            <div style="margin: 1rem 0; padding: 1rem; background: var(--bg); border-radius: 10px;">
                                <h4>Description</h4>
                                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Price and Stock Information -->
                <div class="row" style="margin-top: 2rem;">
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem; background: var(--success-light); border-radius: 10px;">
                            <small class="text-muted">Selling Price</small>
                            <h3 style="color: var(--success); margin: 0.5rem 0;">$<?php echo number_format($product['unit_price'], 2); ?></h3>
                            <small>Per <?php echo $product['unit_type']; ?></small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem; background: var(--warning-light); border-radius: 10px;">
                            <small class="text-muted">Cost Price</small>
                            <h3 style="color: var(--warning); margin: 0.5rem 0;">$<?php echo number_format($product['cost_price'] ?? 0, 2); ?></h3>
                            <small>Profit: $<?php echo number_format($product['unit_price'] - ($product['cost_price'] ?? 0), 2); ?></small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem; background: var(--primary-light); border-radius: 10px;">
                            <small class="text-muted">Current Stock Value</small>
                            <h3 style="color: var(--primary); margin: 0.5rem 0;">$<?php echo number_format($product['current_stock'] * $product['unit_price'], 2); ?></h3>
                            <small><?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?> in stock</small>
                        </div>
                    </div>
                </div>
                
                <!-- Stock Progress Bar -->
                <div style="margin-top: 2rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Stock Level</span>
                        <span><?php echo $product['current_stock']; ?> / <?php echo $product['max_stock']; ?> <?php echo $product['unit_type']; ?></span>
                    </div>
                    <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo min($stock_percent, 100); ?>%; 
                            background: var(--<?php echo $stock_status; ?>); border-radius: 5px;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                        <small>Min: <?php echo $product['min_stock']; ?></small>
                        <small>Max: <?php echo $product['max_stock']; ?></small>
                    </div>
                    <?php if ($stock_status == 'error'): ?>
                        <div style="color: var(--error); margin-top: 0.5rem;">
                            <i class="fas fa-exclamation-triangle"></i> Low stock alert! Reorder needed.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Supplier Information -->
        <?php if ($product['supplier_name']): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-truck"></i> Supplier Information
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-6">
                        <p><strong>Supplier:</strong> <?php echo htmlspecialchars($product['supplier_name']); ?></p>
                        <?php if ($product['contact_person']): ?>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['contact_person']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <?php if ($product['supplier_phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($product['supplier_phone']); ?></p>
                        <?php endif; ?>
                        <a href="suppliers.php" class="btn btn-outline" style="margin-top: 1rem;">
                            <i class="fas fa-external-link-alt"></i> View Supplier Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Sales -->
        <?php if (!empty($recent_sales)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart"></i> Recent Sales
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                            <td>
                                <a href="../sales/view.php?id=<?php echo $sale['sale_id']; ?>" style="color: var(--primary);">
                                    <?php echo htmlspecialchars($sale['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td>$<?php echo number_format($sale['unit_price'], 2); ?></td>
                            <td><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="../sales/index.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                        View All Sales for This Product
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-4">
        <!-- Quick Stats -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i> Sales Statistics
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                    <div style="text-align: center;">
                        <small class="text-muted">Total Sold</small>
                        <h2 style="margin: 0.5rem 0;"><?php echo $sales_stats['total_sold']; ?></h2>
                        <small><?php echo $product['unit_type']; ?></small>
                    </div>
                    <div style="text-align: center;">
                        <small class="text-muted">Total Revenue</small>
                        <h2 style="margin: 0.5rem 0; color: var(--success);">$<?php echo number_format($sales_stats['total_revenue'], 2); ?></h2>
                        <small>From sales</small>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <small class="text-muted">Average Sale Price</small>
                    <h4 style="margin: 0.5rem 0;">$<?php echo number_format($sales_stats['avg_sale_price'] ?? $product['unit_price'], 2); ?></h4>
                    
                    <small class="text-muted">Number of Sales</small>
                    <h4 style="margin: 0.5rem 0;"><?php echo $sales_stats['sale_count']; ?></h4>
                    
                    <small class="text-muted">Created On</small>
                    <h4 style="margin: 0.5rem 0;"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></h4>
                    
                    <small class="text-muted">Last Updated</small>
                    <h4 style="margin: 0.5rem 0;"><?php echo date('M d, Y H:i', strtotime($product['updated_at'] ?? $product['created_at'])); ?></h4>
                </div>
            </div>
        </div>
        
        <!-- Inventory History -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i> Recent Stock Changes
                </h3>
            </div>
            <div style="padding: 1.5rem; max-height: 300px; overflow-y: auto;">
                <?php if (empty($inventory_history)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        <i class="fas fa-history" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p style="margin-top: 1rem;">No inventory history recorded</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory_history as $history): ?>
                        <div style="margin-bottom: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                                <strong style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $history['change_type']); ?></strong>
                                <span class="badge <?php echo $history['change_type'] == 'stock_in' ? 'badge-success' : 'badge-error'; ?>">
                                    <?php echo $history['change_type'] == 'stock_in' ? '+' : '-'; ?>
                                    <?php echo $history['quantity_change']; ?>
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-light);">
                                <?php echo date('M d, H:i', strtotime($history['created_at'])); ?>
                                <?php if ($history['notes']): ?>
                                    · <?php echo htmlspecialchars($history['notes']); ?>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.85rem; margin-top: 0.3rem;">
                                <small>Stock: <?php echo $history['previous_stock']; ?> → <?php echo $history['new_stock']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; gap: 0.8rem;">
                    <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Product
                    </a>
                    
                    <a href="../inventory/stock-adjustment.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                        <i class="fas fa-exchange-alt"></i> Adjust Stock
                    </a>
                    
                    <a href="../sales/create.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                        <i class="fas fa-cart-plus"></i> Sell This Product
                    </a>
                    
                    <a href="delete.php?id=<?php echo $product_id; ?>" 
                       class="btn btn-outline" 
                       style="color: var(--error);"
                       onclick="return confirm('Are you sure you want to delete this product? This will also remove all related sales records.')">
                        <i class="fas fa-trash"></i> Delete Product
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Print-friendly function
    function printProductDetails() {
        const printContent = document.querySelector('.row').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <html>
            <head>
                <title>Product Details - <?php echo htmlspecialchars($product['product_name']); ?></title>
                <style>
                    body { font-family: Arial; margin: 20px; color: #333; }
                    .card { border: 1px solid #ddd; border-radius: 10px; margin-bottom: 20px; }
                    .card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }
                    .card-title { margin: 0; }
                    .row { display: flex; gap: 20px; }
                    .col-8 { flex: 8; }
                    .col-4 { flex: 4; }
                    .badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; }
                    .badge-primary { background: #007bff; color: white; }
                    .badge-success { background: #28a745; color: white; }
                    .badge-warning { background: #ffc107; color: black; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    .text-muted { color: #6c757d; }
                </style>
            </head>
            <body>
                <h1>Product Details: <?php echo htmlspecialchars($product['product_name']); ?></h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${printContent}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }
    
    // Share product link
    function shareProduct() {
        const url = window.location.href;
        const title = "Check out this product: <?php echo htmlspecialchars($product['product_name']); ?>";
        
        if (navigator.share) {
            navigator.share({
                title: title,
                url: url
            });
        } else {
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard!', 'success');
            });
        }
    }
    
    // Stock level alert
    const stockPercent = <?php echo $stock_percent; ?>;
    if (stockPercent < 15) {
        // Show warning toast if stock is very low
        showToast('Warning: Stock is very low! Consider reordering.', 'warning', 5000);
    }
</script>

<style>
    @media print {
        .page-actions, .btn, .badge {
            display: none !important;
        }
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
    }
    
    .stock-warning {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>
