<?php
// admin/inventory/index.php
$page_title = "Inventory Overview";
require_once '../includes/header.php';

// Get inventory stats
$stats = [];
$products = [];

try {
    // Total products count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM EASYSALLES_PRODUCTS");
    $stats['total_products'] = $stmt->fetch()['total'];
    
    // Total stock value
    $stmt = $pdo->query("SELECT SUM(current_stock * unit_price) as value FROM EASYSALLES_PRODUCTS WHERE status = 'active'");
    $stats['total_value'] = $stmt->fetch()['value'] ?? 0;
    
    // Low stock count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_PRODUCTS WHERE current_stock <= min_stock AND status = 'active'");
    $stats['low_stock'] = $stmt->fetch()['count'];
    
    // Out of stock count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM EASYSALLES_PRODUCTS WHERE current_stock = 0 AND status = 'active'");
    $stats['out_of_stock'] = $stmt->fetch()['count'];
    
    // Get products with stock levels
    $stmt = $pdo->query("SELECT product_id, product_name, product_code, category, 
                                 current_stock, min_stock, max_stock, unit_price,
                                 unit_type, status
                          FROM EASYSALLES_PRODUCTS 
                          WHERE status = 'active'
                          ORDER BY current_stock ASC");
    $products = $stmt->fetchAll();
    
    // Get recent inventory logs
    $stmt = $pdo->prepare("SELECT l.*, p.product_name, u.username 
                          FROM EASYSALLES_INVENTORY_LOG l
                          LEFT JOIN EASYSALLES_PRODUCTS p ON l.product_id = p.product_id
                          LEFT JOIN EASYSALLES_USERS u ON l.created_by = u.user_id
                          ORDER BY l.created_at DESC 
                          LIMIT 10");
    $stmt->execute();
    $recent_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // If tables don't exist, show empty state
    $stats = ['total_products' => 0, 'total_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
    $products = [];
    $recent_logs = [];
}

// Categorize products by stock level
$critical_products = array_filter($products, fn($p) => $p['current_stock'] == 0);
$low_products = array_filter($products, fn($p) => $p['current_stock'] > 0 && $p['current_stock'] <= $p['min_stock']);
$normal_products = array_filter($products, fn($p) => $p['current_stock'] > $p['min_stock'] && $p['current_stock'] <= $p['max_stock']);
$overstock_products = array_filter($products, fn($p) => $p['current_stock'] > $p['max_stock']);
?>

<div class="page-header">
    <div class="page-title">
        <h2>Inventory Overview</h2>
        <p>Monitor stock levels, values, and manage your inventory</p>
    </div>
    <div class="page-actions">
        <a href="stock-adjustment.php" class="btn btn-primary">
            <i class="fas fa-exchange-alt"></i> Adjust Stock
        </a>
        <a href="low-stock.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
        </a>
    </div>
</div>

<!-- Inventory Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_products']; ?></h3>
            <p>Total Products</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($stats['total_value'], 2); ?></h3>
            <p>Total Inventory Value</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['low_stock']; ?></h3>
            <p>Low Stock Items</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['out_of_stock']; ?></h3>
            <p>Out of Stock</p>
        </div>
    </div>
</div>

<!-- Stock Status Overview -->
<div class="row" style="margin-bottom: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stock Status Distribution</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-3">
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="width: 80px; height: 80px; background: var(--error-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-times" style="font-size: 2rem; color: var(--error);"></i>
                            </div>
                            <h3 style="color: var(--error); margin-bottom: 0.5rem;"><?php echo count($critical_products); ?></h3>
                            <p>Out of Stock</p>
                        </div>
                    </div>
                    
                    <div class="col-3">
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="width: 80px; height: 80px; background: var(--warning-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--warning);"></i>
                            </div>
                            <h3 style="color: var(--warning); margin-bottom: 0.5rem;"><?php echo count($low_products); ?></h3>
                            <p>Low Stock</p>
                        </div>
                    </div>
                    
                    <div class="col-3">
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="width: 80px; height: 80px; background: var(--success-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                            </div>
                            <h3 style="color: var(--success); margin-bottom: 0.5rem;"><?php echo count($normal_products); ?></h3>
                            <p>Normal Stock</p>
                        </div>
                    </div>
                    
                    <div class="col-3">
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="width: 80px; height: 80px; background: var(--accent-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-arrow-up" style="font-size: 2rem; color: var(--accent);"></i>
                            </div>
                            <h3 style="color: var(--accent); margin-bottom: 0.5rem;"><?php echo count($overstock_products); ?></h3>
                            <p>Overstock</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Critical Stock Items -->
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-times-circle" style="color: var(--error);"></i> 
                    Critical Stock (Out of Stock)
                </h3>
            </div>
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($critical_products)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;"></i>
                        <h4>No Critical Stock Items</h4>
                        <p class="text-muted">All products are in stock</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Required</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($critical_products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></span>
                                </td>
                                <td>
                                    <span style="color: var(--error); font-weight: 600;">0 <?php echo htmlspecialchars($product['unit_type']); ?></span><br>
                                    <small>Min: <?php echo $product['min_stock']; ?></small>
                                </td>
                                <td>
                                    <a href="stock-adjustment.php?product_id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-outline" 
                                       style="padding: 0.4rem 0.8rem;">
                                        <i class="fas fa-plus"></i> Restock
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Items -->
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> 
                    Low Stock Items
                </h3>
            </div>
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($low_products)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;"></i>
                        <h4>No Low Stock Items</h4>
                        <p class="text-muted">All products have sufficient stock</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current</th>
                                <th>Min</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_products as $product): 
                                $percent = ($product['current_stock'] / $product['min_stock']) * 100;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                </td>
                                <td>
                                    <span style="color: var(--warning); font-weight: 600;">
                                        <?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $product['min_stock']; ?></td>
                                <td>
                                    <div style="width: 60px; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo min($percent, 100); ?>%; background: var(--warning);"></div>
                                    </div>
                                    <small><?php echo round($percent); ?>% of min</small>
                                </td>
                                <td>
                                    <a href="stock-adjustment.php?product_id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-outline" 
                                       style="padding: 0.4rem 0.8rem;">
                                        <i class="fas fa-plus"></i> Add Stock
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Inventory Activity -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Inventory Activity</h3>
                <a href="../reports/inventory.php" class="btn btn-outline">View Full Log</a>
            </div>
            <div class="table-container">
                <?php if (empty($recent_logs)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-history" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4>No Inventory Activity</h4>
                        <p class="text-muted">Inventory logs will appear here after stock adjustments</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Product</th>
                                <th>Activity</th>
                                <th>Quantity Change</th>
                                <th>Stock Before</th>
                                <th>Stock After</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): 
                                $change_color = 'var(--success)';
                                $change_icon = 'fa-plus';
                                if ($log['quantity_change'] < 0) {
                                    $change_color = 'var(--error)';
                                    $change_icon = 'fa-minus';
                                }
                                if ($log['change_type'] == 'adjustment') {
                                    $change_color = 'var(--warning)';
                                    $change_icon = 'fa-exchange-alt';
                                }
                                
                                $activity_text = ucfirst(str_replace('_', ' ', $log['change_type']));
                                if ($log['reference_type']) {
                                    $activity_text .= " (" . ucfirst($log['reference_type']) . ")";
                                }
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                                <td>
                                    <span class="badge" style="background: <?php echo $change_color; ?>20; color: <?php echo $change_color; ?>;">
                                        <i class="fas <?php echo $change_icon; ?>"></i> <?php echo $activity_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: <?php echo $change_color; ?>; font-weight: 600;">
                                        <?php echo $log['quantity_change'] > 0 ? '+' : ''; ?><?php echo $log['quantity_change']; ?>
                                    </span>
                                </td>
                                <td><?php echo $log['previous_stock']; ?></td>
                                <td><?php echo $log['new_stock']; ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Inventory Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-2">
                        <a href="stock-adjustment.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-exchange-alt"></i> Adjust Stock
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="low-stock.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../products/add.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-plus"></i> Add Product
                        </a>
                    </div>
                    <div class="col-2">
                        <button onclick="exportInventoryReport()" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                    <div class="col-2">
                        <button onclick="printInventory()" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                    <div class="col-2">
                        <a href="../reports/inventory.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Export inventory report
    function exportInventoryReport() {
        const data = [];
        
        // Add headers
        data.push(['Product Name', 'SKU', 'Category', 'Current Stock', 'Min Stock', 'Max Stock', 'Unit Price', 'Stock Value', 'Status']);
        
        // Add product data
        <?php foreach ($products as $product): ?>
            const stockValue = <?php echo $product['current_stock'] * $product['unit_price']; ?>;
            const status = <?php echo $product['current_stock'] == 0 ? "'Out of Stock'" : ($product['current_stock'] <= $product['min_stock'] ? "'Low Stock'" : "'Normal'"); ?>;
            data.push([
                '<?php echo addslashes($product['product_name']); ?>',
                '<?php echo $product['product_code']; ?>',
                '<?php echo addslashes($product['category'] ?: 'Uncategorized'); ?>',
                <?php echo $product['current_stock']; ?>,
                <?php echo $product['min_stock']; ?>,
                <?php echo $product['max_stock']; ?>,
                <?php echo $product['unit_price']; ?>,
                stockValue.toFixed(2),
                status
            ]);
        <?php endforeach; ?>
        
        // Create CSV
        let csv = '';
        data.forEach(row => {
            csv += row.join(',') + '\n';
        });
        
        // Create download link
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inventory_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Inventory report exported successfully', 'success');
    }
    
    // Print inventory
    function printInventory() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Inventory Report - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    .stats { display: flex; justify-content: space-between; margin: 20px 0; }
                    .stat-box { padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; width: 23%; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .critical { background-color: #ffebee; }
                    .low { background-color: #fff3e0; }
                </style>
            </head>
            <body>
                <h1>Inventory Report - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                
                <div class="stats">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                    <div class="stat-box">
                        <h3>$<?php echo number_format($stats['total_value'], 2); ?></h3>
                        <p>Total Value</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $stats['out_of_stock']; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                </div>
                
                <h2>Product Inventory</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Min Stock</th>
                            <th>Max Stock</th>
                            <th>Unit Price</th>
                            <th>Stock Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `);
        
        <?php foreach ($products as $product): 
            $stockValue = $product['current_stock'] * $product['unit_price'];
            $status = $product['current_stock'] == 0 ? 'Out of Stock' : ($product['current_stock'] <= $product['min_stock'] ? 'Low Stock' : 'Normal');
            $rowClass = $product['current_stock'] == 0 ? 'critical' : ($product['current_stock'] <= $product['min_stock'] ? 'low' : '');
        ?>
            printWindow.document.write(`
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo addslashes($product['product_name']); ?></td>
                    <td><?php echo $product['product_code']; ?></td>
                    <td><?php echo addslashes($product['category'] ?: 'Uncategorized'); ?></td>
                    <td><?php echo $product['current_stock']; ?></td>
                    <td><?php echo $product['min_stock']; ?></td>
                    <td><?php echo $product['max_stock']; ?></td>
                    <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                    <td>$<?php echo number_format($stockValue, 2); ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
            `);
        <?php endforeach; ?>
        
        printWindow.document.write(`
                    </tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Auto-refresh critical stock alerts
    setInterval(() => {
        document.querySelectorAll('.stat-card:nth-child(3), .stat-card:nth-child(4)').forEach(card => {
            if (card.querySelector('h3').textContent !== '0') {
                card.style.animation = card.style.animation ? '' : 'pulse 2s infinite';
            }
        });
    }, 3000);
</script>

<style>
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
</style>

<?php require_once '../includes/footer.php'; ?>
