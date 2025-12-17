<?php
// admin/products/categories.php
$page_title = "Product Categories";
require_once '../includes/header.php';

// Check if categories table exists, create it if not
try {
    $pdo->query("SELECT 1 FROM EASYSALLES_CATEGORIES LIMIT 1");
} catch (PDOException $e) {
    // Create the table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `EASYSALLES_CATEGORIES` (
            `category_id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `category_name` varchar(100) NOT NULL UNIQUE,
            `description` text,
            `color` varchar(20) DEFAULT '#06B6D4',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ");
    
    // Migrate existing categories from products table
    $pdo->exec("
        INSERT IGNORE INTO EASYSALLES_CATEGORIES (category_name)
        SELECT DISTINCT category 
        FROM EASYSALLES_PRODUCTS 
        WHERE category IS NOT NULL AND category != ''
    ");
}

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.category_name as category,
            COUNT(p.product_id) as product_count,
            COALESCE(SUM(p.current_stock), 0) as total_stock,
            COALESCE(SUM(p.unit_price * p.current_stock), 0) as stock_value
        FROM EASYSALLES_CATEGORIES c
        LEFT JOIN EASYSALLES_PRODUCTS p ON c.category_name = p.category
        GROUP BY c.category_id, c.category_name
        ORDER BY c.category_name
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
    $error = $e->getMessage();
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category' && !empty($_POST['category_name'])) {
        $category_name = trim($_POST['category_name']);
        
        try {
            // Check if category exists in the CATEGORIES table
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_CATEGORIES WHERE category_name = ?");
            $stmt->execute([$category_name]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                // Insert into the proper categories table
                $stmt = $pdo->prepare("INSERT INTO EASYSALLES_CATEGORIES (category_name) VALUES (?)");
                $stmt->execute([$category_name]);
                
                echo '<script>alert("Category added successfully!"); window.location.reload();</script>';
            } else {
                echo '<script>alert("Category already exists!");</script>';
            }
        } catch (PDOException $e) {
            echo '<script>alert("Error adding category: ' . addslashes($e->getMessage()) . '");</script>';
        }
    }
    
    if ($action === 'edit_category' && !empty($_POST['old_name']) && !empty($_POST['new_name'])) {
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_name']);
        
        try {
            // Update category name
            $stmt = $pdo->prepare("UPDATE EASYSALLES_CATEGORIES SET category_name = ? WHERE category_name = ?");
            $stmt->execute([$new_name, $old_name]);
            
            // Update products with the new category name
            $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = ? WHERE category = ?");
            $stmt->execute([$new_name, $old_name]);
            
            echo '<script>alert("Category updated successfully!"); window.location.reload();</script>';
        } catch (PDOException $e) {
            echo '<script>alert("Error updating category: ' . addslashes($e->getMessage()) . '");</script>';
        }
    }
    
    if ($action === 'delete_category' && !empty($_POST['category_name'])) {
        $category_name = trim($_POST['category_name']);
        
        try {
            // Delete from categories table
            $stmt = $pdo->prepare("DELETE FROM EASYSALLES_CATEGORIES WHERE category_name = ?");
            $stmt->execute([$category_name]);
            
            // Set products to uncategorized
            $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = NULL WHERE category = ?");
            $stmt->execute([$category_name]);
            
            echo '<script>alert("Category deleted successfully!"); window.location.reload();</script>';
        } catch (PDOException $e) {
            echo '<script>alert("Error deleting category: ' . addslashes($e->getMessage()) . '");</script>';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --accent: #8b5cf6;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .page-title h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .page-title p {
            color: var(--text-light);
        }
        
        .page-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        
        .btn-outline:hover {
            background-color: var(--background);
        }
        
        .card {
            background-color: var(--card-bg);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .col-3 {
            grid-column: span 1;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background-color: var(--background);
            border-bottom: 2px solid var(--border);
        }
        
        .table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-light);
        }
        
        .table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-primary {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        
        .text-muted {
            color: var(--text-light);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
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
            max-width: 500px;
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

        <?php if (isset($error)): ?>
            <div class="card" style="margin-bottom: 1.5rem; background-color: #fee2e2; color: #991b1b;">
                <div style="padding: 1.5rem;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>

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
        <div class="modal-content">
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

    <!-- Hidden forms for edit and delete -->
    <form id="editCategoryForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="edit_category">
        <input type="hidden" name="old_name" id="editOldName">
        <input type="hidden" name="new_name" id="editNewName">
    </form>
    
    <form id="deleteCategoryForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="category_name" id="deleteCategoryName">
    </form>

    <script>
        // Modal functions
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'block';
        }
        
        function editCategory(categoryName) {
            const newName = prompt('Enter new name for category:', categoryName);
            if (newName && newName.trim() !== '' && newName.trim() !== categoryName) {
                document.getElementById('editOldName').value = categoryName;
                document.getElementById('editNewName').value = newName.trim();
                document.getElementById('editCategoryForm').submit();
            }
        }
        
        function deleteCategory(categoryName) {
            if (confirm(`Are you sure you want to delete the category "${categoryName}"? Products in this category will become uncategorized.`)) {
                document.getElementById('deleteCategoryName').value = categoryName;
                document.getElementById('deleteCategoryForm').submit();
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
</body>
</html>
<?php require_once '../includes/footer.php'; ?>