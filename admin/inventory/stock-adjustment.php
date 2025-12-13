<?php
// admin/inventory/stock-adjustment.php
$page_title = "Stock Adjustment";
require_once '../includes/header.php';

$product_id = $_GET['product_id'] ?? 0;
$error = '';
$success = '';
$product = null;

// Get product details if product_id is provided
if ($product_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get all products for dropdown
$products = [];
try {
    $stmt = $pdo->query("SELECT product_id, product_name, product_code, current_stock FROM EASYSALLES_PRODUCTS WHERE status = 'active' ORDER BY product_name");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adjust_product_id = $_POST['product_id'] ?? 0;
    $adjustment_type = $_POST['adjustment_type'] ?? 'stock_in';
    $quantity = abs(intval($_POST['quantity'] ?? 0));
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (!$adjust_product_id || $quantity <= 0) {
        $error = "Please select a product and enter a valid quantity";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get current product details
            $stmt = $pdo->prepare("SELECT product_name, current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ? FOR UPDATE");
            $stmt->execute([$adjust_product_id]);
            $current_product = $stmt->fetch();
            
            if (!$current_product) {
                throw new Exception("Product not found");
            }
            
            $previous_stock = $current_product['current_stock'];
            $quantity_change = $adjustment_type == 'stock_in' ? $quantity : -$quantity;
            $new_stock = $previous_stock + $quantity_change;
            
            // Validate new stock (cannot be negative)
            if ($new_stock < 0) {
                throw new Exception("Cannot reduce stock below 0. Current stock: {$previous_stock}, Reduction: {$quantity}");
            }
            
            // Update product stock
            $updateStmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET current_stock = ? WHERE product_id = ?");
            $updateStmt->execute([$new_stock, $adjust_product_id]);
            
            // Log inventory change
            $logStmt = $pdo->prepare("INSERT INTO EASYSALLES_INVENTORY_LOG 
                (product_id, change_type, quantity_change, previous_stock, new_stock, 
                 reference_type, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $reference_type = 'manual_adjustment';
            if ($reason) {
                $reference_type = $reason;
            }
            
            $logStmt->execute([
                $adjust_product_id, 
                $adjustment_type, 
                $quantity_change, 
                $previous_stock, 
                $new_stock,
                $reference_type,
                $notes,
                $_SESSION['user_id']
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Stock adjusted successfully!";
            
            // Refresh product data
            if ($adjust_product_id == $product_id) {
                $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
            }
            
            // Show success details
            $action_text = $adjustment_type == 'stock_in' ? 'added to' : 'removed from';
            echo '<script>showToast("' . $quantity . ' units ' . $action_text . ' stock successfully", "success");</script>';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Stock Adjustment</h2>
        <p>Add or remove stock from inventory with detailed tracking</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Adjust Stock Levels</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if ($error): ?>
                    <div class="message error-message" style="background: var(--error-light); color: var(--error); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success-message" style="background: var(--success-light); color: var(--success); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="adjustmentForm">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label class="form-label">Select Product *</label>
                                <select name="product_id" 
                                        id="productSelect" 
                                        class="form-control" 
                                        required
                                        onchange="loadProductDetails(this.value)">
                                    <option value="">-- Select a Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['product_id']; ?>" 
                                            <?php echo $product_id == $p['product_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['product_name']); ?> 
                                            (SKU: <?php echo htmlspecialchars($p['product_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Adjustment Type *</label>
                                <select name="adjustment_type" 
                                        id="adjustmentType" 
                                        class="form-control" 
                                        required
                                        onchange="updateAdjustmentColor()">
                                    <option value="stock_in" selected>Add Stock (Stock In)</option>
                                    <option value="stock_out">Remove Stock (Stock Out)</option>
                                    <option value="adjustment">Adjustment</option>
                                    <option value="damage">Damage/Loss</option>
                                    <option value="return">Customer Return</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Details Card (shown when product is selected) -->
                    <div id="productDetailsCard" style="display: <?php echo $product ? 'block' : 'none'; ?>;">
                        <div class="card" style="background: var(--bg); border: 2px solid var(--border); margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h4 class="card-title">Current Stock Information</h4>
                            </div>
                            <div style="padding: 1rem;">
                                <div class="row">
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h5 id="currentStock" style="font-size: 2rem; color: var(--primary); margin: 0;">
                                                <?php echo $product['current_stock'] ?? 0; ?>
                                            </h5>
                                            <small>Current Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h5 id="minStock" style="font-size: 1.5rem; color: var(--warning); margin: 0;">
                                                <?php echo $product['min_stock'] ?? 10; ?>
                                            </h5>
                                            <small>Min Stock Level</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h5 id="maxStock" style="font-size: 1.5rem; color: var(--success); margin: 0;">
                                                <?php echo $product['max_stock'] ?? 100; ?>
                                            </h5>
                                            <small>Max Stock Level</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h5 id="stockStatus" style="font-size: 1.2rem; margin: 0;">
                                                <?php 
                                                if ($product) {
                                                    if ($product['current_stock'] == 0) {
                                                        echo '<span style="color: var(--error);">Out of Stock</span>';
                                                    } elseif ($product['current_stock'] <= $product['min_stock']) {
                                                        echo '<span style="color: var(--warning);">Low Stock</span>';
                                                    } else {
                                                        echo '<span style="color: var(--success);">Normal</span>';
                                                    }
                                                }
                                                ?>
                                            </h5>
                                            <small>Stock Status</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Stock Progress Bar -->
                                <div style="margin-top: 1rem;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                                        <small>0</small>
                                        <small id="stockProgressLabel">Stock Level</small>
                                        <small id="maxStockLabel"><?php echo $product['max_stock'] ?? 100; ?></small>
                                    </div>
                                    <div style="height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; position: relative;">
                                        <div id="stockProgressBar" style="height: 100%; width: 0%; background: var(--primary);"></div>
                                        <div id="minStockMarker" style="position: absolute; top: -2px; width: 3px; height: 12px; background: var(--warning);"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                        <small style="color: var(--warning);">Min: <span id="minStockValue"><?php echo $product['min_stock'] ?? 10; ?></span></small>
                                        <small style="color: var(--primary);">Current: <span id="currentStockValue"><?php echo $product['current_stock'] ?? 0; ?></span></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Quantity *</label>
                                <input type="number" 
                                       name="quantity" 
                                       id="quantity" 
                                       class="form-control" 
                                       min="1" 
                                       required
                                       placeholder="Enter quantity"
                                       oninput="updateStockPreview()">
                                <small class="text-muted">Number of units to add/remove</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Reason for Adjustment</label>
                                <select name="reason" class="form-control">
                                    <option value="">-- Select Reason --</option>
                                    <option value="restock">Restock/Replenishment</option>
                                    <option value="sale">Sale/Transaction</option>
                                    <option value="damage">Damage/Expired</option>
                                    <option value="return">Customer Return</option>
                                    <option value="transfer">Store Transfer</option>
                                    <option value="correction">Inventory Correction</option>
                                    <option value="sample">Free Sample</option>
                                    <option value="promotion">Promotional Item</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Unit Type</label>
                                <input type="text" 
                                       id="unitType" 
                                       class="form-control" 
                                       readonly
                                       value="<?php echo htmlspecialchars($product['unit_type'] ?? 'piece'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Add any additional notes about this adjustment..."></textarea>
                    </div>
                    
                    <!-- Preview Card -->
                    <div class="card" id="previewCard" style="display: none; border-left: 4px solid var(--success);">
                        <div class="card-header">
                            <h4 class="card-title">Adjustment Preview</h4>
                        </div>
                        <div style="padding: 1rem;">
                            <div class="row">
                                <div class="col-4">
                                    <div style="text-align: center;">
                                        <h5 id="adjustmentAmount" style="margin: 0;"></h5>
                                        <small>Quantity to Adjust</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div style="text-align: center;">
                                        <h5 id="newStockLevel" style="margin: 0;"></h5>
                                        <small>New Stock Level</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div style="text-align: center;">
                                        <h5 id="adjustmentImpact" style="margin: 0;"></h5>
                                        <small>Impact on Status</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <i class="fas fa-check-circle"></i> Apply Adjustment
                        </button>
                        <button type="reset" class="btn btn-outline" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        <button type="button" class="btn btn-outline" onclick="quickAdd(10)">
                            <i class="fas fa-plus"></i> Quick Add 10
                        </button>
                        <button type="button" class="btn btn-outline" onclick="quickRemove(5)">
                            <i class="fas fa-minus"></i> Quick Remove 5
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Adjustments -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Recent Stock Adjustments</h3>
            </div>
            <div class="table-container">
                <?php 
                try {
                    $stmt = $pdo->prepare("SELECT l.*, p.product_name, u.username 
                                          FROM EASYSALLES_INVENTORY_LOG l
                                          LEFT JOIN EASYSALLES_PRODUCTS p ON l.product_id = p.product_id
                                          LEFT JOIN EASYSALLES_USERS u ON l.created_by = u.user_id
                                          WHERE l.change_type IN ('stock_in', 'stock_out', 'adjustment')
                                          ORDER BY l.created_at DESC 
                                          LIMIT 10");
                    $stmt->execute();
                    $recent_adjustments = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $recent_adjustments = [];
                }
                ?>
                
                <?php if (empty($recent_adjustments)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-exchange-alt" style="font-size: 3rem; color: var(--border); margin-bottom: 1rem;"></i>
                        <h4>No Recent Adjustments</h4>
                        <p class="text-muted">Adjustment history will appear here</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>From</th>
                                <th>To</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_adjustments as $adj): 
                                $change_color = $adj['quantity_change'] > 0 ? 'var(--success)' : 'var(--error)';
                                $change_icon = $adj['quantity_change'] > 0 ? 'fa-plus' : 'fa-minus';
                            ?>
                            <tr>
                                <td><?php echo date('M d', strtotime($adj['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($adj['product_name']); ?></td>
                                <td>
                                    <span style="color: <?php echo $change_color; ?>;">
                                        <i class="fas <?php echo $change_icon; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $adj['change_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: <?php echo $change_color; ?>; font-weight: 600;">
                                        <?php echo $adj['quantity_change'] > 0 ? '+' : ''; ?><?php echo $adj['quantity_change']; ?>
                                    </span>
                                </td>
                                <td><?php echo $adj['previous_stock']; ?></td>
                                <td><?php echo $adj['new_stock']; ?></td>
                                <td><?php echo htmlspecialchars($adj['username']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <!-- Quick Stock Actions -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Quick Stock Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php 
                // Get products that need attention
                try {
                    $stmt = $pdo->query("SELECT product_id, product_name, current_stock, min_stock 
                                        FROM EASYSALLES_PRODUCTS 
                                        WHERE status = 'active' AND current_stock <= min_stock
                                        ORDER BY current_stock ASC 
                                        LIMIT 5");
                    $needs_attention = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $needs_attention = [];
                }
                ?>
                
                <?php if (!empty($needs_attention)): ?>
                    <h4 style="color: var(--warning); margin-bottom: 1rem;">
                        <i class="fas fa-exclamation-triangle"></i> Needs Attention
                    </h4>
                    <?php foreach ($needs_attention as $item): ?>
                        <div style="background: var(--warning-light); padding: 0.8rem; border-radius: 8px; margin-bottom: 0.8rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                    <small>Current: <?php echo $item['current_stock']; ?> | Min: <?php echo $item['min_stock']; ?></small>
                                </div>
                                <a href="stock-adjustment.php?product_id=<?php echo $item['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">
                                    <i class="fas fa-plus"></i> Restock
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <h5 style="color: var(--primary); margin-bottom: 0.8rem;">
                        <i class="fas fa-bolt"></i> Quick Links
                    </h5>
                    <div class="row">
                        <div class="col-6">
                            <a href="../products/add.php" class="btn btn-outline" style="width: 100%; margin-bottom: 0.5rem;">
                                <i class="fas fa-plus"></i> New Product
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="low-stock.php" class="btn btn-outline" style="width: 100%; margin-bottom: 0.5rem;">
                                <i class="fas fa-list"></i> Low Stock List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Adjustment Guidelines -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> Adjustment Guidelines
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h5 style="color: var(--success); margin-bottom: 0.5rem;">
                        <i class="fas fa-plus-circle"></i> Stock In
                    </h5>
                    <ul style="color: var(--text-light); padding-left: 1rem; margin: 0; font-size: 0.9rem;">
                        <li>New inventory from suppliers</li>
                        <li>Customer returns</li>
                        <li>Transfer from other stores</li>
                    </ul>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h5 style="color: var(--error); margin-bottom: 0.5rem;">
                        <i class="fas fa-minus-circle"></i> Stock Out
                    </h5>
                    <ul style="color: var(--text-light); padding-left: 1rem; margin: 0; font-size: 0.9rem;">
                        <li>Damaged/expired goods</li>
                        <li>Free samples</li>
                        <li>Promotional giveaways</li>
                    </ul>
                </div>
                
                <div style="background: var(--primary-light); padding: 1rem; border-radius: 8px;">
                    <h5 style="color: var(--primary); margin-bottom: 0.5rem;">
                        <i class="fas fa-shield-alt"></i> Best Practices
                    </h5>
                    <ul style="color: var(--primary); padding-left: 1rem; margin: 0; font-size: 0.9rem;">
                        <li>Always document the reason</li>
                        <li>Verify counts before adjusting</li>
                        <li>Update regularly for accuracy</li>
                        <li>Review inventory reports weekly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Load product details via AJAX
    function loadProductDetails(productId) {
        if (!productId) {
            document.getElementById('productDetailsCard').style.display = 'none';
            document.getElementById('previewCard').style.display = 'none';
            return;
        }
        
        // Make AJAX request to get product details
        fetch(`get_product_details.php?id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI with product details
                    document.getElementById('currentStock').textContent = data.current_stock;
                    document.getElementById('minStock').textContent = data.min_stock;
                    document.getElementById('maxStock').textContent = data.max_stock;
                    document.getElementById('unitType').value = data.unit_type;
                    
                    // Update stock progress
                    const progress = (data.current_stock / data.max_stock) * 100;
                    document.getElementById('stockProgressBar').style.width = Math.min(progress, 100) + '%';
                    
                    const minMarker = (data.min_stock / data.max_stock) * 100;
                    document.getElementById('minStockMarker').style.left = Math.min(minMarker, 100) + '%';
                    
                    // Update labels
                    document.getElementById('maxStockLabel').textContent = data.max_stock;
                    document.getElementById('minStockValue').textContent = data.min_stock;
                    document.getElementById('currentStockValue').textContent = data.current_stock;
                    
                    // Update stock status
                    let statusText = 'Normal';
                    let statusColor = 'var(--success)';
                    if (data.current_stock == 0) {
                        statusText = 'Out of Stock';
                        statusColor = 'var(--error)';
                    } else if (data.current_stock <= data.min_stock) {
                        statusText = 'Low Stock';
                        statusColor = 'var(--warning)';
                    }
                    document.getElementById('stockStatus').innerHTML = `<span style="color: ${statusColor};">${statusText}</span>`;
                    
                    // Show the card
                    document.getElementById('productDetailsCard').style.display = 'block';
                    
                    // Update preview if quantity is entered
                    updateStockPreview();
                    
                } else {
                    showToast('Error loading product details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading product details', 'error');
            });
    }
    
    // Update stock preview based on adjustment
    function updateStockPreview() {
        const productSelect = document.getElementById('productSelect');
        const adjustmentType = document.getElementById('adjustmentType').value;
        const quantityInput = document.getElementById('quantity');
        
        if (!productSelect.value || !quantityInput.value) {
            document.getElementById('previewCard').style.display = 'none';
            return;
        }
        
        const currentStock = parseInt(document.getElementById('currentStock').textContent) || 0;
        const minStock = parseInt(document.getElementById('minStock').textContent) || 10;
        const maxStock = parseInt(document.getElementById('maxStock').textContent) || 100;
        const quantity = parseInt(quantityInput.value) || 0;
        
        const adjustment = adjustmentType === 'stock_in' ? quantity : -quantity;
        const newStock = currentStock + adjustment;
        
        // Update preview card
        document.getElementById('adjustmentAmount').innerHTML = `
            <span style="color: ${adjustment >= 0 ? 'var(--success)' : 'var(--error)'};">
                <i class="fas ${adjustment >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'}"></i>
                ${adjustment >= 0 ? '+' : ''}${adjustment}
            </span>
        `;
        
        document.getElementById('newStockLevel').innerHTML = `
            <span style="color: ${newStock < minStock ? 'var(--warning)' : 'var(--success)'};">
                ${newStock}
            </span>
        `;
        
        // Determine impact on status
        let impact = 'No Change';
        let impactColor = 'var(--text)';
        
        if (currentStock <= minStock && newStock > minStock) {
            impact = 'Stock Normalized';
            impactColor = 'var(--success)';
        } else if (currentStock > minStock && newStock <= minStock) {
            impact = 'Now Low Stock';
            impactColor = 'var(--warning)';
        } else if (currentStock > 0 && newStock === 0) {
            impact = 'Now Out of Stock';
            impactColor = 'var(--error)';
        } else if (currentStock === 0 && newStock > 0) {
            impact = 'Back in Stock';
            impactColor = 'var(--success)';
        }
        
        document.getElementById('adjustmentImpact').innerHTML = `
            <span style="color: ${impactColor};">${impact}</span>
        `;
        
        // Show preview card
        document.getElementById('previewCard').style.display = 'block';
    }
    
    // Update adjustment type color
    function updateAdjustmentColor() {
        const select = document.getElementById('adjustmentType');
        const colors = {
            'stock_in': 'var(--success)',
            'stock_out': 'var(--error)',
            'adjustment': 'var(--warning)',
            'damage': 'var(--error)',
            'return': 'var(--accent)'
        };
        
        select.style.borderColor = colors[select.value] || '';
        updateStockPreview();
    }
    
    // Quick action buttons
    function quickAdd(amount) {
        document.getElementById('adjustmentType').value = 'stock_in';
        document.getElementById('quantity').value = amount;
        updateAdjustmentColor();
        updateStockPreview();
    }
    
    function quickRemove(amount) {
        document.getElementById('adjustmentType').value = 'stock_out';
        document.getElementById('quantity').value = amount;
        updateAdjustmentColor();
        updateStockPreview();
    }
    
    // Reset form
    function resetForm() {
        document.getElementById('adjustmentForm').reset();
        document.getElementById('productDetailsCard').style.display = 'none';
        document.getElementById('previewCard').style.display = 'none';
        document.getElementById('adjustmentType').style.borderColor = '';
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($product): ?>
            loadProductDetails(<?php echo $product_id; ?>);
        <?php endif; ?>
        
        updateAdjustmentColor();
        
        // Form validation
        document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
            const productId = this.product_id.value;
            const quantity = parseInt(this.quantity.value) || 0;
            const adjustmentType = this.adjustment_type.value;
            const currentStock = parseInt(document.getElementById('currentStock').textContent) || 0;
            
            if (!productId) {
                e.preventDefault();
                alert('Please select a product');
                this.product_id.focus();
                return false;
            }
            
            if (quantity <= 0) {
                e.preventDefault();
                alert('Please enter a valid quantity (greater than 0)');
                this.quantity.focus();
                return false;
            }
            
            if (adjustmentType === 'stock_out' && quantity > currentStock) {
                e.preventDefault();
                alert(`Cannot remove ${quantity} units. Current stock is only ${currentStock} units.`);
                this.quantity.focus();
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });
    });
</script>

<!-- File: get_product_details.php (for AJAX requests) -->
<script>
    // Mock function - in production, create a separate PHP file
    function getProductDetails(productId) {
        return {
            success: true,
            current_stock: <?php echo $product['current_stock'] ?? 0; ?>,
            min_stock: <?php echo $product['min_stock'] ?? 10; ?>,
            max_stock: <?php echo $product['max_stock'] ?? 100; ?>,
            unit_type: '<?php echo $product['unit_type'] ?? 'piece'; ?>'
        };
    }
</script>

<?php require_once '../includes/footer.php'; ?>
