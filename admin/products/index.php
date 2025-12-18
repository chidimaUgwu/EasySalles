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

// Calculate statistics
$active_count = array_filter($products, fn($p) => $p['status'] == 'active');
$low_stock_count = array_filter($products, fn($p) => $p['current_stock'] <= $p['min_stock']);
$total_value = array_sum(array_map(fn($p) => $p['current_stock'] * $p['unit_price'], $products));
?>

<div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2.5rem; border-radius: 20px; color: white; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
    <div class="page-title">
        <h1 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem 0; color: white;">📦 Product Management</h1>
        <p style="font-size: 1.1rem; opacity: 0.9; margin: 0;">Manage your entire product inventory with ease</p>
    </div>
    <div class="page-actions" style="display: flex; gap: 1rem;">
        <a href="add.php" class="btn btn-primary" style="background: white; color: #667eea; border: none; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
            <i class="fas fa-plus-circle"></i> Add New Product
        </a>
        <a href="categories.php" class="btn btn-outline" style="background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(255,255,255,0.3); padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 600; transition: all 0.3s ease;">
            <i class="fas fa-tags"></i> Manage Categories
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row" style="margin-bottom: 2rem;">
    <div class="col-3">
        <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.9rem; color: #718096; margin-bottom: 0.5rem;">Total Products</div>
                    <div style="font-size: 2rem; font-weight: 700; color: #4a5568;"><?php echo $total_products; ?></div>
                </div>
                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #4299e1, #63b3ed); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-boxes" style="color: white; font-size: 1.5rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.9rem; color: #718096; margin-bottom: 0.5rem;">Active Products</div>
                    <div style="font-size: 2rem; font-weight: 700; color: #38a169;"><?php echo count($active_count); ?></div>
                </div>
                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #38a169, #68d391); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-check-circle" style="color: white; font-size: 1.5rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.9rem; color: #718096; margin-bottom: 0.5rem;">Low Stock</div>
                    <div style="font-size: 2rem; font-weight: 700; color: #e53e3e;"><?php echo count($low_stock_count); ?></div>
                </div>
                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #e53e3e, #fc8181); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-exclamation-triangle" style="color: white; font-size: 1.5rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.9rem; color: #718096; margin-bottom: 0.5rem;">Total Value</div>
                    <div style="font-size: 2rem; font-weight: 700; color: #805ad5;">$<?php echo number_format($total_value, 2); ?></div>
                </div>
                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #805ad5, #b794f4); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-dollar-sign" style="color: white; font-size: 1.5rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 16px; overflow: hidden;">
    <div class="card-header" style="background: linear-gradient(90deg, #f7fafc, #edf2f7); border-bottom: 1px solid #e2e8f0; padding: 1.5rem;">
        <h3 class="card-title" style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #2d3748;">
            <i class="fas fa-filter" style="margin-right: 0.5rem; color: #667eea;"></i>
            Filter Products
        </h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row" style="align-items: center;">
            <div class="col-3">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search products by name, SKU, description..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="padding-left: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; height: 48px;">
                    </div>
                </div>
            </div>
            <div class="col-2">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-tag" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                        <select name="category" class="form-control" style="padding-left: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; height: 48px;">
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
            </div>
            <div class="col-2">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-circle" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                        <select name="status" class="form-control" style="padding-left: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; height: 48px;">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-2">
                <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px; border-radius: 12px; border: none; background: linear-gradient(135deg, #667eea, #764ba2); font-weight: 600;">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
            </div>
            <div class="col-2">
                <a href="index.php" class="btn btn-outline" style="width: 100%; height: 48px; border-radius: 12px; border: 2px solid #e2e8f0; background: white; color: #4a5568; font-weight: 600;">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-outline" style="width: 100%; height: 48px; border-radius: 12px; border: 2px solid #e2e8f0; background: white; color: #4a5568;"
                        onclick="printProducts()" title="Print Products">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 16px; overflow: hidden;">
    <div class="card-header" style="background: white; border-bottom: 1px solid #e2e8f0; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 class="card-title" style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #2d3748;">
                <i class="fas fa-list-alt" style="margin-right: 0.5rem; color: #667eea;"></i>
                Products List
                <span style="font-size: 0.875rem; color: #718096; margin-left: 0.5rem;">
                    (<?php echo $total_products; ?> products found)
                </span>
            </h3>
        </div>
        <div class="btn-group" style="display: flex; gap: 0.5rem;">
            <button class="btn btn-outline" onclick="exportToCSV()" 
                    style="border-radius: 10px; border: 2px solid #e2e8f0; padding: 0.5rem 1rem; font-weight: 600; color: #4a5568;">
                <i class="fas fa-download"></i> Export CSV
            </button>
            <button class="btn btn-outline" onclick="toggleViewMode()" id="viewToggle"
                    style="border-radius: 10px; border: 2px solid #e2e8f0; padding: 0.5rem 1rem; font-weight: 600; color: #4a5568;">
                <i class="fas fa-th-large"></i> Grid View
            </button>
        </div>
    </div>
    <div class="table-container">
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 4rem;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #f7fafc, #edf2f7); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; animation: float 3s ease-in-out infinite;">
                    <i class="fas fa-box-open" style="font-size: 3.5rem; color: #cbd5e0;"></i>
                </div>
                <h3 style="font-size: 1.5rem; color: #2d3748; margin-bottom: 1rem;">No Products Found</h3>
                <p style="color: #718096; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">
                    No products match your search criteria. Try adjusting your filters or add a new product to get started.
                </p>
                <a href="add.php" class="btn btn-primary" 
                   style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 0.8rem 2rem; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                    <i class="fas fa-plus-circle"></i> Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <!-- Table View -->
            <div id="tableView">
                <table class="table" id="productsTable" style="border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: linear-gradient(90deg, #f7fafc, #edf2f7);">
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">#</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Product</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Category</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Price</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Stock</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $product): 
                            $stock_status = 'success';
                            $stock_color = '#38a169';
                            $stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
                            
                            if ($product['current_stock'] <= $product['min_stock']) {
                                $stock_status = 'error';
                                $stock_color = '#e53e3e';
                            } elseif ($stock_percent < 30) {
                                $stock_status = 'warning';
                                $stock_color = '#d69e2e';
                            }
                        ?>
                        <tr style="transition: all 0.3s ease; border-bottom: 1px solid #edf2f7;" 
                            onmouseover="this.style.backgroundColor='#f7fafc'" 
                            onmouseout="this.style.backgroundColor='white'">
                            <td style="padding: 1rem; font-weight: 600; color: #4a5568;"><?php echo $index + 1; ?></td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                                            <i class="fas fa-box" style="color: white; font-size: 1.2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div style="font-size: 0.875rem; color: #718096;">
                                            SKU: <span style="font-family: monospace; background: #f7fafc; padding: 0.125rem 0.5rem; border-radius: 6px;"><?php echo htmlspecialchars($product['product_code']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <?php if ($product['category']): ?>
                                    <span class="badge" style="background: rgba(102, 126, 234, 0.1); color: #667eea; padding: 0.375rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($product['category']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #a0aec0; font-size: 0.875rem;">
                                        <i class="fas fa-minus"></i> Uncategorized
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: #38a169; font-size: 1.1rem;">
                                    $<?php echo number_format($product['unit_price'], 2); ?>
                                </div>
                                <div style="font-size: 0.875rem; color: #718096;">
                                    Cost: $<?php echo number_format($product['cost_price'] ?? 0, 2); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem; min-width: 200px;">
                                <div style="margin-bottom: 0.5rem;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                        <span style="color: #4a5568; font-weight: 600;">
                                            <?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?>
                                        </span>
                                        <span style="color: <?php echo $stock_color; ?>; font-weight: 600;">
                                            <?php echo round($stock_percent); ?>%
                                        </span>
                                    </div>
                                    <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo min($stock_percent, 100); ?>%; 
                                            background: <?php echo $stock_color; ?>; border-radius: 4px; transition: width 1s ease;"></div>
                                    </div>
                                </div>
                                <div style="font-size: 0.75rem; color: #a0aec0;">
                                    Min: <?php echo $product['min_stock']; ?> | Max: <?php echo $product['max_stock']; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <?php 
                                $status_color = '#38a169';
                                $status_bg = 'rgba(56, 161, 105, 0.1)';
                                if ($product['status'] == 'inactive') {
                                    $status_color = '#d69e2e';
                                    $status_bg = 'rgba(214, 158, 46, 0.1)';
                                }
                                if ($product['status'] == 'discontinued') {
                                    $status_color = '#e53e3e';
                                    $status_bg = 'rgba(229, 62, 62, 0.1)';
                                }
                                ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>;">
                                    <?php if ($product['status'] == 'active'): ?>
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?php elseif ($product['status'] == 'inactive'): ?>
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-ban" style="font-size: 0.75rem;"></i>
                                    <?php endif; ?>
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-action" 
                                       style="width: 36px; height: 36px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #4a5568; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease;"
                                       title="View Details"
                                       onmouseover="this.style.borderColor='#4299e1'; this.style.color='#4299e1'"
                                       onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#4a5568'">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-action" 
                                       style="width: 36px; height: 36px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #4a5568; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease;"
                                       title="Edit Product"
                                       onmouseover="this.style.borderColor='#38a169'; this.style.color='#38a169'"
                                       onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#4a5568'">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-action" 
                                       style="width: 36px; height: 36px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #4a5568; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease;"
                                       title="Delete Product"
                                       onmouseover="this.style.borderColor='#e53e3e'; this.style.color='#e53e3e'"
                                       onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#4a5568'"
                                       onclick="return confirm('⚠️ Are you sure you want to delete this product? This will permanently remove all product data and related sales records.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grid View (Hidden by default) -->
            <div id="gridView" style="display: none; padding: 1.5rem;">
                <div class="row">
                    <?php foreach ($products as $product): 
                        $stock_status = 'success';
                        $stock_color = '#38a169';
                        $stock_percent = ($product['current_stock'] / $product['max_stock']) * 100;
                        
                        if ($product['current_stock'] <= $product['min_stock']) {
                            $stock_status = 'error';
                            $stock_color = '#e53e3e';
                        } elseif ($stock_percent < 30) {
                            $stock_status = 'warning';
                            $stock_color = '#d69e2e';
                        }
                    ?>
                    <div class="col-3" style="margin-bottom: 1.5rem;">
                        <div class="product-card" style="background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; height: 100%; border: 1px solid #e2e8f0;">
                            <div style="height: 160px; background: linear-gradient(135deg, #f7fafc, #edf2f7); display: flex; align-items: center; justify-content: center; position: relative;">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 20px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-box" style="color: white; font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="position: absolute; top: 1rem; right: 1rem;">
                                    <?php 
                                    $status_color = '#38a169';
                                    if ($product['status'] == 'inactive') $status_color = '#d69e2e';
                                    if ($product['status'] == 'discontinued') $status_color = '#e53e3e';
                                    ?>
                                    <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo $status_color; ?>; border-radius: 50%;"></span>
                                </div>
                            </div>
                            <div style="padding: 1.25rem;">
                                <div style="margin-bottom: 1rem;">
                                    <div style="font-weight: 600; color: #2d3748; margin-bottom: 0.5rem; font-size: 1rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars(mb_strimwidth($product['product_name'], 0, 30, '...')); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #718096; margin-bottom: 0.5rem;">
                                        SKU: <?php echo htmlspecialchars($product['product_code']); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                        <span style="font-size: 0.875rem; padding: 0.25rem 0.75rem; background: rgba(102, 126, 234, 0.1); color: #667eea; border-radius: 12px; font-weight: 600;">
                                            <?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div style="font-weight: 700; color: #38a169; font-size: 1.25rem;">
                                            $<?php echo number_format($product['unit_price'], 2); ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: #718096;">
                                            Stock: <?php echo $product['current_stock']; ?>
                                        </div>
                                    </div>
                                    <div style="height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo min($stock_percent, 100); ?>%; 
                                            background: <?php echo $stock_color; ?>; border-radius: 3px;"></div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-outline" 
                                       style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #4a5568; text-align: center; text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.3s ease;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-outline" 
                                       style="flex: 1; padding: 0.5rem; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #4a5568; text-align: center; text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.3s ease;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="row" style="margin-top: 2rem;">
    <div class="col-12">
        <div class="card" style="background: linear-gradient(135deg, #f7fafc, #edf2f7); border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <div class="card-header" style="background: transparent; border: none; padding: 1.5rem;">
                <h3 class="card-title" style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #2d3748;">
                    <i class="fas fa-bolt" style="margin-right: 0.5rem; color: #667eea;"></i>
                    Quick Actions
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div class="row">
                    <?php 
                    $quick_actions = [
                        ['icon' => 'fa-plus', 'label' => 'Add Product', 'url' => 'add.php', 'color' => '#667eea'],
                        ['icon' => 'fa-exchange-alt', 'label' => 'Adjust Stock', 'url' => '../inventory/stock-adjustment.php', 'color' => '#38a169'],
                        ['icon' => 'fa-exclamation-triangle', 'label' => 'Low Stock', 'url' => '../inventory/low-stock.php', 'color' => '#e53e3e'],
                        ['icon' => 'fa-tags', 'label' => 'Categories', 'url' => 'categories.php', 'color' => '#d69e2e'],
                        ['icon' => 'fa-truck', 'label' => 'Suppliers', 'url' => 'suppliers.php', 'color' => '#805ad5'],
                        ['icon' => 'fa-chart-bar', 'label' => 'Reports', 'url' => '../reports/products.php', 'color' => '#4299e1']
                    ];
                    ?>
                    <?php foreach ($quick_actions as $action): ?>
                    <div class="col-2">
                        <a href="<?php echo $action['url']; ?>" class="quick-action-btn" 
                           style="display: block; text-decoration: none; text-align: center; padding: 1.5rem 0.5rem; background: white; border-radius: 12px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;"
                           onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.1)'"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">
                            <div style="width: 48px; height: 48px; background: <?php echo $action['color']; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas <?php echo $action['icon']; ?>" style="color: white; font-size: 1.25rem;"></i>
                            </div>
                            <div style="font-weight: 600; color: #2d3748; font-size: 0.9rem;"><?php echo $action['label']; ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
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
        
        // Show success animation
        const exportBtn = document.querySelector('[onclick="exportToCSV()"]');
        exportBtn.innerHTML = '<i class="fas fa-check"></i> Exported!';
        exportBtn.style.background = 'linear-gradient(135deg, #38a169, #68d391)';
        exportBtn.style.color = 'white';
        exportBtn.style.border = 'none';
        
        setTimeout(() => {
            exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
            exportBtn.style.background = '';
            exportBtn.style.color = '';
            exportBtn.style.border = '';
        }, 2000);
        
        showToast('Products exported successfully!', 'success');
    }
    
    // Print products
    function printProducts() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Products List - EasySalles</title>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                    body { 
                        font-family: 'Inter', Arial, sans-serif; 
                        margin: 40px; 
                        color: #2d3748;
                        line-height: 1.6;
                    }
                    .header { 
                        text-align: center; 
                        margin-bottom: 30px; 
                        padding-bottom: 20px;
                        border-bottom: 2px solid #e2e8f0;
                    }
                    h1 { 
                        color: #2d3748; 
                        font-size: 28px;
                        font-weight: 700;
                        margin: 0;
                    }
                    .subtitle { 
                        color: #718096; 
                        margin: 5px 0 20px 0;
                    }
                    .meta { 
                        color: #a0aec0; 
                        font-size: 14px;
                        margin-bottom: 30px;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin-top: 20px;
                        font-size: 14px;
                    }
                    th { 
                        background: #f7fafc; 
                        padding: 12px 15px;
                        text-align: left;
                        font-weight: 600;
                        color: #4a5568;
                        border-bottom: 2px solid #e2e8f0;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }
                    td { 
                        padding: 12px 15px;
                        border-bottom: 1px solid #edf2f7;
                    }
                    .badge {
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 12px;
                        font-weight: 600;
                    }
                    .status-active { background: #c6f6d5; color: #22543d; }
                    .status-inactive { background: #feebc8; color: #744210; }
                    .status-discontinued { background: #fed7d7; color: #742a2a; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                    .totals {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 2px solid #e2e8f0;
                        display: flex;
                        justify-content: space-between;
                        font-weight: 600;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>EasySalles - Products Report</h1>
                    <div class="subtitle">Comprehensive product inventory listing</div>
                    <div class="meta">
                        Generated on: ${new Date().toLocaleString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                    </div>
                </div>
        `);
        
        const table = document.getElementById('productsTable');
        if (table) {
            // Clone table and clean up for printing
            const printTable = table.cloneNode(true);
            
            // Remove action buttons
            const actionCells = printTable.querySelectorAll('td:last-child, th:last-child');
            actionCells.forEach(cell => cell.remove());
            
            // Clean up badges and styling
            const badges = printTable.querySelectorAll('.badge');
            badges.forEach(badge => {
                const text = badge.innerText;
                badge.outerHTML = `<span class="badge status-${text.toLowerCase()}">${text}</span>`;
            });
            
            printWindow.document.write(printTable.outerHTML);
        }
        
        // Add totals
        printWindow.document.write(`
            <div class="totals">
                <div>Total Products: ${<?php echo $total_products; ?>}</div>
                <div>Total Inventory Value: $${<?php echo $total_value; ?>.toFixed(2)}</div>
            </div>
        `);
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
    
    // Toggle between table and grid view
    let isGridView = false;
    function toggleViewMode() {
        const tableView = document.getElementById('tableView');
        const gridView = document.getElementById('gridView');
        const toggleBtn = document.getElementById('viewToggle');
        
        if (isGridView) {
            tableView.style.display = 'block';
            gridView.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fas fa-th-large"></i> Grid View';
            toggleBtn.style.borderColor = '#e2e8f0';
            toggleBtn.style.color = '#4a5568';
        } else {
            tableView.style.display = 'none';
            gridView.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fas fa-list"></i> Table View';
            toggleBtn.style.borderColor = '#667eea';
            toggleBtn.style.color = '#667eea';
        }
        isGridView = !isGridView;
    }
    
    // Add hover effects to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
            card.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = '0 4px 20px rgba(0,0,0,0.05)';
        });
    });
    
    // Auto-refresh stock alerts
    setInterval(() => {
        document.querySelectorAll('[style*="color: #e53e3e"]').forEach(el => {
            if (el.closest('tr') || el.closest('.product-card')) {
                el.style.animation = el.style.animation ? '' : 'pulse 1.5s infinite';
            }
        });
    }, 2000);
</script>

<style>
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.6; }
        100% { opacity: 1; }
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Apply animation to product rows */
    #productsTable tbody tr {
        animation: slideIn 0.5s ease forwards;
        animation-delay: calc(var(--row-index) * 0.05s);
        opacity: 0;
    }
    
    /* Set row index for staggered animation */
    #productsTable tbody tr:nth-child(1) { --row-index: 1; }
    #productsTable tbody tr:nth-child(2) { --row-index: 2; }
    #productsTable tbody tr:nth-child(3) { --row-index: 3; }
    #productsTable tbody tr:nth-child(4) { --row-index: 4; }
    #productsTable tbody tr:nth-child(5) { --row-index: 5; }
    #productsTable tbody tr:nth-child(6) { --row-index: 6; }
    #productsTable tbody tr:nth-child(7) { --row-index: 7; }
    #productsTable tbody tr:nth-child(8) { --row-index: 8; }
    #productsTable tbody tr:nth-child(9) { --row-index: 9; }
    #productsTable tbody tr:nth-child(10) { --row-index: 10; }
    
    /* Product card hover effect */
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
        border-color: #cbd5e0 !important;
    }
    
    /* Smooth transitions */
    .btn, .btn-action, .stat-card, .product-card, .quick-action-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    
    /* Custom scrollbar for table */
    .table-container {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .table-container::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    .table-container::-webkit-scrollbar-track {
        background: #f7fafc;
        border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
</style>

<?php require_once '../includes/footer.php'; ?>