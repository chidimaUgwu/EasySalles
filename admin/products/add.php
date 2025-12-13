<?php
// admin/products/add.php
$page_title = "Add New Product";
require_once '../includes/header.php';

$error = '';
$success = '';

// Get categories for dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM EASYSALLES_PRODUCTS WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $existing_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $existing_categories = [];
}

// Get suppliers
$suppliers = [];
try {
    $stmt = $pdo->query("SELECT supplier_id, supplier_name FROM EASYSALLES_SUPPLIERS WHERE status = 'active' ORDER BY supplier_name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Suppliers table might not exist yet
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'] ?? '';
    $product_code = $_POST['product_code'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $new_category = $_POST['new_category'] ?? '';
    $unit_price = $_POST['unit_price'] ?? 0;
    $cost_price = $_POST['cost_price'] ?? 0;
    $current_stock = $_POST['current_stock'] ?? 0;
    $min_stock = $_POST['min_stock'] ?? 10;
    $max_stock = $_POST['max_stock'] ?? 100;
    $unit_type = $_POST['unit_type'] ?? 'piece';
    $supplier_id = $_POST['supplier_id'] ?? '';
    $barcode = $_POST['barcode'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($product_name)) $errors[] = "Product name is required";
    if (empty($product_code)) $errors[] = "Product code/SKU is required";
    if (!is_numeric($unit_price) || $unit_price <= 0) $errors[] = "Valid unit price is required";
    if (!is_numeric($current_stock) || $current_stock < 0) $errors[] = "Valid current stock is required";
    
    // Check if product code exists
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM EASYSALLES_PRODUCTS WHERE product_code = ?");
        $stmt->execute([$product_code]);
        if ($stmt->fetch()) {
            $errors[] = "Product code already exists. Please use a unique SKU.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    // Use new category if provided
    if (!empty($new_category)) {
        $category = trim($new_category);
    }
    
    if (empty($errors)) {
        try {
            // Insert product
            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_PRODUCTS 
                (product_code, product_name, description, category, unit_price, cost_price,
                 current_stock, min_stock, max_stock, unit_type, supplier_id, barcode,
                 image_url, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $product_code, $product_name, $description, $category, $unit_price, $cost_price,
                $current_stock, $min_stock, $max_stock, $unit_type, $supplier_id, $barcode,
                $image_url, $status, $_SESSION['user_id']
            ]);
            
            if ($result) {
                $product_id = $pdo->lastInsertId();
                
                // Log inventory change
                $stmt = $pdo->prepare("INSERT INTO EASYSALLES_INVENTORY_LOG 
                    (product_id, change_type, quantity_change, previous_stock, new_stock,
                     reference_type, notes, created_by) 
                    VALUES (?, 'stock_in', ?, 0, ?, 'product_add', 'Initial stock added', ?)");
                $stmt->execute([$product_id, $current_stock, $current_stock, $_SESSION['user_id']]);
                
                $success = "Product added successfully!";
                
                // Show success with product details
                echo '<script>showToast("Product added successfully!", "success");</script>';
                
                // Clear form for next entry
                $_POST = [];
                
            } else {
                $error = "Failed to add product. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Generate product code if not provided
$default_product_code = "PROD-" . date('Ymd') . "-" . strtoupper(substr(uniqid(), -6));
?>

<div class="page-header">
    <div class="page-title">
        <h2>Add New Product</h2>
        <p>Add products to your inventory with pricing and stock information</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Product Information</h3>
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
                        <div style="margin-top: 0.5rem;">
                            <a href="add.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                <i class="fas fa-plus"></i> Add Another Product
                            </a>
                            <a href="index.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-left: 0.5rem;">
                                <i class="fas fa-list"></i> View All Products
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="productForm">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label class="form-label">Product Name *</label>
                                <input type="text" 
                                       name="product_name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" 
                                       required 
                                       placeholder="Enter product name"
                                       autofocus>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Product Code/SKU *</label>
                                <input type="text" 
                                       name="product_code" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['product_code'] ?? $default_product_code); ?>" 
                                       required 
                                       placeholder="e.g., PROD-001">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Product description (optional)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <select name="category" 
                                            class="form-control" 
                                            id="categorySelect"
                                            onchange="toggleNewCategory()">
                                        <option value="">Select Category</option>
                                        <?php foreach ($existing_categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                                <?php echo ($_POST['category'] ?? '') == $cat['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="new_category" 
                                            <?php echo isset($_POST['new_category']) ? 'selected' : ''; ?>>
                                            + Add New Category
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group" id="newCategoryGroup" style="display: none;">
                                <label class="form-label">New Category Name</label>
                                <input type="text" 
                                       name="new_category" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['new_category'] ?? ''); ?>" 
                                       placeholder="Enter new category name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Unit Price ($) *</label>
                                <input type="number" 
                                       name="unit_price" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>" 
                                       required 
                                       step="0.01" 
                                       min="0.01"
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Cost Price ($)</label>
                                <input type="number" 
                                       name="cost_price" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>" 
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Unit Type</label>
                                <select name="unit_type" class="form-control">
                                    <option value="piece" <?php echo ($_POST['unit_type'] ?? 'piece') == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                    <option value="kg" <?php echo ($_POST['unit_type'] ?? '') == 'kg' ? 'selected' : ''; ?>>Kilogram</option>
                                    <option value="liter" <?php echo ($_POST['unit_type'] ?? '') == 'liter' ? 'selected' : ''; ?>>Liter</option>
                                    <option value="pack" <?php echo ($_POST['unit_type'] ?? '') == 'pack' ? 'selected' : ''; ?>>Pack</option>
                                    <option value="box" <?php echo ($_POST['unit_type'] ?? '') == 'box' ? 'selected' : ''; ?>>Box</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Current Stock *</label>
                                <input type="number" 
                                       name="current_stock" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['current_stock'] ?? '0'); ?>" 
                                       required 
                                       min="0"
                                       placeholder="0">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Min. Stock Level</label>
                                <input type="number" 
                                       name="min_stock" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['min_stock'] ?? '10'); ?>" 
                                       min="0"
                                       placeholder="10">
                                <small class="text-muted">Low stock alert trigger</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Max. Stock Level</label>
                                <input type="number" 
                                       name="max_stock" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['max_stock'] ?? '100'); ?>" 
                                       min="0"
                                       placeholder="100">
                                <small class="text-muted">Maximum storage capacity</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo ($_POST['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($_POST['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-top: 1rem;">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Barcode</label>
                                <input type="text" 
                                       name="barcode" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['barcode'] ?? ''); ?>" 
                                       placeholder="Optional barcode">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-control">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>"
                                            <?php echo ($_POST['supplier_id'] ?? '') == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Image URL</label>
                                <input type="url" 
                                       name="image_url" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['image_url'] ?? ''); ?>" 
                                       placeholder="https://example.com/image.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <i class="fas fa-save"></i> Save Product
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        <button type="button" class="btn btn-outline" onclick="generateBarcode()">
                            <i class="fas fa-barcode"></i> Generate Barcode
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <!-- Product Preview -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Product Preview</h3>
            </div>
            <div style="padding: 1.5rem; text-align: center;">
                <div id="productPreviewImage" 
                     style="width: 200px; height: 200px; margin: 0 auto 1rem; 
                            background: linear-gradient(135deg, var(--primary-light), var(--accent-light)); 
                            border-radius: 12px; display: flex; align-items: center; justify-content: center;
                            overflow: hidden;">
                    <i class="fas fa-box" style="font-size: 4rem; color: var(--primary);"></i>
                </div>
                
                <h4 id="previewName">Product Name</h4>
                <p id="previewCode" class="text-muted">SKU: PROD-CODE</p>
                
                <div style="display: flex; justify-content: center; gap: 1rem; margin: 1rem 0;">
                    <span class="badge badge-primary" id="previewCategory">Category</span>
                    <span class="badge badge-success" id="previewStatus">Active</span>
                </div>
                
                <div style="background: var(--bg); padding: 1rem; border-radius: 10px; margin-top: 1rem;">
                    <h3 id="previewPrice" style="color: var(--success); margin: 0;">$0.00</h3>
                    <small>Unit Price</small>
                </div>
            </div>
        </div>
        
        <!-- Quick Tips -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lightbulb"></i> Quick Tips
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <ul style="color: var(--text-light); padding-left: 1rem; margin: 0;">
                    <li>Use descriptive product names</li>
                    <li>Keep SKU codes unique and consistent</li>
                    <li>Set realistic min/max stock levels</li>
                    <li>Regularly update cost prices for accurate profit margins</li>
                    <li>Add product images for better identification</li>
                </ul>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--accent-light); border-radius: 10px;">
                    <h5 style="color: var(--accent); margin-bottom: 0.5rem;">
                        <i class="fas fa-calculator"></i> Profit Calculator
                    </h5>
                    <div id="profitCalc">
                        <p style="margin: 0.3rem 0;">Enter price details to see profit margin</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle new category field
    function toggleNewCategory() {
        const select = document.getElementById('categorySelect');
        const newCategoryGroup = document.getElementById('newCategoryGroup');
        
        if (select.value === 'new_category') {
            newCategoryGroup.style.display = 'block';
            newCategoryGroup.querySelector('input').focus();
        } else {
            newCategoryGroup.style.display = 'none';
        }
        updatePreview();
    }
    
    // Update product preview in real-time
    function updatePreview() {
        const form = document.forms.productForm;
        
        // Update preview elements
        document.getElementById('previewName').textContent = 
            form.product_name.value || 'Product Name';
        document.getElementById('previewCode').textContent = 
            'SKU: ' + (form.product_code.value || 'PROD-CODE');
        document.getElementById('previewCategory').textContent = 
            form.category.value || (form.new_category.value || 'Uncategorized');
        document.getElementById('previewStatus').textContent = 
            form.status.value || 'Active';
        document.getElementById('previewPrice').textContent = 
            '$' + (parseFloat(form.unit_price.value) || 0).toFixed(2);
        
        // Update image preview
        const previewImage = document.getElementById('productPreviewImage');
        if (form.image_url.value) {
            previewImage.innerHTML = `<img src="${form.image_url.value}" alt="Product Image" style="width: 100%; height: 100%; object-fit: cover;">`;
        } else {
            previewImage.innerHTML = '<i class="fas fa-box" style="font-size: 4rem; color: var(--primary);"></i>';
        }
        
        // Update profit calculator
        const unitPrice = parseFloat(form.unit_price.value) || 0;
        const costPrice = parseFloat(form.cost_price.value) || 0;
        if (unitPrice > 0 && costPrice > 0) {
            const profit = unitPrice - costPrice;
            const margin = (profit / unitPrice) * 100;
            document.getElementById('profitCalc').innerHTML = `
                <p style="margin: 0.3rem 0;"><strong>Profit per unit:</strong> $${profit.toFixed(2)}</p>
                <p style="margin: 0.3rem 0;"><strong>Margin:</strong> ${margin.toFixed(1)}%</p>
            `;
        }
    }
    
    // Generate barcode
    function generateBarcode() {
        const barcodeField = document.querySelector('input[name="barcode"]');
        const sku = document.querySelector('input[name="product_code"]').value || 'PROD';
        const random = Math.random().toString(36).substr(2, 8).toUpperCase();
        barcodeField.value = sku + '-' + random;
        updatePreview();
    }
    
    // Initialize preview
    document.addEventListener('DOMContentLoaded', function() {
        // Attach event listeners to all form fields
        const form = document.forms.productForm;
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });
        
        // Initial preview update
        updatePreview();
        toggleNewCategory();
        
        // Auto-focus product name
        form.product_name.focus();
        
        // Calculate profit when prices change
        const priceInputs = ['unit_price', 'cost_price'];
        priceInputs.forEach(name => {
            const input = form[name];
            input.addEventListener('input', function() {
                const unitPrice = parseFloat(form.unit_price.value) || 0;
                const costPrice = parseFloat(form.cost_price.value) || 0;
                if (unitPrice < costPrice) {
                    this.style.borderColor = 'var(--error)';
                    showToast('Unit price should be higher than cost price for profit', 'warning');
                } else {
                    this.style.borderColor = '';
                }
            });
        });
    });
    
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const unitPrice = parseFloat(this.unit_price.value) || 0;
        const costPrice = parseFloat(this.cost_price.value) || 0;
        const currentStock = parseInt(this.current_stock.value) || 0;
        const minStock = parseInt(this.min_stock.value) || 0;
        const maxStock = parseInt(this.max_stock.value) || 0;
        
        if (unitPrice <= 0) {
            e.preventDefault();
            alert('Unit price must be greater than 0');
            this.unit_price.focus();
            return false;
        }
        
        if (currentStock < 0) {
            e.preventDefault();
            alert('Current stock cannot be negative');
            this.current_stock.focus();
            return false;
        }
        
        if (minStock >= maxStock) {
            e.preventDefault();
            alert('Minimum stock level should be less than maximum stock level');
            this.min_stock.focus();
            return false;
        }
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
    });
</script>

<?php require_once '../includes/footer.php'; ?>
