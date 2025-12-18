<?php
// admin/products/categories.php
$page_title = "Product Categories";

// Start output buffering to capture output
ob_start();

require_once '../includes/header.php';

// Initialize session messages
if (!isset($_SESSION['category_messages'])) {
    $_SESSION['category_messages'] = [];
}

// Get categories
$categories = [];
try {
    // First, get categories from the CATEGORIES table
    $stmt = $pdo->query("
        SELECT 
            c.category_id,
            c.category_name,
            c.description,
            c.color,
            c.created_at,
            COUNT(p.product_id) as product_count,
            SUM(p.current_stock) as total_stock,
            SUM(p.unit_price * p.current_stock) as stock_value
        FROM EASYSALLES_CATEGORIES c
        LEFT JOIN EASYSALLES_PRODUCTS p ON c.category_name = p.category
        GROUP BY c.category_id, c.category_name, c.description, c.color, c.created_at
        ORDER BY c.category_name
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // If categories table doesn't exist, create it
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS EASYSALLES_CATEGORIES (
                category_id INT AUTO_INCREMENT PRIMARY KEY,
                category_name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                color VARCHAR(20) DEFAULT '#06B6D4',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Also, make sure products table has category field
            $pdo->exec("ALTER TABLE EASYSALLES_PRODUCTS 
                        MODIFY COLUMN category VARCHAR(100) DEFAULT NULL");
        } catch (PDOException $createError) {
            $_SESSION['category_messages'][] = [
                'type' => 'error',
                'text' => "Error creating categories table: " . $createError->getMessage()
            ];
        }
    }
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect = false;
    
    if ($action === 'add_category' && !empty($_POST['category_name'])) {
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#06B6D4';
        
        try {
            // Check if category exists in CATEGORIES table
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_CATEGORIES WHERE category_name = ?");
            $stmt->execute([$category_name]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                // Insert into CATEGORIES table
                $stmt = $pdo->prepare("
                    INSERT INTO EASYSALLES_CATEGORIES 
                    (category_name, description, color, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$category_name, $description, $color]);
                
                $_SESSION['category_messages'][] = [
                    'type' => 'success',
                    'text' => "Category '$category_name' added successfully!"
                ];
                
                $redirect = true;
            } else {
                $_SESSION['category_messages'][] = [
                    'type' => 'warning',
                    'text' => "Category '$category_name' already exists!"
                ];
            }
        } catch (PDOException $e) {
            $_SESSION['category_messages'][] = [
                'type' => 'error',
                'text' => "Error adding category: " . $e->getMessage()
            ];
        }
    } elseif ($action === 'edit_category' && !empty($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
        $category_name = trim($_POST['category_name']);
        $old_category_name = $_POST['old_category_name'] ?? '';
        
        try {
            // Update category in CATEGORIES table
            $stmt = $pdo->prepare("
                UPDATE EASYSALLES_CATEGORIES 
                SET category_name = ?, description = ?, color = ?
                WHERE category_id = ?
            ");
            $stmt->execute([$category_name, $_POST['description'] ?? '', $_POST['color'] ?? '#06B6D4', $category_id]);
            
            // Also update category in PRODUCTS table if name changed
            if ($old_category_name && $old_category_name !== $category_name) {
                $stmt = $pdo->prepare("
                    UPDATE EASYSALLES_PRODUCTS 
                    SET category = ? 
                    WHERE category = ?
                ");
                $stmt->execute([$category_name, $old_category_name]);
            }
            
            $_SESSION['category_messages'][] = [
                'type' => 'success',
                'text' => "Category updated successfully!"
            ];
            
            $redirect = true;
        } catch (PDOException $e) {
            $_SESSION['category_messages'][] = [
                'type' => 'error',
                'text' => "Error updating category: " . $e->getMessage()
            ];
        }
    } elseif ($action === 'delete_category' && !empty($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
        $category_name = $_POST['category_name'] ?? '';
        
        try {
            // First, set products with this category to NULL
            $stmt = $pdo->prepare("
                UPDATE EASYSALLES_PRODUCTS 
                SET category = NULL 
                WHERE category = ?
            ");
            $stmt->execute([$category_name]);
            
            // Then delete the category
            $stmt = $pdo->prepare("DELETE FROM EASYSALLES_CATEGORIES WHERE category_id = ?");
            $stmt->execute([$category_id]);
            
            $_SESSION['category_messages'][] = [
                'type' => 'success',
                'text' => "Category deleted successfully! Products have been uncategorized."
            ];
            
            $redirect = true;
        } catch (PDOException $e) {
            $_SESSION['category_messages'][] = [
                'type' => 'error',
                'text' => "Error deleting category: " . $e->getMessage()
            ];
        }
    }
    
    // If redirect is needed, clear output buffer and redirect
    if ($redirect) {
        ob_end_clean(); // Clear the output buffer
        header("Location: categories.php");
        exit();
    }
}

// Get and clear messages
$messages = $_SESSION['category_messages'] ?? [];
$_SESSION['category_messages'] = [];

// End output buffering and get the header content
$header_content = ob_get_clean();
echo $header_content;
?>

<div class="page-header">
    <div class="page-title">
        <h2>Product Categories</h2>
        <p>Manage product categories and view category statistics</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <button onclick="openAddCategoryModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>
</div>

<!-- Display messages -->
<?php foreach ($messages as $message): ?>
    <div class="message <?php echo $message['type']; ?>-message" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 10px; background: var(--<?php echo $message['type']; ?>-light); color: var(--<?php echo $message['type']; ?>);">
        <i class="fas fa-<?php 
            echo $message['type'] === 'success' ? 'check-circle' : 
                 ($message['type'] === 'error' ? 'exclamation-circle' : 
                 ($message['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
        ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
<?php endforeach; ?>

<?php if (empty($categories)): ?>
    <div class="card">
        <div style="text-align: center; padding: 4rem;">
            <span style="font-size: 4rem; color: var(--text-light); opacity: 0.5;">
                <i class="fas fa-folder"></i>
            </span>
            <h3 style="margin: 1rem 0;">No Categories Found</h3>
            <p class="text-muted">Start by adding your first product category</p>
            <button onclick="openAddCategoryModal()" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Add Your First Category
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
                            $total_stock += $cat['total_stock'] ?? 0;
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
                            $total_value += $cat['stock_value'] ?? 0;
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
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <strong>
                                <span class="category-color" style="display: inline-block; width: 12px; height: 12px; background: <?php echo htmlspecialchars($category['color'] ?? '#06B6D4'); ?>; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></span>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </strong>
                            <?php if ($category['description']): ?>
                                <p class="text-muted" style="margin: 0.25rem 0 0 0; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>
                                    <?php if (strlen($category['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?php echo $category['product_count'] ?? 0; ?> products</span>
                        </td>
                        <td><?php echo $category['total_stock'] ?? 0; ?></td>
                        <td>
                            <strong>$<?php echo number_format($category['stock_value'] ?? 0, 2); ?></strong>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="index.php?category=<?php echo urlencode($category['category_name']); ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="View Products">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                        class="btn btn-outline" 
                                        style="padding: 0.4rem 0.8rem;"
                                        title="Edit Category">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                        class="btn btn-outline" 
                                        style="padding: 0.4rem 0.8rem; color: var(--error);"
                                        title="Delete Category">
                                    <i class="fas fa-trash"></i>
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
                
                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Category description"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" 
                           name="color" 
                           class="form-control" 
                           style="height: 40px; padding: 5px;"
                           value="#06B6D4">
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addCategoryModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Category</h3>
            <span class="modal-close" onclick="closeModal('editCategoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editCategoryForm">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                <input type="hidden" name="old_category_name" id="edit_old_category_name">
                
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" 
                           name="category_name" 
                           id="edit_category_name"
                           class="form-control" 
                           placeholder="Enter category name"
                           required
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" 
                              id="edit_description"
                              class="form-control" 
                              rows="3" 
                              placeholder="Category description"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" 
                           name="color" 
                           id="edit_color"
                           class="form-control" 
                           style="height: 40px; padding: 5px;"
                           value="#06B6D4">
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editCategoryModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div id="deleteCategoryModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header" style="border-bottom-color: var(--error-light);">
            <h3 style="color: var(--error);">Delete Category</h3>
            <span class="modal-close" onclick="closeModal('deleteCategoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <p id="deleteMessage" style="margin-bottom: 1.5rem;">
                Are you sure you want to delete this category? 
                All products in this category will become uncategorized.
            </p>
            <form method="POST" action="" id="deleteCategoryForm">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_category_id">
                <input type="hidden" name="category_name" id="delete_category_name">
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteCategoryModal')">
                        <i class="fas fa-times"></i> Cancel
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
    
    function editCategory(category) {
        document.getElementById('edit_category_id').value = category.category_id;
        document.getElementById('edit_old_category_name').value = category.category_name;
        document.getElementById('edit_category_name').value = category.category_name;
        document.getElementById('edit_description').value = category.description || '';
        document.getElementById('edit_color').value = category.color || '#06B6D4';
        document.getElementById('editCategoryModal').style.display = 'block';
    }
    
    function deleteCategory(category) {
        document.getElementById('delete_category_id').value = category.category_id;
        document.getElementById('delete_category_name').value = category.category_name;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete the category "<strong>${category.category_name}</strong>"?<br>
            All products in this category will become uncategorized.`;
        document.getElementById('deleteCategoryModal').style.display = 'block';
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
    
    // Handle form submissions
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        const categoryName = this.category_name.value.trim();
        if (!categoryName) {
            e.preventDefault();
            showToast('Please enter a category name', 'warning');
            return false;
        }
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
    });
    
    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        const categoryName = this.category_name.value.trim();
        if (!categoryName) {
            e.preventDefault();
            showToast('Please enter a category name', 'warning');
            return false;
        }
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
    });
    
    document.getElementById('deleteCategoryForm').addEventListener('submit', function(e) {
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        submitBtn.disabled = true;
    });
    
    // Auto-hide messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }, 5000);
        });
        
        // Focus on modal inputs when opened
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'style') {
                    const modal = mutation.target;
                    if (modal.style.display === 'block') {
                        const input = modal.querySelector('input[autofocus]');
                        if (input) {
                            input.focus();
                        }
                    }
                }
            });
        });
        
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            observer.observe(modal, { attributes: true });
        });
    });
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
        overflow: auto;
    }
    
    .modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 0;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        animation: modalFadeIn 0.3s;
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
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Message animations */
    .message {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>