<?php
// admin/products/edit.php
$page_title = "Edit Product";
require_once '../includes/header.php';

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
        require_once '../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error: ' . $e->getMessage() . '</div>';
    require_once '../includes/footer.php';
    exit;
}

// Get existing categories
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM EASYSALLES_PRODUCTS WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $existing_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $existing_categories = [];
}

// Get existing suppliers
$suppliers = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT supplier FROM EASYSALLES_PRODUCTS WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
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
    $supplier = $_POST['supplier'] ?? '';
    $new_supplier = $_POST['new_supplier'] ?? '';
    $barcode = $_POST['barcode'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($product_name)) $errors[] = "Product name is required";
    if (empty($product_code)) $errors[] = "Product code/SKU is required";
    if (!is_numeric($unit_price) || $unit_price <= 0) $errors[] = "Valid unit price is required";
    if (!is_numeric($current_stock) || $current_stock < 0) $errors[] = "Valid current stock is required";
    
    // Check if product code exists for another product
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM EASYSALLES_PRODUCTS WHERE product_code = ? AND product_id != ?");
        $stmt->execute([$product_code, $product_id]);
        if ($stmt->fetch()) {
            $errors[] = "Product code already exists for another product. Please use a unique SKU.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    // Use new category if provided
    if (!empty($new_category)) {
        $category = trim($new_category);
    }
    
    // Use new supplier if provided
    if (!empty($new_supplier)) {
        $supplier = trim($new_supplier);
    }
    
    if (empty($errors)) {
        try {
            // Calculate stock change
            $old_stock = $product['current_stock'];
            $stock_change = $current_stock - $old_stock;
            
            // Update product
            $stmt = $pdo->prepare("
                UPDATE EASYSALLES_PRODUCTS 
                SET product_name = ?, product_code = ?, description = ?, category = ?,
                    unit_price = ?, cost_price = ?, current_stock = ?, min_stock = ?,
                    max_stock = ?, unit_type = ?, supplier = ?, barcode = ?,
                    image_url = ?, status = ?, updated_at = NOW()
                WHERE product_id = ?
            ");
            
            $result = $stmt->execute([
                $product_name, $product_code, $description, $category,
                $unit_price, $cost_price, $current_stock, $min_stock,
                $max_stock, $unit_type, $supplier, $barcode,
                $image_url, $status, $product_id
            ]);
            
            if ($result) {
                // Log inventory change if stock changed
                if ($stock_change != 0) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO EASYSALLES_INVENTORY_LOG 
                            (product_id, change_type, quantity_change, previous_stock, new_stock,
                             reference_type, notes, created_by) 
                            VALUES (?, ?, ?, ?, ?, 'stock_adjustment', 'Stock updated via edit', ?)
                        ");
                        $stmt->execute([
                            $product_id,
                            $stock_change > 0 ? 'stock_in' : 'stock_out',
                            abs($stock_change),
                            $old_stock,
                            $current_stock,
                            $_SESSION['user_id'] ?? 1
                        ]);
                    } catch (PDOException $e) {
                        // Log error but continue
                        error_log("Failed to log inventory change: " . $e->getMessage());
                    }
                }
                
                echo '<script>alert("Product updated successfully!"); window.location.href = "view.php?id=' . $product_id . '";</script>';
                exit;
            } else {
                $error = "Failed to update product. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EasySalles</title>
    <style>
        /* Same styles as view.php, but add form styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Edit Product</h2>
                <p>Update product information and pricing</p>
            </div>
            <div class="page-actions">
                <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-outline">
                    ← Back to Product
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Product Information</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label class="form-label">Product Name *</label>
                                <input type="text" 
                                       name="product_name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['product_name']); ?>" 
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
                                       value="<?php echo htmlspecialchars($product['product_code']); ?>" 
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
                                  placeholder="Product description (optional)"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control" id="categorySelect">
                                    <option value="">Select Category</option>
                                    <?php foreach ($existing_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                            <?php echo ($product['category'] ?? '') == $cat['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="new_category">+ Add New Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group" id="newCategoryGroup" style="display: none;">
                                <label class="form-label">New Category Name</label>
                                <input type="text" 
                                       name="new_category" 
                                       class="form-control" 
                                       placeholder="Enter new category name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Supplier</label>
                                <select name="supplier" class="form-control" id="supplierSelect">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?php echo htmlspecialchars($sup['supplier']); ?>"
                                            <?php echo ($product['supplier'] ?? '') == $sup['supplier'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sup['supplier']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="new_supplier">+ Add New Supplier</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group" id="newSupplierGroup" style="display: none;">
                                <label class="form-label">New Supplier Name</label>
                                <input type="text" 
                                       name="new_supplier" 
                                       class="form-control" 
                                       placeholder="Enter new supplier name">
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
                                       value="<?php echo htmlspecialchars($product['unit_price']); ?>" 
                                       required 
                                       step="0.01" 
                                       min="0.01">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Cost Price ($)</label>
                                <input type="number" 
                                       name="cost_price" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['cost_price'] ?? ''); ?>" 
                                       step="0.01" 
                                       min="0">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Unit Type</label>
                                <select name="unit_type" class="form-control">
                                    <option value="piece" <?php echo $product['unit_type'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                    <option value="kg" <?php echo $product['unit_type'] == 'kg' ? 'selected' : ''; ?>>Kilogram</option>
                                    <option value="liter" <?php echo $product['unit_type'] == 'liter' ? 'selected' : ''; ?>>Liter</option>
                                    <option value="pack" <?php echo $product['unit_type'] == 'pack' ? 'selected' : ''; ?>>Pack</option>
                                    <option value="box" <?php echo $product['unit_type'] == 'box' ? 'selected' : ''; ?>>Box</option>
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
                                       value="<?php echo htmlspecialchars($product['current_stock']); ?>" 
                                       required 
                                       min="0">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Min. Stock Level</label>
                                <input type="number" 
                                       name="min_stock" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['min_stock']); ?>" 
                                       min="0">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Max. Stock Level</label>
                                <input type="number" 
                                       name="max_stock" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['max_stock']); ?>" 
                                       min="0">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="discontinued" <?php echo $product['status'] == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Barcode</label>
                                <input type="text" 
                                       name="barcode" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>" 
                                       placeholder="Optional barcode">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Image URL</label>
                                <input type="url" 
                                       name="image_url" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" 
                                       placeholder="https://example.com/image.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            💾 Save Changes
                        </button>
                        <button type="reset" class="btn btn-outline">
                            🔄 Reset Form
                        </button>
                        <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-outline">
                            ❌ Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle new category field
        document.getElementById('categorySelect').addEventListener('change', function() {
            const newCategoryGroup = document.getElementById('newCategoryGroup');
            if (this.value === 'new_category') {
                newCategoryGroup.style.display = 'block';
                newCategoryGroup.querySelector('input').focus();
            } else {
                newCategoryGroup.style.display = 'none';
            }
        });
        
        // Toggle new supplier field
        document.getElementById('supplierSelect').addEventListener('change', function() {
            const newSupplierGroup = document.getElementById('newSupplierGroup');
            if (this.value === 'new_supplier') {
                newSupplierGroup.style.display = 'block';
                newSupplierGroup.querySelector('input').focus();
            } else {
                newSupplierGroup.style.display = 'none';
            }
        });
        
        // Validate form
        document.querySelector('form').addEventListener('submit', function(e) {
            const unitPrice = parseFloat(this.unit_price.value) || 0;
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
            
            return true;
        });
    </script>
</body>
</html>
