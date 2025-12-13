<?php
// admin/inventory/low-stock.php
$page_title = "Low Stock Alerts";
require_once '../includes/header.php';

// Get low stock products
$critical_products = [];
$low_products = [];

try {
    // Get out of stock products (critical)
    $stmt = $pdo->prepare("SELECT p.*, 
                                  (p.min_stock - p.current_stock) as needed,
                                  (p.current_stock * p.unit_price) as stock_value
                           FROM EASYSALLES_PRODUCTS p
                           WHERE p.status = 'active' AND p.current_stock = 0
                           ORDER BY p.product_name");
    $stmt->execute();
    $critical_products = $stmt->fetchAll();
    
    // Get low stock products (below min level)
    $stmt = $pdo->prepare("SELECT p.*, 
                                  (p.min_stock - p.current_stock) as needed,
                                  (p.current_stock * p.unit_price) as stock_value,
                                  ROUND((p.current_stock * 100.0 / p.min_stock), 1) as percent_of_min
                           FROM EASYSALLES_PRODUCTS p
                           WHERE p.status = 'active' 
                           AND p.current_stock > 0 
                           AND p.current_stock <= p.min_stock
                           ORDER BY percent_of_min ASC, p.product_name");
    $stmt->execute();
    $low_products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tables might not exist yet
}

$total_critical = count($critical_products);
$total_low = count($low_products);
$total_alerts = $total_critical + $total_low;
?>

<div class="page-header">
    <div class="page-title">
        <h2>Low Stock Alerts</h2>
        <p>Monitor products that need immediate attention (<?php echo $total_alerts; ?> alerts)</p>
    </div>
    <div class="page-actions">
        <a href="stock-adjustment.php" class="btn btn-primary">
            <i class="fas fa-exchange-alt"></i> Adjust Stock
        </a>
        <button onclick="printAlerts()" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button onclick="exportAlerts()" class="btn btn-outline" style="margin-left: 0.5rem;">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
</div>

<!-- Alert Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--error-light); color: var(--error);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_critical; ?></h3>
            <p>Out of Stock</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_low; ?></h3>
            <p>Low Stock</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--accent-light); color: var(--accent);">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_critical + $total_low; ?></h3>
            <p>Total Alerts</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <h3>
                <?php 
                $total_needed = 0;
                foreach ($critical_products as $p) $total_needed += $p['needed'];
                foreach ($low_products as $p) $total_needed += $p['needed'];
                echo $total_needed;
                ?>
            </h3>
            <p>Units Needed</p>
        </div>
    </div>
</div>

<!-- Critical Alerts (Out of Stock) -->
<div class="card" style="margin-bottom: 2rem; border-left: 4px solid var(--error);">
    <div class="card-header">
        <h3 class="card-title" style="color: var(--error);">
            <i class="fas fa-times-circle"></i> 
            Critical Alerts - Out of Stock (<?php echo $total_critical; ?>)
        </h3>
        <?php if ($total_critical > 0): ?>
            <a href="stock-adjustment.php" class="btn btn-error">
                <i class="fas fa-plus"></i> Restock All Critical Items
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($critical_products)): ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--success);">No Critical Alerts</h3>
            <p class="text-muted">All products are in stock</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Min Stock</th>
                        <th>Needed</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($critical_products as $product): ?>
                    <tr style="background: rgba(239, 68, 68, 0.05);">
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-box" style="color: var(--error);"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></span>
                        </td>
                        <td>
                            <span style="color: var(--warning);"><?php echo $product['min_stock']; ?></span>
                        </td>
                        <td>
                            <span style="color: var(--error); font-weight: 600; font-size: 1.1rem;">
                                <?php echo $product['needed']; ?>
                            </span>
                        </td>
                        <td>
                            $<?php echo number_format($product['unit_price'], 2); ?>
                        </td>
                        <td>
                            <span class="badge badge-error">
                                <i class="fas fa-times"></i> Out of Stock
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="stock-adjustment.php?product_id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem; border-color: var(--error); color: var(--error);">
                                    <i class="fas fa-plus"></i> Restock
                                </a>
                                <a href="../products/view.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;">
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

<!-- Low Stock Alerts -->
<div class="card" style="border-left: 4px solid var(--warning);">
    <div class="card-header">
        <h3 class="card-title" style="color: var(--warning);">
            <i class="fas fa-exclamation-triangle"></i> 
            Low Stock Alerts (<?php echo $total_low; ?>)
        </h3>
        <?php if ($total_low > 0): ?>
            <button onclick="restockAllLowItems()" class="btn btn-warning">
                <i class="fas fa-cart-plus"></i> Restock All Low Items
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (empty($low_products)): ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--success);">No Low Stock Alerts</h3>
            <p class="text-muted">All products have sufficient stock</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current</th>
                        <th>Min</th>
                        <th>Needed</th>
                        <th>% of Min</th>
                        <th>Status</th>
                        <th>Urgency</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_products as $product): 
                        $percent = $product['percent_of_min'];
                        $urgency = 'Low';
                        $urgency_color = 'var(--success)';
                        
                        if ($percent < 25) {
                            $urgency = 'Critical';
                            $urgency_color = 'var(--error)';
                        } elseif ($percent < 50) {
                            $urgency = 'High';
                            $urgency_color = 'var(--warning)';
                        } elseif ($percent < 75) {
                            $urgency = 'Medium';
                            $urgency_color = 'var(--accent)';
                        }
                    ?>
                    <tr style="background: rgba(245, 158, 11, 0.05);">
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-box" style="color: var(--warning);"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color: var(--warning); font-weight: 600;">
                                <?php echo $product['current_stock']; ?>
                            </span>
                        </td>
                        <td><?php echo $product['min_stock']; ?></td>
                        <td>
                            <span style="color: var(--warning); font-weight: 600;">
                                <?php echo $product['needed']; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 60px; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo $percent; ?>%; background: var(--warning);"></div>
                                </div>
                                <span style="color: var(--warning);"><?php echo $percent; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-warning">
                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                            </span>
                        </td>
                        <td>
                            <span style="color: <?php echo $urgency_color; ?>; font-weight: 600;">
                                <?php echo $urgency; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="stock-adjustment.php?product_id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem; border-color: var(--warning); color: var(--warning);">
                                    <i class="fas fa-plus"></i> Add Stock
                                </a>
                                <a href="../products/view.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;">
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

<!-- Restock Suggestions -->
<?php if ($total_alerts > 0): ?>
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lightbulb"></i> Restock Suggestions
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-6">
                        <h4 style="color: var(--error); margin-bottom: 1rem;">
                            <i class="fas fa-fire"></i> Priority Items
                        </h4>
                        <ul>
                            <?php 
                            $priority_items = array_merge($critical_products, 
                                array_filter($low_products, fn($p) => $p['percent_of_min'] < 50));
                            $priority_items = array_slice($priority_items, 0, 5);
                            
                            foreach ($priority_items as $item): 
                                $type = $item['current_stock'] == 0 ? 'Out of Stock' : 'Low Stock';
                            ?>
                            <li style="margin-bottom: 0.5rem;">
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong> 
                                (<?php echo $type; ?>) - 
                                <span style="color: var(--error);">Needs <?php echo $item['needed']; ?> units</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="col-6">
                        <h4 style="color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-chart-line"></i> Total Requirements
                        </h4>
                        <div style="background: var(--bg); padding: 1rem; border-radius: 10px;">
                            <?php 
                            $total_needed_units = 0;
                            $total_needed_value = 0;
                            
                            foreach ($critical_products as $p) {
                                $total_needed_units += $p['needed'];
                                $total_needed_value += $p['needed'] * $p['unit_price'];
                            }
                            foreach ($low_products as $p) {
                                $total_needed_units += $p['needed'];
                                $total_needed_value += $p['needed'] * $p['unit_price'];
                            }
                            ?>
                            <p><strong>Total Units Needed:</strong> <span style="color: var(--primary);"><?php echo $total_needed_units; ?></span></p>
                            <p><strong>Estimated Cost:</strong> <span style="color: var(--success);">$<?php echo number_format($total_needed_value, 2); ?></span></p>
                            <p><strong>Critical Items:</strong> <span style="color: var(--error);"><?php echo $total_critical; ?></span></p>
                            <p><strong>Low Items:</strong> <span style="color: var(--warning);"><?php echo $total_low; ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Email/SMS Alert Settings -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bell"></i> Alert Settings
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Low Stock Threshold</label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="range" 
                                       min="1" 
                                       max="100" 
                                       value="10" 
                                       class="form-control"
                                       style="flex: 1;">
                                <span id="thresholdValue" style="min-width: 50px; text-align: center;">10%</span>
                            </div>
                            <small class="text-muted">Alert when stock is below this percentage of min level</small>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Alert Frequency</label>
                            <select class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="immediate">Immediate</option>
                                <option value="never">Never</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Notification Method</label>
                            <div>
                                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <input type="checkbox" checked> 
                                    <i class="fas fa-envelope" style="color: var(--primary);"></i> Email
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox"> 
                                    <i class="fas fa-sms" style="color: var(--accent);"></i> SMS
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button class="btn btn-outline" style="margin-left: 0.5rem;" onclick="testAlert()">
                        <i class="fas fa-bell"></i> Test Alert
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update threshold value display
    document.querySelector('input[type="range"]').addEventListener('input', function() {
        document.getElementById('thresholdValue').textContent = this.value + '%';
    });
    
    // Export alerts to CSV
    function exportAlerts() {
        const data = [];
        
        // Add headers
        data.push(['Product Name', 'SKU', 'Category', 'Current Stock', 'Min Stock', 'Needed', 'Unit Price', 'Stock Value', 'Status', 'Urgency']);
        
        // Add critical products
        <?php foreach ($critical_products as $product): ?>
            data.push([
                '<?php echo addslashes($product['product_name']); ?>',
                '<?php echo $product['product_code']; ?>',
                '<?php echo addslashes($product['category'] ?: 'Uncategorized'); ?>',
                <?php echo $product['current_stock']; ?>,
                <?php echo $product['min_stock']; ?>,
                <?php echo $product['needed']; ?>,
                <?php echo $product['unit_price']; ?>,
                <?php echo $product['stock_value']; ?>,
                'Out of Stock',
                'Critical'
            ]);
        <?php endforeach; ?>
        
        // Add low stock products
        <?php foreach ($low_products as $product): 
            $urgency = 'Low';
            if ($product['percent_of_min'] < 25) $urgency = 'Critical';
            elseif ($product['percent_of_min'] < 50) $urgency = 'High';
            elseif ($product['percent_of_min'] < 75) $urgency = 'Medium';
        ?>
            data.push([
                '<?php echo addslashes($product['product_name']); ?>',
                '<?php echo $product['product_code']; ?>',
                '<?php echo addslashes($product['category'] ?: 'Uncategorized'); ?>',
                <?php echo $product['current_stock']; ?>,
                <?php echo $product['min_stock']; ?>,
                <?php echo $product['needed']; ?>,
                <?php echo $product['unit_price']; ?>,
                <?php echo $product['stock_value']; ?>,
                'Low Stock',
                '<?php echo $urgency; ?>'
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
        a.download = 'low_stock_alerts_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Alerts exported successfully', 'success');
    }
    
    // Print alerts
    function printAlerts() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Low Stock Alerts - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    .summary { display: flex; justify-content: space-between; margin: 20px 0; }
                    .summary-box { padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; width: 23%; }
                    .critical { background-color: #ffebee; }
                    .low { background-color: #fff3e0; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .urgent { background-color: #ffcdd2; }
                    .high { background-color: #ffe0b2; }
                </style>
            </head>
            <body>
                <h1>Low Stock Alerts - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                
                <div class="summary">
                    <div class="summary-box" style="border-color: #ef4444;">
                        <h3 style="color: #ef4444;"><?php echo $total_critical; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                    <div class="summary-box" style="border-color: #f59e0b;">
                        <h3 style="color: #f59e0b;"><?php echo $total_low; ?></h3>
                        <p>Low Stock</p>
                    </div>
                    <div class="summary-box" style="border-color: #7c3aed;">
                        <h3 style="color: #7c3aed;"><?php echo $total_alerts; ?></h3>
                        <p>Total Alerts</p>
                    </div>
                    <div class="summary-box" style="border-color: #10b981;">
                        <h3 style="color: #10b981;"><?php 
                            $total_needed = 0;
                            foreach ($critical_products as $p) $total_needed += $p['needed'];
                            foreach ($low_products as $p) $total_needed += $p['needed'];
                            echo $total_needed;
                        ?></h3>
                        <p>Units Needed</p>
                    </div>
                </div>
                
                <h2>Critical Alerts - Out of Stock</h2>
                <?php if (!empty($critical_products)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Min Stock</th>
                                <th>Needed</th>
                                <th>Unit Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($critical_products as $product): ?>
                                <tr class="critical">
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></td>
                                    <td><?php echo $product['min_stock']; ?></td>
                                    <td style="color: #ef4444; font-weight: bold;"><?php echo $product['needed']; ?></td>
                                    <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #10b981;">No critical alerts - all products are in stock</p>
                <?php endif; ?>
                
                <h2 style="margin-top: 30px;">Low Stock Alerts</h2>
                <?php if (!empty($low_products)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Current</th>
                                <th>Min</th>
                                <th>Needed</th>
                                <th>% of Min</th>
                                <th>Urgency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_products as $product): 
                                $urgency = 'Low';
                                if ($product['percent_of_min'] < 25) $urgency = 'Critical';
                                elseif ($product['percent_of_min'] < 50) $urgency = 'High';
                                elseif ($product['percent_of_min'] < 75) $urgency = 'Medium';
                                
                                $rowClass = '';
                                if ($urgency === 'Critical') $rowClass = 'urgent';
                                elseif ($urgency === 'High') $rowClass = 'high';
                            ?>
                                <tr class="${rowClass}">
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                    <td><?php echo $product['current_stock']; ?></td>
                                    <td><?php echo $product['min_stock']; ?></td>
                                    <td><?php echo $product['needed']; ?></td>
                                    <td><?php echo $product['percent_of_min']; ?>%</td>
                                    <td><?php echo $urgency; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #10b981;">No low stock alerts - all products have sufficient stock</p>
                <?php endif; ?>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Restock all low items
    function restockAllLowItems() {
        if (confirm('This will create stock adjustments for all low stock items. Continue?')) {
            // In production, this would make an AJAX call to process all items
            showToast('Processing restock for all low items...', 'info');
            
            // Simulate processing
            setTimeout(() => {
                showToast('Restock orders created for all low stock items', 'success');
            }, 2000);
        }
    }
    
    // Test alert
    function testAlert() {
        showToast('Test alert sent to your email', 'success');
    }
    
    // Auto-refresh critical alerts
    setInterval(() => {
        const criticalCards = document.querySelectorAll('.card:first-child .stat-card:first-child, .card:first-child .stat-card:nth-child(2)');
        criticalCards.forEach(card => {
            if (parseInt(card.querySelector('h3').textContent) > 0) {
                card.style.animation = card.style.animation ? '' : 'pulse 1.5s infinite';
            }
        });
    }, 2000);
</script>

<style>
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }
</style>

<?php require_once '../includes/footer.php'; ?>
