<?php
// admin/products/index.php (FIXED VERSION)
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

<style>
    /* Keep all your original admin functionality */
    /* Only change the visual design of product cards */
    
    .products-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .page-title h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 0.5rem 0;
    }
    
    .page-title p {
        color: var(--text-muted);
        margin: 0;
    }
    
    .page-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
    }
    
    .btn-secondary {
        background: var(--secondary);
        color: white;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text);
        border: 2px solid var(--border);
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    /* Filters Card - Keep original functionality with better styling */
    .filters-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-group {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.9rem;
    }
    
    .filter-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--input-bg);
        color: var(--text);
    }
    
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    
    .filter-btn {
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .filter-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
    }
    
    .reset-btn {
        padding: 0.75rem 1.5rem;
        background: var(--card-bg);
        color: var(--text);
        border: 2px solid var(--border);
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: 48px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .reset-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    /* Statistics Cards - Keep your original stats */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    /* BEAUTIFUL PRODUCT CARDS - This is what you wanted! */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }
    
    .product-card {
        background: var(--card-bg);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(124, 58, 237, 0.15);
        border-color: var(--primary-light);
    }
    
    .product-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        z-index: 1;
    }
    
    .product-image-container {
        height: 180px;
        overflow: hidden;
        position: relative;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
    }
    
    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.05);
    }
    
    .no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--primary);
        font-size: 3.5rem;
        opacity: 0.5;
    }
    
    .product-header {
        padding: 1.25rem 1.25rem 0.5rem;
    }
    
    .product-code {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.85rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 0.75rem;
    }
    
    .product-name {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text);
        line-height: 1.3;
    }
    
    .product-description {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-details {
        padding: 0 1.25rem;
        flex-grow: 1;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    
    .detail-item {
        text-align: center;
        padding: 0.75rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .detail-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    }
    
    .detail-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }
    
    .detail-value {
        font-weight: 600;
        color: var(--text);
        font-size: 1rem;
    }
    
    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        width: fit-content;
    }
    
    .stock-indicator {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        margin: 1rem 0;
        overflow: hidden;
    }
    
    .stock-level {
        height: 100%;
        border-radius: 3px;
        transition: all 0.3s ease;
    }
    
    .stock-low { background: #EF4444; }
    .stock-medium { background: #F59E0B; }
    .stock-high { background: #10B981; }
    
    .stock-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 1.25rem;
    }
    
    .product-price-section {
        padding: 1.25rem;
        border-top: 2px solid var(--border);
        text-align: center;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01));
    }
    
    .price-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }
    
    .price-value {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .product-status {
        font-size: 0.8rem;
        padding: 0.2rem 0.75rem;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .status-inactive {
        background: rgba(245, 158, 11, 0.1);
        color: #F59E0B;
    }
    
    .status-discontinued {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
    }
    
    /* ADMIN ACTIONS - Keep your original admin buttons */
    .admin-actions {
        padding: 1.25rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        background: var(--card-bg);
    }
    
    .admin-btn {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .admin-btn-view {
        background: rgba(59, 130, 246, 0.1);
        color: #3B82F6;
        border-color: rgba(59, 130, 246, 0.2);
    }
    
    .admin-btn-view:hover {
        background: rgba(59, 130, 246, 0.2);
        border-color: #3B82F6;
        transform: translateY(-2px);
    }
    
    .admin-btn-edit {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
        border-color: rgba(16, 185, 129, 0.2);
    }
    
    .admin-btn-edit:hover {
        background: rgba(16, 185, 129, 0.2);
        border-color: #10B981;
        transform: translateY(-2px);
    }
    
    .admin-btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
        border-color: rgba(239, 68, 68, 0.2);
    }
    
    .admin-btn-delete:hover {
        background: rgba(239, 68, 68, 0.2);
        border-color: #EF4444;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        grid-column: 1 / -1;
        background: var(--card-bg);
        border-radius: 16px;
        border: 2px dashed var(--border);
    }
    
    .empty-state i {
        font-size: 3.5rem;
        color: var(--border);
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }
    
    /* Quick Actions - Keep original */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .quick-action-btn {
        padding: 1rem;
        background: var(--card-bg);
        border-radius: 12px;
        border: 1px solid var(--border);
        text-decoration: none;
        color: var(--text);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .quick-action-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.1);
    }
    
    .quick-action-btn i {
        font-size: 1.5rem;
        color: var(--primary);
    }
    
    /* View Toggle */
    .view-toggle {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding: 0.5rem;
        background: var(--card-bg);
        border-radius: 12px;
        border: 1px solid var(--border);
        width: fit-content;
    }
    
    .view-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        border: none;
        background: none;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .view-btn.active {
        background: var(--primary);
        color: white;
    }
</style>

<div class="products-container">
    <!-- Page Header - Keep your original header -->
    <div class="page-header">
        <div class="page-title">
            <h2>📦 Manage Products</h2>
            <p>View and manage all products in your inventory (<?php echo $total_products; ?> products)</p>
        </div>
        <div class="page-actions">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
            <a href="categories.php" class="btn btn-secondary">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
        </div>
    </div>

    <!-- Statistics - Keep your original stats -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_products; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php 
                $active_count = array_filter($products, fn($p) => $p['status'] == 'active');
                echo count($active_count);
                ?>
            </div>
            <div class="stat-label">Active Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php 
                $low_stock_count = array_filter($products, fn($p) => $p['current_stock'] <= $p['min_stock']);
                echo count($low_stock_count);
                ?>
            </div>
            <div class="stat-label">Low Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                $<?php 
                $total_value = array_sum(array_map(fn($p) => $p['current_stock'] * $p['unit_price'], $products));
                echo number_format($total_value, 2);
                ?>
            </div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- Filters - Keep your original filter functionality -->
    <div class="filters-card">
        <form method="GET" action="" id="productsFilter">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search Products</label>
                    <input type="text" 
                           name="search" 
                           class="filter-control" 
                           placeholder="Search by name, code, description..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                
                <a href="index.php" class="reset-btn">
                    <i class="fas fa-redo"></i> Reset
                </a>
                
                <button type="button" class="filter-btn" onclick="printProducts()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </form>
    </div>

    <!-- View Toggle -->
    <div class="view-toggle">
        <button class="view-btn active" onclick="setViewMode('grid')">
            <i class="fas fa-th-large"></i> Grid
        </button>
        <button class="view-btn" onclick="setViewMode('list')">
            <i class="fas fa-list"></i> List
        </button>
    </div>

    <!-- Products Grid/List View -->
    <div id="productsView">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Products Found</h3>
                <p>No products match your search criteria. Try adjusting your filters or add a new product to get started.</p>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <!-- Grid View (Default) -->
            <div id="gridView" class="products-grid">
                <?php foreach ($products as $product): 
                    // Calculate stock percentage
                    $max_stock = max($product['max_stock'], 1);
                    $stock_percentage = ($product['current_stock'] / $max_stock) * 100;
                    $stock_class = $stock_percentage <= 20 ? 'stock-low' : 
                                  ($stock_percentage <= 50 ? 'stock-medium' : 'stock-high');
                    
                    // Default image if none
                    $image_url = $product['image_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($product['product_name']) . '&background=7C3AED&color=fff&size=256';
                    
                    // Status class
                    $status_class = 'status-' . $product['status'];
                ?>
                    <div class="product-card" data-id="<?php echo $product['product_id']; ?>">
                        <!-- Product Image -->
                        <div class="product-image-container">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     class="product-image"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($product['product_name']); ?>&background=7C3AED&color=fff&size=256'">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Header -->
                        <div class="product-header">
                            <div class="product-meta">
                                <span class="product-code"><?php echo htmlspecialchars($product['product_code']); ?></span>
                                <span class="product-status <?php echo $status_class; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($product['status']); ?>
                                </span>
                            </div>
                            
                            <h3 class="product-name" title="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </h3>
                            
                            <?php if ($product['category']): ?>
                                <div class="category-badge" style="background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['description']): ?>
                                <p class="product-description" title="<?php echo htmlspecialchars($product['description']); ?>">
                                    <?php echo htmlspecialchars(mb_strimwidth($product['description'], 0, 100, '...')); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Details -->
                        <div class="product-details">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Current Stock</div>
                                    <div class="detail-value">
                                        <?php echo $product['current_stock']; ?> 
                                        <small style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($product['unit_type']); ?></small>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Price</div>
                                    <div class="detail-value">$<?php echo number_format($product['unit_price'], 2); ?></div>
                                </div>
                            </div>
                            
                            <!-- Stock Indicator -->
                            <div class="stock-indicator">
                                <div class="stock-level <?php echo $stock_class; ?>" 
                                     style="width: <?php echo min($stock_percentage, 100); ?>%"></div>
                            </div>
                            
                            <div class="stock-info">
                                <span>Stock Level: <?php echo round($stock_percentage); ?>%</span>
                                <span>Min: <?php echo $product['min_stock']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Price & Admin Actions -->
                        <div class="product-price-section">
                            <div class="price-value">
                                <strong>Total Value:</strong>
                                $<?php echo number_format($product['current_stock'] * $product['unit_price'], 2); ?>
                            </div>
                        </div>
                        
                        <!-- Admin Actions - Keep your original admin buttons -->
                        <div class="admin-actions">
                            <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                               class="admin-btn admin-btn-view"
                               title="View Details">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                               class="admin-btn admin-btn-edit"
                               title="Edit Product">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="delete.php?id=<?php echo $product['product_id']; ?>" 
                               class="admin-btn admin-btn-delete"
                               title="Delete Product"
                               onclick="return confirm('Are you sure you want to delete this product? This will also remove all related sales records.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List View (Hidden by default) -->
            <div id="listView" style="display: none;">
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
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-box" style="color: white;"></i>
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
                                       class="admin-btn admin-btn-view" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                       class="admin-btn admin-btn-edit"
                                       title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $product['product_id']; ?>" 
                                       class="admin-btn admin-btn-delete"
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
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions - Keep your original quick actions -->
    <div class="quick-actions">
        <a href="add.php" class="quick-action-btn">
            <i class="fas fa-plus"></i>
            <span>Add Product</span>
        </a>
        <a href="../inventory/stock-adjustment.php" class="quick-action-btn">
            <i class="fas fa-exchange-alt"></i>
            <span>Adjust Stock</span>
        </a>
        <a href="../inventory/low-stock.php" class="quick-action-btn">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Low Stock</span>
        </a>
        <a href="categories.php" class="quick-action-btn">
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </a>
        <a href="suppliers.php" class="quick-action-btn">
            <i class="fas fa-truck"></i>
            <span>Suppliers</span>
        </a>
        <a href="../reports/products.php" class="quick-action-btn">
            <i class="fas fa-chart-bar"></i>
            <span>Product Reports</span>
        </a>
    </div>
</div>

<script>
    // Export to CSV - Keep your original function
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
                if (col.querySelector('.admin-btn')) {
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
    
    // Print products - Keep your original function
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
        } else {
            // If in grid view, use the first table we can find
            const anyTable = document.querySelector('table');
            if (anyTable) {
                printWindow.document.write(anyTable.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, ''));
            }
        }
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    // View mode toggle
    function setViewMode(mode) {
        const gridView = document.getElementById('gridView');
        const listView = document.getElementById('listView');
        const gridBtn = document.querySelector('.view-btn:nth-child(1)');
        const listBtn = document.querySelector('.view-btn:nth-child(2)');
        
        if (mode === 'grid') {
            gridView.style.display = 'grid';
            listView.style.display = 'none';
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        } else {
            gridView.style.display = 'none';
            listView.style.display = 'block';
            gridBtn.classList.remove('active');
            listBtn.classList.add('active');
        }
        
        // Save preference to localStorage
        localStorage.setItem('productViewMode', mode);
    }
    
    // Load saved view mode
    document.addEventListener('DOMContentLoaded', function() {
        const savedMode = localStorage.getItem('productViewMode') || 'grid';
        setViewMode(savedMode);
        
        // Image error handling
        document.querySelectorAll('.product-image').forEach(img => {
            img.onerror = function() {
                this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.alt) + '&background=7C3AED&color=fff&size=256';
            };
        });
        
        // Auto-refresh stock alerts
        setInterval(() => {
            document.querySelectorAll('.stock-low').forEach(el => {
                const parentCard = el.closest('.product-card');
                if (parentCard) {
                    parentCard.style.animation = parentCard.style.animation ? '' : 'pulse 2s infinite';
                }
            });
        }, 3000);
    });
    
    // Animation for low stock
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    `;
    document.head.appendChild(style);
</script>

<?php require_once '../includes/footer.php'; ?>