<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $product_code = trim($_POST['product_code']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = $_POST['category_id'] ?: 1;
        $price = floatval($_POST['price']);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $current_stock = intval($_POST['current_stock']);
        $min_stock_level = intval($_POST['min_stock_level']);
        $unit = $_POST['unit'];
        $barcode = trim($_POST['barcode']);
        
        try {
            Database::query(
                "INSERT INTO easysalles_products 
                (product_code, name, description, category_id, price, cost_price, 
                 current_stock, min_stock_level, unit, barcode, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$product_code, $name, $description, $category_id, $price, $cost_price,
                 $current_stock, $min_stock_level, $unit, $barcode, $_SESSION['user_id']]
            );
            
            $product_id = Database::getInstance()->lastInsertId();
            
            // Log stock history
            Database::query(
                "INSERT INTO easysalles_stock_history 
                (product_id, previous_stock, new_stock, change_type, notes, changed_by) 
                VALUES (?, 0, ?, 'purchase', 'Initial stock', ?)",
                [$product_id, $current_stock, $_SESSION['user_id']]
            );
            
            $_SESSION['success'] = "Product added successfully!";
            header('Location: products.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding product: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_stock'])) {
        $product_id = $_POST['product_id'];
        $adjustment = intval($_POST['adjustment']);
        $notes = trim($_POST['notes']);
        
        // Get current stock
        $product = Database::query(
            "SELECT current_stock FROM easysalles_products WHERE id = ?",
            [$product_id]
        )->fetch();
        
        $new_stock = $product['current_stock'] + $adjustment;
        
        Database::query(
            "UPDATE easysalles_products SET current_stock = ? WHERE id = ?",
            [$new_stock, $product_id]
        );
        
        // Log stock history
        Database::query(
            "INSERT INTO easysalles_stock_history 
            (product_id, previous_stock, new_stock, change_type, notes, changed_by) 
            VALUES (?, ?, ?, 'adjustment', ?, ?)",
            [$product_id, $product['current_stock'], $new_stock, $notes, $_SESSION['user_id']]
        );
        
        $_SESSION['success'] = "Stock updated successfully!";
    }
    
    if (isset($_POST['toggle_status'])) {
        $product_id = $_POST['product_id'];
        $is_active = $_POST['is_active'];
        
        Database::query(
            "UPDATE easysalles_products SET is_active = ? WHERE id = ?",
            [$is_active, $product_id]
        );
        
        $_SESSION['success'] = "Product status updated!";
    }
}

// Get categories
$categories = Database::query("SELECT * FROM easysalles_categories ORDER BY name")->fetchAll();

// Get products with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($category_filter) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

// Get total count
$total = Database::query(
    "SELECT COUNT(*) as count FROM easysalles_products p $where",
    $params
)->fetch()['count'];

$total_pages = ceil($total / $limit);

// Get products
$products = Database::query(
    "SELECT p.*, c.name as category_name, 
            CASE 
                WHEN p.current_stock <= 0 THEN 'out_of_stock'
                WHEN p.current_stock <= p.min_stock_level THEN 'low_stock'
                ELSE 'in_stock'
            END as stock_status
     FROM easysalles_products p 
     LEFT JOIN easysalles_categories c ON p.category_id = c.id 
     $where 
     ORDER BY p.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
)->fetchAll();

// Get stock alerts (low stock items)
$stock_alerts = Database::query(
    "SELECT COUNT(*) as count FROM easysalles_products 
     WHERE current_stock <= min_stock_level AND is_active = 1"
)->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        .stock-in_stock { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stock-low_stock { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stock-out_of_stock { background: rgba(239, 68, 68, 0.1); color: var(--error); }
        
        .product-image {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .filters-bar {
            background: var(--surface);
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .quick-stat {
            background: var(--surface);
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .quick-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .quick-stat-label {
            font-size: 0.875rem;
            color: var(--text-light);
        }
    </style>
</head>
<body class="dashboard-layout">
    <!-- Include Sidebar -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div class="search-bar">
                <svg class="icon search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" placeholder="Search products...">
            </div>
            <?php include '../includes/user_menu.php'; ?>
        </header>
        
        <div class="content-area">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h1>Product Management</h1>
                <button class="btn btn-primary" onclick="openModal('addProductModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Add Product
                </button>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $total; ?></div>
                    <div class="quick-stat-label">Total Products</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $stock_alerts; ?></div>
                    <div class="quick-stat-label">Low Stock Items</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value">
                        <?php 
                            $active_products = Database::query(
                                "SELECT COUNT(*) as count FROM easysalles_products WHERE is_active = 1"
                            )->fetch()['count'];
                            echo $active_products;
                        ?>
                    </div>
                    <div class="quick-stat-label">Active Products</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value">
                        <?php 
                            $total_value = Database::query(
                                "SELECT SUM(price * current_stock) as total FROM easysalles_products"
                            )->fetch()['total'];
                            echo formatCurrency($total_value ?? 0);
                        ?>
                    </div>
                    <div class="quick-stat-label">Inventory Value</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by name, code, barcode..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                    
                    <select name="category" style="min-width: 150px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="stock_status" style="min-width: 150px;">
                        <option value="">All Stock Status</option>
                        <option value="in_stock">In Stock</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="products.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Products (<?php echo $total; ?>)</h2>
                    <div class="table-actions">
                        <button class="btn btn-secondary" onclick="openModal('bulkUpdateModal')">
                            Bulk Update
                        </button>
                        <button class="btn btn-secondary" onclick="exportProducts()">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                            Export
                        </button>
                    </div>
                </div>
                
                <?php if (empty($products)): ?>
                    <div style="padding: 3rem; text-align: center; color: var(--text-light);">
                        <svg style="width: 48px; height: 48px; fill: var(--text-lighter); margin-bottom: 1rem;" viewBox="0 0 24 24">
                            <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                        </svg>
                        <h3>No products found</h3>
                        <p>Add your first product to get started</p>
                        <button class="btn btn-primary" onclick="openModal('addProductModal')" style="margin-top: 1rem;">
                            Add Product
                        </button>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="product-image">
                                            <?php echo getInitials($product['name']); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <?php if ($product['description']): ?>
                                                <div style="font-size: 0.75rem; color: var(--text-light); max-width: 200px;">
                                                    <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>
                                                    <?php if (strlen($product['description']) > 50): ?>...<?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: var(--bg); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($product['product_code']); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="background: <?php echo $product['color_code'] ?? '#7C3AED'; ?>20; 
                                                 color: <?php echo $product['color_code'] ?? '#7C3AED'; ?>;
                                                 padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;">
                                    <?php echo formatCurrency($product['price']); ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="stock-indicator stock-<?php echo $product['stock_status']; ?>">
                                            <?php 
                                                switch($product['stock_status']) {
                                                    case 'in_stock': echo '✅'; break;
                                                    case 'low_stock': echo '⚠️'; break;
                                                    case 'out_of_stock': echo '❌'; break;
                                                }
                                            ?>
                                            <?php echo $product['current_stock']; ?> <?php echo $product['unit']; ?>
                                        </span>
                                        <?php if ($product['stock_status'] === 'low_stock'): ?>
                                            <span style="font-size: 0.75rem; color: var(--warning);">
                                                Min: <?php echo $product['min_stock_level']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $product['is_active'] ? '0' : '1'; ?>">
                                        <button type="submit" name="toggle_status" class="status-badge <?php echo $product['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="table-actions-cell">
                                    <button class="btn btn-secondary btn-sm" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        Edit
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="openStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['current_stock']; ?>)">
                                        Stock
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div style="padding: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: center; gap: 0.5rem;">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?>" class="btn btn-secondary btn-sm">First</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?>" class="btn btn-secondary btn-sm">Previous</a>
                        <?php endif; ?>
                        
                        <span style="padding: 0.5rem 1rem; background: var(--bg); border-radius: var(--radius-sm);">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?>" class="btn btn-secondary btn-sm">Next</a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?>" class="btn btn-secondary btn-sm">Last</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Add Product Modal -->
    <div class="modal" id="addProductModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="modal-close" onclick="closeModal('addProductModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Product Code *</label>
                            <input type="text" name="product_code" required 
                                   placeholder="PROD-001" 
                                   value="PROD-<?php echo str_pad($total + 1, 3, '0', STR_PAD_LEFT); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="name" required placeholder="Product Name">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Product description..."></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Unit</label>
                            <select name="unit">
                                <option value="pcs">Pieces</option>
                                <option value="kg">Kilogram</option>
                                <option value="g">Gram</option>
                                <option value="L">Liter</option>
                                <option value="ml">Milliliter</option>
                                <option value="box">Box</option>
                                <option value="pack">Pack</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Selling Price *</label>
                            <input type="number" name="price" required step="0.01" min="0" placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label>Cost Price</label>
                            <input type="number" name="cost_price" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Initial Stock *</label>
                            <input type="number" name="current_stock" required min="0" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label>Min Stock Level</label>
                            <input type="number" name="min_stock_level" min="0" value="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Barcode (Optional)</label>
                        <input type="text" name="barcode" placeholder="123456789012">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Update Stock Modal -->
    <div class="modal" id="updateStockModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Update Stock</h3>
                <button class="modal-close" onclick="closeModal('updateStockModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="stock_product_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product</label>
                        <input type="text" id="stock_product_name" readonly style="background: var(--bg);">
                    </div>
                    
                    <div class="form-group">
                        <label>Current Stock</label>
                        <input type="text" id="current_stock_display" readonly style="background: var(--bg);">
                    </div>
                    
                    <div class="form-group">
                        <label>Adjustment Type</label>
                        <select id="adjustment_type" onchange="updateAdjustmentLabel()">
                            <option value="add">Add Stock (+)</option>
                            <option value="remove">Remove Stock (-)</option>
                            <option value="set">Set Stock (Set to)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label id="adjustment_label">Quantity to Add</label>
                        <input type="number" name="adjustment" id="adjustment_input" required min="1" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2" placeholder="Reason for stock adjustment..."></textarea>
                    </div>
                    
                    <div style="background: var(--bg); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
                        <strong>New Stock will be: <span id="new_stock_preview">0</span></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateStockModal')">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bulk Update Modal -->
    <div class="modal" id="bulkUpdateModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Bulk Operations</h3>
                <button class="modal-close" onclick="closeModal('bulkUpdateModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Operation</label>
                    <select id="bulk_operation">
                        <option value="update_price">Update Prices</option>
                        <option value="update_category">Update Categories</option>
                        <option value="update_stock">Bulk Stock Update</option>
                        <option value="export">Export Selected</option>
                    </select>
                </div>
                
                <div id="bulk_content">
                    <p>Select products first from the table using checkboxes.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUpdateModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="executeBulkOperation()">Execute</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // Stock update functions
    let currentStock = 0;
    
    function openStockModal(productId, productName, stock) {
        currentStock = stock;
        document.getElementById('stock_product_id').value = productId;
        document.getElementById('stock_product_name').value = productName;
        document.getElementById('current_stock_display').value = stock;
        updateAdjustmentLabel();
        calculateNewStock();
        openModal('updateStockModal');
    }
    
    function updateAdjustmentLabel() {
        const type = document.getElementById('adjustment_type').value;
        const label = document.getElementById('adjustment_label');
        const input = document.getElementById('adjustment_input');
        
        switch(type) {
            case 'add':
                label.textContent = 'Quantity to Add';
                input.min = 1;
                input.value = 1;
                break;
            case 'remove':
                label.textContent = 'Quantity to Remove';
                input.min = 1;
                input.max = currentStock;
                input.value = 1;
                break;
            case 'set':
                label.textContent = 'Set Stock To';
                input.min = 0;
                input.value = currentStock;
                break;
        }
        calculateNewStock();
    }
    
    function calculateNewStock() {
        const type = document.getElementById('adjustment_type').value;
        const adjustment = parseInt(document.getElementById('adjustment_input').value) || 0;
        let newStock = currentStock;
        
        switch(type) {
            case 'add':
                newStock = currentStock + adjustment;
                break;
            case 'remove':
                newStock = currentStock - adjustment;
                break;
            case 'set':
                newStock = adjustment;
                break;
        }
        
        document.getElementById('new_stock_preview').textContent = newStock;
        
        // Color code the preview
        const preview = document.getElementById('new_stock_preview');
        if (newStock <= 0) {
            preview.style.color = 'var(--error)';
        } else if (newStock <= 10) {
            preview.style.color = 'var(--warning)';
        } else {
            preview.style.color = 'var(--success)';
        }
    }
    
    // Listen for input changes
    document.getElementById('adjustment_input').addEventListener('input', calculateNewStock);
    document.getElementById('adjustment_type').addEventListener('change', calculateNewStock);
    
    // Product management functions
    function editProduct(productId) {
        // Load product data via AJAX and open edit modal
        fetch(`../api/products.php?action=get&id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate edit form
                    // Open edit modal
                    alert('Edit functionality coming soon!');
                }
            });
    }
    
    function deleteProduct(productId) {
        if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
            fetch(`../api/products.php?action=delete&id=${productId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }
    
    function exportProducts() {
        // Get current filters
        const search = new URLSearchParams(window.location.search).get('search') || '';
        const category = new URLSearchParams(window.location.search).get('category') || '';
        
        // Open export in new tab
        window.open(`../api/export.php?type=products&search=${encodeURIComponent(search)}&category=${category}`, '_blank');
    }
    
    // Bulk operations
    function executeBulkOperation() {
        const operation = document.getElementById('bulk_operation').value;
        alert(`Bulk ${operation} functionality coming soon!`);
        closeModal('bulkUpdateModal');
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener for adjustment input
        const adjustmentInput = document.getElementById('adjustment_input');
        if (adjustmentInput) {
            adjustmentInput.addEventListener('input', calculateNewStock);
        }
    });
    </script>
</body>
</html>
