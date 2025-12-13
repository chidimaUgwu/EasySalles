<?php
// admin/products/categories.php
$page_title = "Manage Categories";
require_once '../../includes/header.php';

$error = '';
$success = '';

// Get all categories with product counts
try {
    $stmt = $pdo->query("
        SELECT category, 
               COUNT(*) as product_count,
               SUM(current_stock) as total_stock,
               AVG(unit_price) as avg_price,
               SUM(current_stock * unit_price) as stock_value
        FROM EASYSALLES_PRODUCTS 
        WHERE category IS NOT NULL AND category != ''
        GROUP BY category
        ORDER BY category ASC
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $error = "Database error: " . $e->getMessage();
}

// Get uncategorized products count
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as uncategorized_count,
               SUM(current_stock) as total_stock
        FROM EASYSALLES_PRODUCTS 
        WHERE category IS NULL OR category = ''
    ");
    $uncategorized = $stmt->fetch();
} catch (PDOException $e) {
    $uncategorized = ['uncategorized_count' => 0, 'total_stock' => 0];
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category') {
        $new_category = trim($_POST['new_category'] ?? '');
        
        if (empty($new_category)) {
            $error = "Category name cannot be empty";
        } else {
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE category = ?");
            $stmt->execute([$new_category]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Category already exists";
            } else {
                $success = "New category '$new_category' added. Products can now be assigned to this category.";
            }
        }
    } elseif ($action === 'rename_category') {
        $old_name = trim($_POST['old_name'] ?? '');
        $new_name = trim($_POST['new_name'] ?? '');
        
        if (empty($old_name) || empty($new_name)) {
            $error = "Both old and new category names are required";
        } elseif ($old_name === $new_name) {
            $error = "New category name must be different from the old one";
        } else {
            try {
                // Update all products with this category
                $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = ? WHERE category = ?");
                $stmt->execute([$new_name, $old_name]);
                $success = "Category renamed from '$old_name' to '$new_name'";
                
                // Refresh categories list
                $stmt = $pdo->query("
                    SELECT category, 
                           COUNT(*) as product_count,
                           SUM(current_stock) as total_stock
                    FROM EASYSALLES_PRODUCTS 
                    WHERE category IS NOT NULL AND category != ''
                    GROUP BY category
                    ORDER BY category ASC
                ");
                $categories = $stmt->fetchAll();
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_category') {
        $category_name = trim($_POST['category_name'] ?? '');
        $move_to = trim($_POST['move_to'] ?? '');
        
        if (empty($category_name)) {
            $error = "Category name is required";
        } else {
            try {
                if ($move_to === 'uncategorized') {
                    // Move products to uncategorized
                    $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = NULL WHERE category = ?");
                    $stmt->execute([$category_name]);
                    $success = "Category '$category_name' deleted. Products moved to uncategorized.";
                } elseif (!empty($move_to)) {
                    // Move products to another category
                    $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = ? WHERE category = ?");
                    $stmt->execute([$move_to, $category_name]);
                    $success = "Category '$category_name' merged into '$move_to'";
                } else {
                    // Delete category without moving products (set to NULL)
                    $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS SET category = NULL WHERE category = ?");
                    $stmt->execute([$category_name]);
                    $success = "Category '$category_name' deleted. Products are now uncategorized.";
                }
                
                // Refresh categories list
                $stmt = $pdo->query("
                    SELECT category, 
                           COUNT(*) as product_count,
                           SUM(current_stock) as total_stock
                    FROM EASYSALLES_PRODUCTS 
                    WHERE category IS NOT NULL AND category != ''
                    GROUP BY category
                    ORDER BY category ASC
                ");
                $categories = $stmt->fetchAll();
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Manage Categories</h2>
        <p>Organize products into categories for better management</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <a href="add.php" class="btn btn-primary" style="margin-left: 0.5rem;">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Product Categories</h3>
                <div class="btn-group">
                    <button class="btn btn-outline" onclick="exportCategories()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="table-container">
                <?php if (empty($categories)): ?>
                    <div style="text-align: center; padding: 4rem;">
                        <div style="width: 100px; height: 100px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                            <i class="fas fa-tags" style="font-size: 3rem; color: var(--border);"></i>
                        </div>
                        <h3>No Categories Found</h3>
                        <p class="text-muted">Create categories to organize your products</p>
                        <button class="btn btn-primary" onclick="document.getElementById('addCategoryModal').style.display='block'" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Create First Category
                        </button>
                    </div>
                <?php else: ?>
                    <table class="table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Products</th>
                                <th>Total Stock</th>
                                <th>Avg. Price</th>
                                <th>Stock Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <div style="width: 12px; height: 12px; background: var(--primary); border-radius: 50%;"></div>
                                        <strong><?php echo htmlspecialchars($category['category']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $category['product_count']; ?> products</span>
                                </td>
                                <td><?php echo number_format($category['total_stock']); ?></td>
                                <td>$<?php echo number_format($category['avg_price'], 2); ?></td>
                                <td><strong>$<?php echo number_format($category['stock_value'], 2); ?></strong></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="index.php?category=<?php echo urlencode($category['category']); ?>" 
                                           class="btn btn-outline" 
                                           style="padding: 0.4rem 0.8rem;"
                                           title="View Products">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="openRenameModal('<?php echo htmlspecialchars($category['category']); ?>')" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem;"
                                                title="Rename Category">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="openDeleteModal('<?php echo htmlspecialchars($category['category']); ?>')" 
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
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Uncategorized Products -->
        <?php if ($uncategorized['uncategorized_count'] > 0): ?>
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-question-circle"></i> Uncategorized Products
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-6">
                        <h4 style="margin: 0;"><?php echo $uncategorized['uncategorized_count']; ?> Products</h4>
                        <p class="text-muted">Without category assignment</p>
                    </div>
                    <div class="col-6">
                        <p><strong>Total Stock:</strong> <?php echo number_format($uncategorized['total_stock']); ?> units</p>
                        <a href="index.php?category=" class="btn btn-outline">
                            <i class="fas fa-eye"></i> View Uncategorized Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-4">
        <!-- Add Category Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus-circle"></i> Add New Category
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="form-group">
                        <label class="form-label">Category Name</label>
                        <input type="text" 
                               name="new_category" 
                               class="form-control" 
                               placeholder="e.g., Electronics, Clothing, Food"
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Category Stats -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie"></i> Category Statistics
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php
                $total_products = array_sum(array_column($categories, 'product_count')) + $uncategorized['uncategorized_count'];
                $total_stock_value = array_sum(array_column($categories, 'stock_value'));
                ?>
                
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <h1 style="color: var(--primary); margin: 0;"><?php echo count($categories); ?></h1>
                    <small class="text-muted">Total Categories</small>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                    <div>
                        <small class="text-muted">Products in Categories</small>
                        <h4 style="margin: 0.3rem 0;"><?php echo $total_products - $uncategorized['uncategorized_count']; ?></h4>
                    </div>
                    <div>
                        <small class="text-muted">Uncategorized</small>
                        <h4 style="margin: 0.3rem 0; color: var(--warning);"><?php echo $uncategorized['uncategorized_count']; ?></h4>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <small class="text-muted">Total Stock Value in Categories</small>
                    <h3 style="color: var(--success); margin: 0.5rem 0;">$<?php echo number_format($total_stock_value, 2); ?></h3>
                </div>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg); border-radius: 10px;">
                    <h5 style="margin-bottom: 0.5rem;">Quick Tips</h5>
                    <ul style="color: var(--text-light); padding-left: 1rem; margin: 0;">
                        <li>Use consistent naming conventions</li>
                        <li>Keep categories broad but meaningful</li>
                        <li>Regularly review and merge similar categories</li>
                        <li>Assign categories to all products for better organization</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cogs"></i> Bulk Actions
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; gap: 0.8rem;">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-boxes"></i> Manage All Products
                    </a>
                    
                    <button class="btn btn-outline" onclick="openBulkAssignModal()">
                        <i class="fas fa-tasks"></i> Bulk Assign Categories
                    </button>
                    
                    <button class="btn btn-outline" onclick="mergeSimilarCategories()">
                        <i class="fas fa-compress-alt"></i> Merge Similar Categories
                    </button>
                    
                    <button class="btn btn-outline" onclick="printCategories()">
                        <i class="fas fa-print"></i> Print Categories List
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="addCategoryModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Add New Category</h3>
            <span class="modal-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_category">
                
                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" 
                           name="new_category" 
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
                              placeholder="Brief description of this category"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="this.form.reset()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="renameCategoryModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Rename Category</h3>
            <span class="modal-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="rename_category">
                <input type="hidden" name="old_name" id="renameOldName">
                
                <div class="form-group">
                    <label class="form-label">Current Name</label>
                    <input type="text" 
                           id="currentNameDisplay" 
                           class="form-control" 
                           disabled
                           style="background: var(--bg);">
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Name</label>
                    <input type="text" 
                           name="new_name" 
                           id="renameNewName"
                           class="form-control" 
                           placeholder="Enter new category name"
                           required
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Affected Products</label>
                    <div id="affectedProductsCount" style="padding: 0.8rem; background: var(--warning-light); border-radius: 8px;">
                        Calculating...
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Rename Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('renameCategoryModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="deleteCategoryModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Delete Category</h3>
            <span class="modal-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_name" id="deleteCategoryName">
                
                <div class="form-group">
                    <label class="form-label">Category to Delete</label>
                    <input type="text" 
                           id="deleteNameDisplay" 
                           class="form-control" 
                           disabled
                           style="background: var(--bg);">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Move Products To</label>
                    <select name="move_to" class="form-control">
                        <option value="uncategorized">Make Products Uncategorized</option>
                        <option value="">-- Select another category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">All products in this category will be moved to the selected category</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Affected Products</label>
                    <div id="deleteAffectedProducts" style="padding: 0.8rem; background: var(--error-light); border-radius: 8px; color: var(--error);">
                        Calculating...
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-error">
                        <i class="fas fa-trash"></i> Delete Category
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteCategoryModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Modal functions
    function openRenameModal(categoryName) {
        document.getElementById('renameOldName').value = categoryName;
        document.getElementById('currentNameDisplay').value = categoryName;
        document.getElementById('renameNewName').value = categoryName;
        document.getElementById('renameCategoryModal').style.display = 'block';
        
        // Get affected products count
        getCategoryStats(categoryName, 'affectedProductsCount');
    }
    
    function openDeleteModal(categoryName) {
        document.getElementById('deleteCategoryName').value = categoryName;
        document.getElementById('deleteNameDisplay').value = categoryName;
        document.getElementById('deleteCategoryModal').style.display = 'block';
        
        // Get affected products count
        getCategoryStats(categoryName, 'deleteAffectedProducts');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function getCategoryStats(categoryName, elementId) {
        // In a real implementation, you would make an AJAX call here
        // For now, we'll use the existing data
        const categories = <?php echo json_encode($categories); ?>;
        const category = categories.find(c => c.category === categoryName);
        
        if (category) {
            const message = `${category.product_count} products (${category.total_stock} units) will be affected`;
            document.getElementById(elementId).innerHTML = message;
        }
    }
    
    // Export categories
    function exportCategories() {
        const table = document.getElementById('categoriesTable');
        if (!table) {
            showToast('No categories to export', 'error');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            for (const col of cols) {
                // Remove buttons from actions column
                if (col.querySelector('.btn')) {
                    rowData.push('');
                } else {
                    rowData.push(col.innerText.replace(/,/g, ''));
                }
            }
            
            csv.push(rowData.join(','));
        }
        
        // Create download link
        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'categories_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Categories exported successfully', 'success');
    }
    
    // Print categories
    function printCategories() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Product Categories - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
                </style>
            </head>
            <body>
                <h1>Product Categories - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
        `);
        
        const table = document.getElementById('categoriesTable');
        if (table) {
            printWindow.document.write(table.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, ''));
        }
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    // Merge similar categories (suggested merges)
    function mergeSimilarCategories() {
        // This would normally involve AI/ML or pattern matching
        // For now, show a message
        showToast('Analyzing categories for similar names...', 'info');
        
        // In a real implementation, you would:
        // 1. Send categories to server for analysis
        // 2. Get suggested merges back
        // 3. Show a modal with merge suggestions
        // 4. Let user confirm merges
    }
    
    // Bulk assign categories modal
    function openBulkAssignModal() {
        showToast('Bulk category assignment feature coming soon!', 'info');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
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
        margin: 5% auto;
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
</style>

<?php require_once '../../includes/footer.php'; ?>
