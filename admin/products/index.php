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
        <p>View and manage all products in your inventory (<?php echo $total_products; ?> products)</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Product
        </a>
        <a href="categories.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-tags"></i> Manage Categories
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Filter Products</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-3">
                <div class="form-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search products..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-2">
                <div class="form-group">
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-2">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>
            </div>
            <div class="col-2">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
            </div>
            <div class="col-2">
                <a href="index.php" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-outline" style="width: 100%;" onclick="printProducts()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Products List</h3>
        <div class="btn-group">
            <button class="btn btn-outline" onclick="exportToCSV()">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>
    <div class="table-container">
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 4rem;">
                <div style="width: 100px; height: 100px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <i class="fas fa-box-open" style="font-size: 3rem; color: var(--border);"></i>
                </div>
                <h3>No Products Found</h3>
                <p class="text-muted">Add your first product to start managing inventory</p>
                <a href="add.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <table class="table" id="productsTable">
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
                        $stock_status = 'success';
                        $stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
                        
                        if ($product['current_stock'] <= $product['min_stock']) {
                            $stock_status = 'error';
                        } elseif ($stock_percent < 30) {
                            $stock_status = 'warning';
                        }
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-light), var(--accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-box" style="color: var(--primary);"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($product['category']): ?>
                                <span class="badge badge-primary"><?php echo htmlspecialchars($product['category']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">Uncategorized</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--success);">$<?php echo number_format($product['unit_price'], 2); ?></strong><br>
                            <small class="text-muted">Cost: $<?php echo number_format($product['cost_price'] ?? 0, 2); ?></small>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                                        <span><?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?></span>
                                        <small><?php echo round($stock_percent); ?>%</small>
                                    </div>
                                    <div style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo min($stock_percent, 100); ?>%; 
                                            background: var(--<?php echo $stock_status; ?>); border-radius: 3px;"></div>
                                    </div>
                                </div>
                                <?php if ($stock_status == 'error'): ?>
                                    <i class="fas fa-exclamation-triangle" style="color: var(--error);"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $status_badge = 'badge-success';
                            if ($product['status'] == 'inactive') $status_badge = 'badge-warning';
                            if ($product['status'] == 'discontinued') $status_badge = 'badge-error';
                            ?>
                            <span class="badge <?php echo $status_badge; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem;"
                                   title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline" 
                                   style="padding: 0.4rem 0.8rem; color: var(--error);"
                                   title="Delete Product"
                                   onclick="return confirm('Are you sure you want to delete this product? This will also remove all related sales records.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Summary Stats -->
            <div style="padding: 1rem; background: var(--bg); border-top: 1px solid var(--border);">
                <div class="row">
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Total Products</small>
                        <h4 style="margin: 0;"><?php echo $total_products; ?></h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Active Products</small>
                        <h4 style="margin: 0; color: var(--success);">
                            <?php 
                            $active_count = array_filter($products, fn($p) => $p['status'] == 'active');
                            echo count($active_count);
                            ?>
                        </h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Low Stock</small>
                        <h4 style="margin: 0; color: var(--error);">
                            <?php 
                            $low_stock_count = array_filter($products, fn($p) => $p['current_stock'] <= $p['min_stock']);
                            echo count($low_stock_count);
                            ?>
                        </h4>
                    </div>
                    <div class="col-3" style="text-align: center;">
                        <small class="text-muted">Total Value</small>
                        <h4 style="margin: 0; color: var(--primary);">
                            $<?php 
                            $total_value = array_sum(array_map(fn($p) => $p['current_stock'] * $p['unit_price'], $products));
                            echo number_format($total_value, 2);
                            ?>
                        </h4>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Product Actions</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <div class="col-2">
                        <a href="add.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-plus"></i> Add Product
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../inventory/stock-adjustment.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-exchange-alt"></i> Adjust Stock
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../inventory/low-stock.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="categories.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-tags"></i> Categories
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="suppliers.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-truck"></i> Suppliers
                        </a>
                    </div>
                    <div class="col-2">
                        <a href="../reports/products.php" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-chart-bar"></i> Product Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
        a.download = 'products_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Products exported successfully', 'success');
    }
    
    // Print products
    function printProducts() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Products List - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                </style>
            </head>
            <body>
                <h1>Products List - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
        `);
        
        const table = document.getElementById('productsTable');
        if (table) {
            printWindow.document.write(table.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, ''));
        }
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    // Auto-refresh stock status
    setInterval(() => {
        document.querySelectorAll('.stock-low-alert').forEach(el => {
            el.style.animation = el.style.animation ? '' : 'pulse 2s infinite';
        });
    }, 3000);
</script>

<style>
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .stock-low-alert {
        animation: pulse 2s infinite;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
