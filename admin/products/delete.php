<?php
// admin/products/delete.php
$page_title = "Delete Product";
require_once '../../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = intval($_GET['id']);

// Get product details
try {
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
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

// Check if product has sales history
$has_sales = false;
$sales_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_SALES_ITEMS WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $sales_count = $stmt->fetchColumn();
    $has_sales = $sales_count > 0;
} catch (PDOException $e) {
    // Table might not exist yet
}

// Handle deletion
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmation = $_POST['confirmation'] ?? '';
    $delete_type = $_POST['delete_type'] ?? 'soft';
    
    if ($confirmation !== 'DELETE') {
        $error = "Please type 'DELETE' to confirm deletion.";
    } else {
        try {
            if ($delete_type === 'hard') {
                // Hard delete - remove product completely
                // First, delete sales items
                if ($has_sales) {
                    $stmt = $pdo->prepare("DELETE FROM EASYSALLES_SALES_ITEMS WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                }
                
                // Delete inventory logs
                $stmt = $pdo->prepare("DELETE FROM EASYSALLES_INVENTORY_LOG WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                // Delete product
                $stmt = $pdo->prepare("DELETE FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                $success = "Product permanently deleted along with all related records.";
            } else {
                // Soft delete - mark as discontinued
                $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET status = 'discontinued', updated_at = NOW() WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                $success = "Product marked as discontinued. It will no longer appear in active product lists.";
            }
            
            echo '<script>
                setTimeout(function() {
                    window.location.href = "index.php";
                }, 2000);
            </script>';
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Delete Product</h2>
        <p>Remove or disable a product from your inventory</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-eye"></i> View Details
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="color: var(--error);">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Product Deletion
                </h3>
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
                        <p style="margin-top: 0.5rem;">Redirecting to products list...</p>
                    </div>
                <?php endif; ?>
                
                <!-- Product Summary -->
                <div style="padding: 1.5rem; background: var(--error-light); border-radius: 10px; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-light), var(--accent-light)); 
                                        border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-box" style="font-size: 2rem; color: var(--primary);"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h3 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <p style="margin: 0; color: var(--text);">
                                SKU: <?php echo htmlspecialchars($product['product_code']); ?>
                                <?php if ($product['category']): ?>
                                    • Category: <?php echo htmlspecialchars($product['category']); ?>
                                <?php endif; ?>
                            </p>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-light);">
                                Price: $<?php echo number_format($product['unit_price'], 2); ?> • 
                                Stock: <?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Warning Messages -->
                <?php if ($has_sales): ?>
                <div style="padding: 1rem; background: var(--warning-light); border-radius: 10px; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning); font-size: 1.5rem; margin-top: 0.2rem;"></i>
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--warning);">Sales History Detected</h4>
                            <p style="margin: 0;">
                                This product has been sold <?php echo $sales_count; ?> time(s). 
                                <?php if ($sales_count > 0): ?>
                                    Deleting this product will remove all sales records associated with it.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($product['current_stock'] > 0): ?>
                <div style="padding: 1rem; background: var(--warning-light); border-radius: 10px; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <i class="fas fa-boxes" style="color: var(--warning); font-size: 1.5rem; margin-top: 0.2rem;"></i>
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--warning);">Stock Remaining</h4>
                            <p style="margin: 0;">
                                There are <?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?> of this product in stock 
                                valued at $<?php echo number_format($product['current_stock'] * $product['unit_price'], 2); ?>.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Deletion Options -->
                <?php if (!$success): ?>
                <form method="POST" action="" id="deleteForm">
                    <div style="margin-bottom: 2rem;">
                        <h4>Deletion Options</h4>
                        
                        <div style="margin: 1rem 0;">
                            <div class="radio-group">
                                <label class="radio-label" style="display: block; margin-bottom: 1rem;">
                                    <input type="radio" 
                                           name="delete_type" 
                                           value="soft" 
                                           checked
                                           onchange="updateDeletionInfo()">
                                    <span class="radio-custom"></span>
                                    <span style="margin-left: 0.5rem;">
                                        <strong>Soft Delete (Recommended)</strong>
                                        <div style="margin-left: 1.8rem; margin-top: 0.3rem; color: var(--text-light);">
                                            Mark product as "Discontinued" - keeps sales history and records intact.
                                            Product will be hidden from active lists but can be restored if needed.
                                        </div>
                                    </span>
                                </label>
                                
                                <label class="radio-label" style="display: block;">
                                    <input type="radio" 
                                           name="delete_type" 
                                           value="hard"
                                           onchange="updateDeletionInfo()">
                                    <span class="radio-custom"></span>
                                    <span style="margin-left: 0.5rem;">
                                        <strong>Hard Delete (Permanent)</strong>
                                        <div style="margin-left: 1.8rem; margin-top: 0.3rem; color: var(--text-light);">
                                            Permanently delete product and all associated records.
                                            This action cannot be undone. Use with extreme caution.
                                        </div>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deletion Impact -->
                    <div id="deletionImpact" style="padding: 1rem; background: var(--bg); border-radius: 10px; margin-bottom: 1.5rem;">
                        <h5>Deletion Impact:</h5>
                        <ul style="color: var(--text-light); margin: 0.5rem 0 0 1rem;">
                            <li>Product will be marked as discontinued</li>
                            <li>Sales history will be preserved</li>
                            <li>Product can be restored if needed</li>
                        </ul>
                    </div>
                    
                    <!-- Confirmation -->
                    <div style="margin-bottom: 2rem;">
                        <h4>Final Confirmation</h4>
                        <p>To confirm deletion, please type <strong>DELETE</strong> in the box below:</p>
                        
                        <div class="form-group" style="max-width: 300px;">
                            <input type="text" 
                                   name="confirmation" 
                                   class="form-control" 
                                   placeholder="Type DELETE here"
                                   required
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-error">
                            <i class="fas fa-trash"></i> Confirm Deletion
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.location.href='view.php?id=<?php echo $product_id; ?>'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.location.href='edit.php?id=<?php echo $product_id; ?>'">
                            <i class="fas fa-edit"></i> Edit Instead
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Alternative Actions -->
        <?php if (!$success): ?>
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lightbulb"></i> Consider These Alternatives
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="width: 60px; height: 60px; background: var(--warning-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-ban" style="color: var(--warning); font-size: 1.5rem;"></i>
                            </div>
                            <h4>Set to Inactive</h4>
                            <p style="font-size: 0.9rem; color: var(--text-light);">
                                Keep product data but hide from sales
                            </p>
                            <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-outline" style="margin-top: 0.5rem;">
                                Make Inactive
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="width: 60px; height: 60px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-tag" style="color: var(--primary); font-size: 1.5rem;"></i>
                            </div>
                            <h4>Update Pricing</h4>
                            <p style="font-size: 0.9rem; color: var(--text-light);">
                                Adjust price instead of deleting
                            </p>
                            <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-outline" style="margin-top: 0.5rem;">
                                Edit Product
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="width: 60px; height: 60px; background: var(--accent-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-archive" style="color: var(--accent); font-size: 1.5rem;"></i>
                            </div>
                            <h4>Archive Product</h4>
                            <p style="font-size: 0.9rem; color: var(--text-light);">
                                Move to archive for future reference
                            </p>
                            <button class="btn btn-outline" style="margin-top: 0.5rem;" onclick="archiveProduct()">
                                Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-4">
        <!-- Deletion Statistics -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i> Product Statistics
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <small class="text-muted">Current Stock Value</small>
                    <h3 style="color: var(--success); margin: 0.5rem 0;">
                        $<?php echo number_format($product['current_stock'] * $product['unit_price'], 2); ?>
                    </h3>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <small class="text-muted">Cost Value</small>
                    <h3 style="color: var(--warning); margin: 0.5rem 0;">
                        $<?php echo number_format($product['current_stock'] * ($product['cost_price'] ?? 0), 2); ?>
                    </h3>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <small class="text-muted">Total Sales</small>
                    <h3 style="margin: 0.5rem 0;"><?php echo $sales_count; ?> sales</h3>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <small class="text-muted">Product Age</small>
                    <h3 style="margin: 0.5rem 0;">
                        <?php 
                        $created = new DateTime($product['created_at']);
                        $now = new DateTime();
                        $interval = $created->diff($now);
                        echo $interval->format('%a days');
                        ?>
                    </h3>
                </div>
                
                <div style="padding: 1rem; background: var(--bg); border-radius: 10px;">
                    <h5 style="margin-bottom: 0.5rem;">Quick Info</h5>
                    <p style="margin: 0.3rem 0; font-size: 0.9rem;">
                        <strong>Created:</strong> <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                    </p>
                    <p style="margin: 0.3rem 0; font-size: 0.9rem;">
                        <strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($product['updated_at'] ?? $product['created_at'])); ?>
                    </p>
                    <p style="margin: 0.3rem 0; font-size: 0.9rem;">
                        <strong>Status:</strong> <?php echo ucfirst($product['status']); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Warning Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header" style="background: var(--error-light);">
                <h3 class="card-title" style="color: var(--error);">
                    <i class="fas fa-exclamation-circle"></i> Important Notes
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <ul style="color: var(--text); padding-left: 1rem; margin: 0;">
                    <li>Deletion affects sales reports</li>
                    <li>Inventory history will be lost</li>
                    <li>Cannot be undone (hard delete)</li>
                    <li>Affects product analytics</li>
                    <li>Consider seasonality before deleting</li>
                </ul>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--error-light); border-radius: 10px;">
                    <h5 style="color: var(--error); margin-bottom: 0.5rem;">
                        <i class="fas fa-shield-alt"></i> Data Protection
                    </h5>
                    <p style="margin: 0.3rem 0; font-size: 0.9rem;">
                        Always backup your data before performing permanent deletions.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-link"></i> Quick Links
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; gap: 0.8rem;">
                    <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-outline">
                        <i class="fas fa-eye"></i> View Product Details
                    </a>
                    
                    <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-outline">
                        <i class="fas fa-edit"></i> Edit Product Instead
                    </a>
                    
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-list"></i> Back to Products List
                    </a>
                    
                    <a href="../reports/products.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i> View Product Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update deletion impact information
    function updateDeletionInfo() {
        const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
        const impactDiv = document.getElementById('deletionImpact');
        
        if (deleteType === 'soft') {
            impactDiv.innerHTML = `
                <h5>Deletion Impact (Soft Delete):</h5>
                <ul style="color: var(--text-light); margin: 0.5rem 0 0 1rem;">
                    <li>Product will be marked as "Discontinued"</li>
                    <li>All sales history will be preserved</li>
                    <li>Product can be restored at any time</li>
                    <li>Will not appear in active product lists</li>
                    <li>Inventory records remain intact</li>
                </ul>
            `;
        } else {
            impactDiv.innerHTML = `
                <h5 style="color: var(--error);">Deletion Impact (Hard Delete):</h5>
                <ul style="color: var(--text-light); margin: 0.5rem 0 0 1rem;">
                    <li><strong style="color: var(--error);">Product will be permanently deleted</strong></li>
                    <li>All sales records for this product will be removed</li>
                    <li><strong style="color: var(--error);">This action cannot be undone</strong></li>
                    <li>Inventory history will be lost</li>
                    <li>Will affect sales reports and analytics</li>
                </ul>
                <div style="margin-top: 1rem; padding: 0.8rem; background: var(--error-light); border-radius: 8px; color: var(--error);">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> Permanent deletion is irreversible. Proceed with extreme caution.
                </div>
            `;
        }
    }
    
    // Archive product function
    function archiveProduct() {
        if (confirm('Archive this product? It will be moved to archived products list.')) {
            // Send archive request
            fetch('archive-product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=<?php echo $product_id; ?>&action=archive`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Product archived successfully', 'success');
                    setTimeout(() => window.location.href = 'index.php', 1500);
                } else {
                    showToast(data.error || 'Failed to archive product', 'error');
                }
            })
            .catch(error => {
                showToast('Error archiving product', 'error');
            });
        }
    }
    
    // Form validation
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        const confirmation = this.confirmation.value;
        const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
        
        if (confirmation !== 'DELETE') {
            e.preventDefault();
            showToast('Please type DELETE to confirm deletion', 'error');
            this.confirmation.focus();
            return false;
        }
        
        if (deleteType === 'hard') {
            if (!confirm('⚠️ WARNING: You are about to PERMANENTLY delete this product and all related records. This action CANNOT be undone. Are you absolutely sure?')) {
                e.preventDefault();
                return false;
            }
        }
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        // Disable cancel button
        const cancelBtn = this.querySelector('button[type="button"]');
        if (cancelBtn) cancelBtn.disabled = true;
    });
    
    // Initialize deletion info
    document.addEventListener('DOMContentLoaded', function() {
        updateDeletionInfo();
        
        // Focus confirmation field
        const confirmationField = document.querySelector('input[name="confirmation"]');
        if (confirmationField) {
            confirmationField.focus();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key to cancel
        if (e.key === 'Escape') {
            window.location.href = 'view.php?id=<?php echo $product_id; ?>';
        }
        
        // Ctrl+Enter to submit
        if (e.ctrlKey && e.key === 'Enter') {
            const form = document.getElementById('deleteForm');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.click();
            }
        }
    });
</script>

<style>
    .radio-group {
        margin: 1rem 0;
    }
    
    .radio-label {
        display: flex;
        align-items: flex-start;
        cursor: pointer;
        padding: 1rem;
        border: 2px solid var(--border);
        border-radius: 10px;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .radio-label:hover {
        border-color: var(--primary-light);
        background: var(--bg);
    }
    
    .radio-label input[type="radio"] {
        display: none;
    }
    
    .radio-custom {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-radius: 50%;
        margin-top: 0.2rem;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .radio-label input[type="radio"]:checked + .radio-custom {
        border-color: var(--primary);
    }
    
    .radio-label input[type="radio"]:checked + .radio-custom::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 10px;
        height: 10px;
        background: var(--primary);
        border-radius: 50%;
    }
    
    .radio-label input[type="radio"]:checked ~ span {
        color: var(--primary);
    }
    
    .btn-error {
        background: var(--error);
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        transition: background 0.3s ease;
    }
    
    .btn-error:hover {
        background: var(--error-dark);
    }
    
    @keyframes warningPulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    .warning-pulse {
        animation: warningPulse 2s infinite;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>
