<?php
// admin/products/index.php
$page_title = "Manage Products";
require_once '../includes/header.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Get categories for filter
try {
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM EASYSALLES_PRODUCTS WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Build query
$query = "SELECT * FROM EASYSALLES_PRODUCTS WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (product_name LIKE ? OR product_code LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
}

if (!empty($status) && in_array($status, ['active', 'inactive', 'discontinued'])) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY product_id DESC";

// Get products
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total products count
$total_products = count($products);
?>

<div class="page-header">
    <div class="page-title">
        <h2>Manage Products</h2>
        <p>View and manage all products in your inventory</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Product
        </a>
        <a href="categories.php" class="btn btn-secondary ml-2">
            <i class="fas fa-tags"></i> Manage Categories
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-light">
                <i class="fas fa-boxes text-primary"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_products; ?></h3>
                <p>Total Products</p>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card">
            <div class="stat-icon bg-success-light">
                <i class="fas fa-check-circle text-success"></i>
            </div>
            <div class="stat-content">
                <h3>
                    <?php 
                    $active_count = array_filter($products, fn($p) => $p['status'] == 'active');
                    echo count($active_count);
                    ?>
                </h3>
                <p>Active Products</p>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning-light">
                <i class="fas fa-exclamation-triangle text-warning"></i>
            </div>
            <div class="stat-content">
                <h3>
                    <?php 
                    $low_stock_count = array_filter($products, fn($p) => $p['current_stock'] <= $p['min_stock']);
                    echo count($low_stock_count);
                    ?>
                </h3>
                <p>Low Stock</p>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card">
            <div class="stat-icon bg-info-light">
                <i class="fas fa-dollar-sign text-info"></i>
            </div>
            <div class="stat-content">
                <h3>
                    $<?php 
                    $total_value = array_sum(array_map(fn($p) => $p['current_stock'] * $p['unit_price'], $products));
                    echo number_format($total_value, 2);
                    ?>
                </h3>
                <p>Total Value</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card card-glass mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-filter mr-2"></i>Filter Products
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-sm" data-toggle="collapse" data-target="#filterCollapse">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse show" id="filterCollapse">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search products..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>
                </div>
                <div class="col-md-1">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="printProducts()">
                                <i class="fas fa-print mr-2"></i>Print
                            </a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToCSV()">
                                <i class="fas fa-download mr-2"></i>Export CSV
                            </a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToExcel()">
                                <i class="fas fa-file-excel mr-2"></i>Export Excel
                            </a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Products Grid -->
<div class="card card-glass">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-boxes mr-2"></i>Products List
        </h3>
        <div class="card-tools">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleView('grid')" id="gridViewBtn">
                    <i class="fas fa-th-large"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleView('list')" id="listViewBtn">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>No Products Found</h3>
                <p class="text-muted">Add your first product to start managing inventory</p>
                <a href="add.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus mr-2"></i>Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <!-- Grid View -->
            <div class="row g-4" id="gridView">
                <?php foreach ($products as $product): 
                    $stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
                    $stock_class = 'success';
                    if ($product['current_stock'] <= $product['min_stock']) {
                        $stock_class = 'danger';
                    } elseif ($stock_percent < 30) {
                        $stock_class = 'warning';
                    }
                    
                    $status_class = 'success';
                    if ($product['status'] == 'inactive') $status_class = 'warning';
                    if ($product['status'] == 'discontinued') $status_class = 'danger';
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="product-card">
                        <div class="product-card-header">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-status">
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </div>
                            <?php if ($stock_class == 'danger'): ?>
                                <div class="product-low-stock">
                                    <i class="fas fa-exclamation-triangle"></i> Low Stock
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-card-body">
                            <h5 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <p class="product-sku text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></p>
                            
                            <?php if ($product['category']): ?>
                                <span class="product-category">
                                    <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($product['category']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <div class="product-price">
                                <span class="price">$<?php echo number_format($product['unit_price'], 2); ?></span>
                                <span class="cost text-muted">Cost: $<?php echo number_format($product['cost_price'] ?? 0, 2); ?></span>
                            </div>
                            
                            <div class="product-stock">
                                <div class="stock-info">
                                    <span><?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?></span>
                                    <span><?php echo round($stock_percent); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $stock_class; ?>" 
                                         style="width: <?php echo min($stock_percent, 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="product-card-footer">
                            <div class="btn-group w-100">
                                <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary" 
                                   title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger" 
                                        title="Delete Product"
                                        onclick="showDeleteModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Table View (Hidden by Default) -->
            <div class="table-responsive d-none" id="tableView">
                <table class="table table-hover" id="productsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $product): 
                            $stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
                            $stock_class = 'success';
                            if ($product['current_stock'] <= $product['min_stock']) {
                                $stock_class = 'danger';
                            } elseif ($stock_percent < 30) {
                                $stock_class = 'warning';
                            }
                            
                            $status_class = 'success';
                            if ($product['status'] == 'inactive') $status_class = 'warning';
                            if ($product['status'] == 'discontinued') $status_class = 'danger';
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             class="table-product-image">
                                    <?php else: ?>
                                        <div class="table-product-placeholder">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ms-3">
                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                        <div class="text-muted small">SKU: <?php echo htmlspecialchars($product['product_code']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($product['category']): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['category']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Uncategorized</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-success">$<?php echo number_format($product['unit_price'], 2); ?></strong>
                                <div class="text-muted small">Cost: $<?php echo number_format($product['cost_price'] ?? 0, 2); ?></div>
                            </td>
                            <td>
                                <div class="stock-indicator">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?></span>
                                        <span class="text-<?php echo $stock_class; ?>"><?php echo round($stock_percent); ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-<?php echo $stock_class; ?>" 
                                             style="width: <?php echo min($stock_percent, 100); ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="Delete Product"
                                            onclick="showDeleteModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
                </div>
                <nav aria-label="Product pagination">
                    <ul class="pagination mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="delete-icon">
                        <i class="fas fa-trash"></i>
                    </div>
                    <h4>Delete Product?</h4>
                    <p class="text-muted">Are you sure you want to delete <strong id="productName"></strong>? This action cannot be undone.</p>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> Deleting this product will also remove all related sales records.
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        I understand this action is permanent and cannot be undone
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    <i class="fas fa-trash me-2"></i>Delete Product
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentProductId = null;
    
    // Toggle between grid and list view
    function toggleView(view) {
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');
        const gridViewBtn = document.getElementById('gridViewBtn');
        const listViewBtn = document.getElementById('listViewBtn');
        
        if (view === 'grid') {
            gridView.classList.remove('d-none');
            tableView.classList.add('d-none');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            localStorage.setItem('productView', 'grid');
        } else {
            gridView.classList.add('d-none');
            tableView.classList.remove('d-none');
            gridViewBtn.classList.remove('active');
            listViewBtn.classList.add('active');
            localStorage.setItem('productView', 'list');
        }
    }
    
    // Show delete confirmation modal
    function showDeleteModal(productId, productName) {
        currentProductId = productId;
        document.getElementById('productName').textContent = productName;
        
        // Reset confirmation checkbox
        document.getElementById('confirmDelete').checked = false;
        document.getElementById('confirmDeleteBtn').disabled = true;
        
        // Show modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    
    // Handle delete confirmation
    document.getElementById('confirmDelete').addEventListener('change', function() {
        document.getElementById('confirmDeleteBtn').disabled = !this.checked;
    });
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!currentProductId) return;
        
        // Show loading
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
        this.disabled = true;
        
        // Send AJAX request to delete product
        fetch('ajax_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${currentProductId}&action=delete`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Product deleted successfully', 'success');
                
                // Close modal
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                deleteModal.hide();
                
                // Remove product card from DOM
                setTimeout(() => {
                    const productCard = document.querySelector(`[data-product-id="${currentProductId}"]`);
                    if (productCard) {
                        productCard.remove();
                    }
                    
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }, 500);
            } else {
                showToast(data.error || 'Failed to delete product', 'error');
                this.innerHTML = '<i class="fas fa-trash me-2"></i>Delete Product';
                this.disabled = false;
            }
        })
        .catch(error => {
            showToast('Error deleting product', 'error');
            this.innerHTML = '<i class="fas fa-trash me-2"></i>Delete Product';
            this.disabled = false;
        });
    });
    
    // Export to CSV
    function exportToCSV() {
        const table = document.getElementById('productsTable');
        if (!table) {
            showToast('No products to export', 'error');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            for (const col of cols) {
                // Remove icons and buttons from actions column
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
        a.download = `products_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Products exported successfully', 'success');
    }
    
    // Export to Excel
    function exportToExcel() {
        const table = document.getElementById('productsTable');
        if (!table) {
            showToast('No products to export', 'error');
            return;
        }
        
        // Create workbook
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, "Products");
        
        // Generate and download
        XLSX.writeFile(wb, `products_${new Date().toISOString().split('T')[0]}.xlsx`);
        
        showToast('Products exported to Excel', 'success');
    }
    
    // Print products
    function printProducts() {
        const printContent = document.getElementById('tableView').innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <html>
            <head>
                <title>Products List - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .no-print { display: none; }
                </style>
            </head>
            <body>
                <h1>Products List - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${printContent}
            </body>
            </html>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload();
    }
    
    // Initialize view from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const savedView = localStorage.getItem('productView') || 'grid';
        toggleView(savedView);
        
        // Add product IDs to cards for easy removal
        document.querySelectorAll('.product-card').forEach((card, index) => {
            card.setAttribute('data-product-id', <?php echo $products[$index]['product_id'] ?? 0; ?>);
        });
    });
</script>

<style>
    /* Glassmorphism effect */
    .card-glass {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    }
    
    /* Stat Cards */
    .stat-card {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.5rem;
    }
    
    .stat-content h3 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    .stat-content p {
        margin: 0;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    /* Product Cards */
    .product-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        height: 100%;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    
    .product-card-header {
        position: relative;
        height: 180px;
        overflow: hidden;
    }
    
    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-image-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
    }
    
    .product-status {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    
    .product-low-stock {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        padding: 5px 10px;
        font-size: 0.8rem;
        text-align: center;
    }
    
    .product-card-body {
        padding: 1.5rem;
    }
    
    .product-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-sku {
        font-size: 0.8rem;
        margin-bottom: 0.8rem;
    }
    
    .product-category {
        display: inline-block;
        background: #f8f9fa;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 1rem;
    }
    
    .product-price {
        margin-bottom: 1rem;
    }
    
    .product-price .price {
        font-size: 1.3rem;
        font-weight: 700;
        color: #28a745;
    }
    
    .product-price .cost {
        font-size: 0.9rem;
        margin-left: 0.5rem;
    }
    
    .product-stock .stock-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
    }
    
    .product-card-footer {
        padding: 1rem 1.5rem;
        background: #f8f9fa;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    /* Table View */
    .table-product-image {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .table-product-placeholder {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .stock-indicator .progress {
        height: 6px;
        border-radius: 3px;
    }
    
    /* Delete Modal */
    .delete-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        color: white;
        font-size: 2.5rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }
    
    .empty-state-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        color: white;
        font-size: 3rem;
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .product-card {
        animation: fadeIn 0.5s ease forwards;
    }
    
    .product-card:nth-child(1) { animation-delay: 0.1s; }
    .product-card:nth-child(2) { animation-delay: 0.2s; }
    .product-card:nth-child(3) { animation-delay: 0.3s; }
    .product-card:nth-child(4) { animation-delay: 0.4s; }
    .product-card:nth-child(5) { animation-delay: 0.5s; }
    .product-card:nth-child(6) { animation-delay: 0.6s; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .product-card {
            margin-bottom: 1rem;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>