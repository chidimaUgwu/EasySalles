<?php
// products.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();

$page_title = 'Products';
include 'includes/header.php';
require 'config/db.php';

// Get products with filter
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT p.*, c.category_name, c.color 
        FROM EASYSALLES_PRODUCTS p
        LEFT JOIN EASYSALLES_CATEGORIES c ON p.category = c.category_name
        WHERE p.status = 'active'";
$params = [];

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($category) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY p.product_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category_name FROM EASYSALLES_CATEGORIES ORDER BY category_name")->fetchAll();
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
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }
    
    @media (max-width: 768px) {
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
    }
    
    .reset-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .product-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(124, 58, 237, 0.15);
    }
    
    .product-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
    }
    
    .product-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .product-code {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text);
        font-size: 0.9rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }
    
    .category-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        background: var(--border);
        color: var(--text);
    }
    
    .product-name {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .product-description {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }
    
    .product-details {
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
    }
    
    .detail-label {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .detail-value {
        font-weight: 600;
        color: var(--text);
    }
    
    .product-price {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--border);
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
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        grid-column: 1 / -1;
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
</style>

<div class="products-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-box"></i> Products
        </h1>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search Products</label>
                    <input type="text" name="search" class="filter-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, code, or description...">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" 
                                <?php echo $category === $cat['category_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                
                <a href="products.php" class="reset-btn">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Products Grid -->
    <div class="products-grid">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Products Found</h3>
                <p>No products match your search criteria. Try a different search term.</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): 
                // Calculate stock percentage
                $stock_percentage = ($product['current_stock'] / $product['max_stock']) * 100;
                $stock_class = $stock_percentage <= 20 ? 'stock-low' : 
                              ($stock_percentage <= 50 ? 'stock-medium' : 'stock-high');
            ?>
                <div class="product-card">
                    <div class="product-header">
                        <span class="product-code"><?php echo htmlspecialchars($product['product_code']); ?></span>
                        <?php if ($product['category']): ?>
                            <span class="category-badge" style="background: <?php echo $product['color'] ?? '#06B6D4'; ?>20; color: <?php echo $product['color'] ?? '#06B6D4'; ?>;">
                                <?php echo htmlspecialchars($product['category']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    
                    <?php if ($product['description']): ?>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="product-details">
                        <div class="detail-item">
                            <div class="detail-label">Current Stock</div>
                            <div class="detail-value"><?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Min Stock</div>
                            <div class="detail-value"><?php echo $product['min_stock']; ?> <?php echo $product['unit_type']; ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Max Stock</div>
                            <div class="detail-value"><?php echo $product['max_stock']; ?> <?php echo $product['unit_type']; ?></div>
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
                    
                    <div class="product-price">
                        <div class="price-label">Price per <?php echo $product['unit_type']; ?></div>
                        <div class="price-value">$<?php echo number_format($product['unit_price'], 2); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Quick search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        const productCards = document.querySelectorAll('.product-card');
        
        // Real-time search if enabled
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            productCards.forEach(card => {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productCode = card.querySelector('.product-code').textContent.toLowerCase();
                const productDesc = card.querySelector('.product-description')?.textContent.toLowerCase() || '';
                
                if (productName.includes(searchTerm) || 
                    productCode.includes(searchTerm) || 
                    productDesc.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>