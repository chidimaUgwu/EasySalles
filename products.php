
<?php
// products.php (FIXED VERSION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();

$page_title = 'Products';
include 'includes/header.php';
require 'config/db.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';

// Build query
$sql = "SELECT p.*, c.category_name, c.color 
        FROM EASYSALLES_PRODUCTS p
        LEFT JOIN EASYSALLES_CATEGORIES c ON p.category = c.category_name
        WHERE p.status = 'active'";
$params = [];

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($category) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

// Add sorting
switch ($sort) {
    case 'name_desc':
        $sql .= " ORDER BY p.product_name DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY p.unit_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.unit_price DESC";
        break;
    case 'stock_asc':
        $sql .= " ORDER BY p.current_stock ASC";
        break;
    case 'stock_desc':
        $sql .= " ORDER BY p.current_stock DESC";
        break;
    default: // name_asc
        $sql .= " ORDER BY p.product_name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM EASYSALLES_CATEGORIES ORDER BY category_name")->fetchAll();

// Get category counts
$category_counts = [];
foreach ($categories as $cat) {
    $count_sql = "SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE category = ? AND status = 'active'";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$cat['category_name']]);
    $category_counts[$cat['category_name']] = $count_stmt->fetchColumn();
}
?>

<style>
    .products-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .page-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .filters-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }
    
    @media (max-width: 992px) {
        .filters-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 576px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .filter-group {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
    }
    
    .filter-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .filter-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: 48px;
        white-space: nowrap;
    }
    
    .filter-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
    }
    
    .reset-btn {
        padding: 0.75rem 1.5rem;
        background: var(--card-bg);
        color: var(--text);
        border: 2px solid var(--border);
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: 48px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }
    
    .reset-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .category-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: var(--card-bg);
        border-radius: 15px;
        border: 1px solid var(--border);
    }
    
    .category-tab {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 2px solid transparent;
    }
    
    .category-tab:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .category-tab.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        color: var(--primary);
        font-weight: 600;
    }
    
    .category-count {
        font-size: 0.85rem;
        background: rgba(255, 255, 255, 0.3);
        padding: 0.1rem 0.5rem;
        border-radius: 10px;
        min-width: 24px;
        text-align: center;
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
    }
    
    @media (max-width: 768px) {
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }
    
    .product-card {
        background: var(--card-bg);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
    }
    
    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(124, 58, 237, 0.15);
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
        height: 200px;
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

    .cart-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 9999;
    transform: translateX(120%);
    transition: transform 0.3s ease;
    max-width: 400px;
    border-left: 4px solid;
}

    .cart-notification.show {
        transform: translateX(0);
    }

    .cart-notification-success {
        border-left-color: #10B981;
        background: linear-gradient(135deg, #10B98110, #10B98105);
    }

    .cart-notification-error {
        border-left-color: #EF4444;
        background: linear-gradient(135deg, #EF444410, #EF444405);
    }

    .cart-notification i {
        font-size: 1.5rem;
    }

    .cart-notification-success i {
        color: #10B981;
    }

    .cart-notification-error i {
        color: #EF4444;
    }

    .cart-notification span {
        flex: 1;
        font-weight: 500;
        color: var(--text);
    }

    .view-cart-btn {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .view-cart-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(124, 58, 237, 0.3);
    }

    .close-notification {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #64748b;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .close-notification:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--text);
    }

    .product-card.added-to-cart {
        border: 2px solid var(--primary);
        animation: pulse 1s;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(124, 58, 237, 0); }
        100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0); }
    }

    /* Add cart icon to header */
    .header-cart {
        position: relative;
        margin-right: 1rem;
    }

    .cart-icon {
        font-size: 1.5rem;
        color: var(--text);
        position: relative;
        text-decoration: none;
    }

    .cart-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        display: none;
    }
    
    .no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--primary);
        font-size: 4rem;
    }
    
    .product-header {
        padding: 1.5rem 1.5rem 0.5rem;
    }
    
    .product-code {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text);
        font-size: 0.9rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 0.75rem;
    }
    
    .product-name {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text);
        line-height: 1.3;
    }
    
    .product-description {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 1rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-details {
        padding: 0 1.5rem 1.5rem;
        flex-grow: 1;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .detail-item {
        text-align: center;
        padding: 0.75rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    
    .detail-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(124, 58, 237, 0.1);
    }
    
    .detail-label {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .detail-value {
        font-weight: 600;
        color: var(--text);
        font-size: 1.1rem;
    }
    
    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 1rem;
        width: fit-content;
    }
    
    .stock-indicator {
        height: 8px;
        background: var(--border);
        border-radius: 4px;
        margin: 1rem 0;
        overflow: hidden;
    }
    
    .stock-level {
        height: 100%;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    
    .stock-low { background: #EF4444; }
    .stock-medium { background: #F59E0B; }
    .stock-high { background: #10B981; }
    
    .stock-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 1.5rem;
    }
    
    .product-price-section {
        padding: 1.5rem;
        border-top: 2px solid var(--border);
        text-align: center;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01));
    }
    
    .price-label {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .price-value {
        font-family: 'Poppins', sans-serif;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .product-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .action-btn {
        flex: 1;
        padding: 0.75rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .add-to-cart-btn {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    
    .add-to-cart-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
    }
    
    .view-details-btn {
        background: var(--card-bg);
        color: var(--text);
        border: 2px solid var(--border);
    }
    
    .view-details-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        grid-column: 1 / -1;
        background: var(--card-bg);
        border-radius: 20px;
        border: 2px dashed var(--border);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: var(--border);
        margin-bottom: 1.5rem;
    }
    
    .empty-state h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state p {
        color: #64748b;
        margin-bottom: 1.5rem;
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
        font-size: 0.85rem;
        padding: 0.2rem 0.75rem;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    .sort-options {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .sort-btn {
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: var(--card-bg);
        border: 2px solid var(--border);
        color: var(--text);
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .sort-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .sort-btn.active {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        border-color: var(--primary);
        color: var(--primary);
        font-weight: 600;
    }
</style>



<div class="products-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-box"></i> Product Catalog
        </h1>
        
        <div class="sort-options">
            <span style="color: #64748b; font-size: 0.9rem;">Sort by:</span>
            <button class="sort-btn <?php echo $sort === 'name_asc' ? 'active' : ''; ?>" data-sort="name_asc">
                <i class="fas fa-sort-alpha-down"></i> Name A-Z
            </button>
            <button class="sort-btn <?php echo $sort === 'name_desc' ? 'active' : ''; ?>" data-sort="name_desc">
                <i class="fas fa-sort-alpha-down-alt"></i> Name Z-A
            </button>
            <button class="sort-btn <?php echo $sort === 'price_asc' ? 'active' : ''; ?>" data-sort="price_asc">
                <i class="fas fa-sort-numeric-down"></i> Price Low-High
            </button>
            <button class="sort-btn <?php echo $sort === 'price_desc' ? 'active' : ''; ?>" data-sort="price_desc">
                <i class="fas fa-sort-numeric-down-alt"></i> Price High-Low
            </button>
        </div>
    </div>
    
    <!-- Category Tabs -->
    <div class="category-tabs">
        <a href="products.php" class="category-tab <?php echo !$category ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>All Products</span>
            <span class="category-count">
                <?php 
                $all_count_sql = "SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE status = 'active'";
                echo $pdo->query($all_count_sql)->fetchColumn();
                ?>
            </span>
        </a>
        
        <?php foreach ($categories as $cat): ?>
            <a href="products.php?category=<?php echo urlencode($cat['category_name']); ?>" 
               class="category-tab <?php echo $category === $cat['category_name'] ? 'active' : ''; ?>"
               style="color: <?php echo $cat['color']; ?>; border-color: <?php echo $cat['color']; ?>20;">
                <i class="fas fa-tag"></i>
                <span><?php echo htmlspecialchars($cat['category_name']); ?></span>
                <span class="category-count"><?php echo $category_counts[$cat['category_name']] ?? 0; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="" id="productsFilter">
            <input type="hidden" name="sort" id="sortInput" value="<?php echo htmlspecialchars($sort); ?>">
            
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search Products</label>
                    <input type="text" name="search" class="filter-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, code, description, or barcode...">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" 
                                <?php echo $category === $cat['category_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                (<?php echo $category_counts[$cat['category_name']] ?? 0; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-control" id="sortSelect">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                
                <a href="products.php" class="reset-btn">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Products Count -->
    <div style="margin-bottom: 1.5rem; color: #64748b; font-size: 0.95rem;">
        <i class="fas fa-box"></i> 
        Showing <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?>
        <?php if ($category): ?>
            in <strong style="color: var(--primary);"><?php echo htmlspecialchars($category); ?></strong> category
        <?php endif; ?>
        <?php if ($search): ?>
            matching "<strong style="color: var(--primary);"><?php echo htmlspecialchars($search); ?></strong>"
        <?php endif; ?>
    </div>
    
    <!-- Products Grid -->
    <div class="products-grid">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Products Found</h3>
                <p>No products match your search criteria. Try a different search term or category.</p>
                <a href="products.php" class="filter-btn" style="text-decoration: none; display: inline-block;">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): 
                // Calculate stock percentage
                $max_stock = max($product['max_stock'], 1);
                $stock_percentage = ($product['current_stock'] / $max_stock) * 100;
                $stock_class = $stock_percentage <= 20 ? 'stock-low' : 
                              ($stock_percentage <= 50 ? 'stock-medium' : 'stock-high');
                
                // Default image if none
                $image_url = $product['image_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($product['product_name']) . '&background=' . substr($product['color'] ?? '7C3AED', 1) . '&color=fff&size=256';
            ?>
                <div class="product-card">
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
                            <span class="product-status status-active">
                                <i class="fas fa-circle"></i> <?php echo ucfirst($product['status']); ?>
                            </span>
                        </div>
                        
                        <h3 class="product-name" title="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </h3>
                        
                        <?php if ($product['category']): ?>
                            <div class="category-badge" style="background: <?php echo $product['color'] ?? '#06B6D4'; ?>20; color: <?php echo $product['color'] ?? '#06B6D4'; ?>;">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($product['category']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['description']): ?>
                            <p class="product-description" title="<?php echo htmlspecialchars($product['description']); ?>">
                                <?php echo htmlspecialchars($product['description']); ?>
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
                                    <small style="font-size: 0.8rem; color: #64748b;"><?php echo $product['unit_type']; ?></small>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Min Stock</div>
                                <div class="detail-value"><?php echo $product['min_stock']; ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Max Stock</div>
                                <div class="detail-value"><?php echo $product['max_stock']; ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Unit Type</div>
                                <div class="detail-value"><?php echo htmlspecialchars($product['unit_type']); ?></div>
                            </div>
                        </div>
                        
                        <!-- Stock Indicator -->
                        <div class="stock-indicator">
                            <div class="stock-level <?php echo $stock_class; ?>" 
                                 style="width: <?php echo min($stock_percentage, 100); ?>%"></div>
                        </div>
                        
                        <div class="stock-info">
                            <span>Stock Level</span>
                            <span><?php echo round($stock_percentage); ?>%</span>
                        </div>
                    </div>
                    
                    <!-- Price & Actions -->
                    <div class="product-price-section">
                        <div class="price-label">Price per <?php echo $product['unit_type']; ?></div>
                        <div class="price-value">$<?php echo number_format($product['unit_price'], 2); ?></div>
                        
                        <?php if ($product['cost_price']): ?>
                            <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">
                                Cost: $<?php echo number_format($product['cost_price'], 2); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Handle sort buttons
    document.addEventListener('DOMContentLoaded', function() {
        // Sort buttons
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const sortValue = this.dataset.sort;
                document.getElementById('sortInput').value = sortValue;
                document.getElementById('sortSelect').value = sortValue;
                document.getElementById('productsFilter').submit();
            });
        });
        
        // View product details
        window.viewProductDetails = function(productId) {
            // In a real implementation, this would show a modal with product details
            alert(`Viewing product details for ID: ${productId}`);
        };
        
        // Real-time search
        const searchInput = document.querySelector('input[name="search"]');
        const productCards = document.querySelectorAll('.product-card');
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            productCards.forEach(card => {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productCode = card.querySelector('.product-code').textContent.toLowerCase();
                const productDesc = card.querySelector('.product-description')?.textContent.toLowerCase() || '';
                
                if (productName.includes(searchTerm) || 
                    productCode.includes(searchTerm) || 
                    productDesc.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Image error handling
        document.querySelectorAll('.product-image').forEach(img => {
            img.onerror = function() {
                this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.alt) + '&background=7C3AED&color=fff&size=256';
            };
        });
        
        // Initialize cart count
        updateHeaderCartCount();
    });
    
    // Add to cart function for products.php
    window.addToCart = function(productId, quantity = 1) {
        // Show loading state
        const btn = event.target.closest('.add-to-cart-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;
        
        // Send AJAX request
        fetch('ajax/add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showNotification('success', data.message);
                
                // Update cart count in header
                updateHeaderCartCount();
                
                // Highlight product card
                const productCard = event.target.closest('.product-card');
                productCard.classList.add('added-to-cart');
                setTimeout(() => {
                    productCard.classList.remove('added-to-cart');
                }, 1000);
                
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Network error. Please try again.');
        })
        .finally(() => {
            // Restore button
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    };
    
    // Show notification
    function showNotification(type, message) {
        // Remove existing notifications
        const existing = document.querySelector('.cart-notification');
        if (existing) existing.remove();
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `cart-notification cart-notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
            <a href="sale-record.php" class="view-cart-btn">View Cart</a>
            <button class="close-notification">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button
        notification.querySelector('.close-notification').onclick = () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        };
    }
    
    // Update cart count in header
    function updateHeaderCartCount() {
        fetch('ajax/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = data.count > 0 ? `(${data.count})` : '';
                        cartCount.style.display = data.count > 0 ? 'inline' : 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error getting cart count:', error);
            });
    }
</script>

<?php include 'includes/footer.php'; ?>

