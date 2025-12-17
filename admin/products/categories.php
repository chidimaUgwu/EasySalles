<?php

// Turn on full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin/products/categories.php
$page_title = "Product Categories";
require_once '../includes/header.php';

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT 
            category,
            COUNT(*) as product_count,
            SUM(current_stock) as total_stock,
            SUM(unit_price * current_stock) as stock_value
        FROM EASYSALLES_PRODUCTS 
        WHERE category IS NOT NULL AND category != ''
        GROUP BY category 
        ORDER BY category
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category' && !empty($_POST['category_name'])) {
        $category_name = trim($_POST['category_name']);
        
        try {
            // Check if category exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE category = ?");
            $stmt->execute([$category_name]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                // Update one product to create the category
                $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = ? WHERE product_id = (SELECT MIN(product_id) FROM EASYSALLES_PRODUCTS)");
                $stmt->execute([$category_name]);
                
                echo '<script>alert("Category added successfully!"); window.location.reload();</script>';
            } else {
                echo '<script>alert("Category already exists!");</script>';
            }
        } catch (PDOException $e) {
            echo '<script>alert("Error adding category: ' . addslashes($e->getMessage()) . '");</script>';
        }
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
        /* Use same styles as view.php */
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Product Categories</h2>
                <p>Manage product categories and view category statistics</p>
            </div>
            <div class="page-actions">
                <a href="index.php" class="btn btn-outline">
                    ← Back to Products
                </a>
                <button onclick="openAddCategoryModal()" class="btn btn-primary">
                    ➕ Add Category
                </button>
            </div>
        </div>

        <?php if (empty($categories)): ?>
            <div class="card">
                <div style="text-align: center; padding: 4rem;">
                    <span style="font-size: 4rem; color: var(--text-light); opacity: 0.5;">📂</span>
                    <h3 style="margin: 1rem 0;">No Categories Found</h3>
                    <p class="text-muted">Start by adding your first product category</p>
                    <button onclick="openAddCategoryModal()" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Add Your First Category
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Categories Stats -->
            <div class="row" style="margin-bottom: 1.5rem;">
                <div class="col-3">
                    <div class="card" style="text-align: center;">
                        <div style="padding: 1.5rem;">
                            <h1 style="color: var(--primary); margin: 0;"><?php echo count($categories); ?></h1>
                            <small class="text-muted">Total Categories</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card" style="text-align: center;">
                        <div style="padding: 1.5rem;">
                            <h1 style="color: var(--success); margin: 0;">
                                <?php 
                                $total_products = 0;
                                foreach ($categories as $cat) {
                                    $total_products += $cat['product_count'];
                                }
                                echo $total_products;
                                ?>
                            </h1>
                            <small class="text-muted">Total Products</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card" style="text-align: center;">
                        <div style="padding: 1.5rem;">
                            <h1 style="color: var(--warning); margin: 0;">
                                <?php 
                                $total_stock = 0;
                                foreach ($categories as $cat) {
                                    $total_stock += $cat['total_stock'];
                                }
                                echo $total_stock;
                                ?>
                            </h1>
                            <small class="text-muted">Total Stock</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card" style="text-align: center;">
                        <div style="padding: 1.5rem;">
                            <h1 style="color: var(--accent); margin: 0;">
                                $<?php 
                                $total_value = 0;
                                foreach ($categories as $cat) {
                                    $total_value += $cat['stock_value'];
                                }
                                echo number_format($total_value, 2);
                                ?>
                            </h1>
                            <small class="text-muted">Total Value</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Categories</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Products</th>
                                <th>Total Stock</th>
                                <th>Stock Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['category']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $category['product_count']; ?> products</span>
                                </td>
                                <td><?php echo $category['total_stock'] ?? 0; ?></td>
                                <td>
                                    <strong>$<?php echo number_format($category['stock_value'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="index.php?category=<?php echo urlencode($category['category']); ?>" 
                                           class="btn btn-outline" 
                                           style="padding: 0.4rem 0.8rem;"
                                           title="View Products">
                                            👁️
                                        </a>
                                        <button onclick="editCategory('<?php echo addslashes($category['category']); ?>')" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem;"
                                                title="Edit Category">
                                            ✏️
                                        </button>
                                        <button onclick="deleteCategory('<?php echo addslashes($category['category']); ?>')" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem; color: var(--error);"
                                                title="Delete Category">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <span class="modal-close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="categoryForm">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" 
                               name="category_name" 
                               class="form-control" 
                               placeholder="Enter category name"
                               required
                               autofocus>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            💾 Save Category
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('addCategoryModal')">
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'block';
        }
        
        function editCategory(categoryName) {
            const newName = prompt('Enter new name for category:', categoryName);
            if (newName && newName.trim() !== categoryName) {
                // Update via AJAX or redirect
                window.location.href = `update_category.php?old=${encodeURIComponent(categoryName)}&new=${encodeURIComponent(newName.trim())}`;
            }
        }
        
        function deleteCategory(categoryName) {
            if (confirm(`Are you sure you want to delete the category "${categoryName}"? Products in this category will become uncategorized.`)) {
                window.location.href = `delete_category.php?category=${encodeURIComponent(categoryName)}`;
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
    </script>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-close {
            color: var(--text-light);
            font-size: 1.8rem;
            cursor: pointer;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: var(--text);
        }
    </style>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
