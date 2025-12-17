<?php
// admin/products/index.php (ENHANCED VERSION)
$page_title = "Manage Products";
require_once '../includes/header.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Get categories for filter
try {
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM EASYSALLES_PRODUCTS WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Build query with sorting
$query = "SELECT p.*, 
                 (SELECT SUM(quantity) FROM EASYSALLES_SALES_ITEMS WHERE product_id = p.product_id) as total_sold,
                 (p.current_stock * p.unit_price) as stock_value
          FROM EASYSALLES_PRODUCTS p 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.description LIKE ? OR p.barcode LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($status) && in_array($status, ['active', 'inactive', 'discontinued'])) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

// Add sorting
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY p.product_name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.product_name DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY p.unit_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.unit_price DESC";
        break;
    case 'stock_asc':
        $query .= " ORDER BY p.current_stock ASC";
        break;
    case 'stock_desc':
        $query .= " ORDER BY p.current_stock DESC";
        break;
    case 'popular':
        $query .= " ORDER BY total_sold DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Get products
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total products count and stats
$total_products = count($products);
$active_count = 0;
$low_stock_count = 0;
$total_stock_value = 0;

foreach ($products as $product) {
    if ($product['status'] == 'active') $active_count++;
    if ($product['current_stock'] <= $product['min_stock']) $low_stock_count++;
    $total_stock_value += ($product['current_stock'] * $product['unit_price']);
}
?>

<style>
    /* Enhanced Products Container */
    .products-admin-container {
        max-width: 1800px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .page-header-admin {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    
    .page-title-section {
        flex: 1;
    }
    
    .page-title-admin {
        font-family: 'Poppins', sans-serif;
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
    }
    
    .page-subtitle {
        color: #64748b;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }
    
    .page-actions-admin {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .btn-gradient-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        padding: 0.875rem 1.75rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2);
    }
    
    .btn-gradient-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
    }
    
    .btn-gradient-secondary {
        background: linear-gradient(135deg, #3b82f6, #06b6d4);
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-gradient-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .stat-trend {
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .trend-up { color: #10b981; }
    .trend-down { color: #ef4444; }
    
    /* Enhanced Filters */
    .filters-card-admin {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .filters-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .filters-grid-admin {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
        gap: 1rem;
        align-items: end;
    }
    
    @media (max-width: 1400px) {
        .filters-grid-admin {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .filters-grid-admin {
            grid-template-columns: 1fr;
        }
    }
    
    .filter-group-admin {
        margin-bottom: 0;
    }
    
    .filter-label-admin {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.95rem;
    }
    
    .filter-control-admin {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .filter-control-admin:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    /* Enhanced Products Table */
    .products-table-container {
        background: var(--card-bg);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        margin-bottom: 2rem;
    }
    
    .table-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01));
    }
    
    .table-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.4rem;
        font-weight: 600;
        color: var(--text);
    }
    
    .table-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .table-btn {
        padding: 0.625rem 1.25rem;
        border-radius: 10px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid var(--border);
        background: var(--card-bg);
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .table-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Enhanced Table */
    .enhanced-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .enhanced-table thead {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .enhanced-table th {
        padding: 1.25rem 1.5rem;
        text-align: left;
        font-weight: 600;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        font-size: 0.95rem;
    }
    
    .enhanced-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--border);
    }
    
    .enhanced-table tbody tr:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.02), rgba(236, 72, 153, 0.01));
        transform: translateX(4px);
    }
    
    .enhanced-table td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
    }
    
    /* Product Cell */
    .product-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .product-image-sm {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1.5rem;
    }
    
    .product-info {
        flex: 1;
    }
    
    .product-name-sm {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.25rem;
        font-size: 1rem;
    }
    
    .product-code-sm {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    /* Category Badge */
    .category-badge-sm {
        padding: 0.4rem 0.9rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        background: rgba(124, 58, 237, 0.1);
        color: var(--primary);
        display: inline-block;
    }
    
    /* Price Display */
    .price-display {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .current-price {
        font-weight: 700;
        color: var(--success);
        font-size: 1.1rem;
    }
    
    .cost-price {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    /* Stock Display */
    .stock-display {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stock-bar {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
    }
    
    .stock-level-bar {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s ease;
    }
    
    .stock-info-sm {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #64748b;
    }
    
    /* Status Badge */
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .status-inactive {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    
    .status-discontinued {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    /* Actions Cell */
    .actions-cell {
        display: flex;
        gap: 0.5rem;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.9rem;
        position: relative;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .btn-view:hover {
        background: rgba(59, 130, 246, 0.2);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    
    .btn-edit {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }
    
    .btn-edit:hover {
        background: rgba(139, 92, 246, 0.2);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
    }
    
    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    .btn-delete:hover {
        background: rgba(239, 68, 68, 0.2);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }
    
    /* Empty State */
    .empty-state-admin {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border-radius: 20px;
        border: 2px dashed var(--border);
        margin: 2rem 0;
    }
    
    .empty-state-icon {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 3rem;
        color: var(--primary);
    }
    
    .empty-state-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .empty-state-text {
        color: #64748b;
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    .quick-action-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    
    .quick-action-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 10px 30px rgba(124, 58, 237, 0.1);
    }
    
    .action-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.8rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(236, 72, 153, 0.05));
        color: var(--primary);
    }
    
    .action-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    
    .action-desc {
        font-size: 0.9rem;
        color: #64748b;
    }
    
    /* Confirmation Modal */
    .confirmation-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    
    .modal-content {
        background: var(--card-bg);
        border-radius: 20px;
        max-width: 500px;
        width: 100%;
        overflow: hidden;
        border: 1px solid var(--border);
        transform: translateY(-20px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .modal-content.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .modal-header {
        padding: 1.5rem 2rem;
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        border-bottom: 1px solid var(--border);
    }
    
    .modal-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        color: #ef4444;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-body {
        padding: 2rem;
    }
    
    .modal-footer {
        padding: 1.5rem 2rem;
        background: var(--bg);
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }
    
    .btn-modal-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-modal-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }
    
    .btn-modal-cancel {
        background: var(--card-bg);
        color: var(--text);
        border: 2px solid var(--border);
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-modal-cancel:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    /* Tooltip */
    .tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.85rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        margin-bottom: 0.5rem;
        z-index: 100;
    }
    
    .tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: #1f2937 transparent transparent transparent;
    }
    
    .action-btn:hover .tooltip {
        opacity: 1;
        visibility: visible;
    }
</style>

<div class="products-admin-container">
    <!-- Page Header -->
    <div class="page-header-admin">
        <div class="page-title-section">
            <h1 class="page-title-admin">
                <i class="fas fa-boxes"></i> Product Management
            </h1>
            <p class="page-subtitle">
                Manage your inventory, track stock levels, and update product information
            </p>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" onclick="filterByStatus('')">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05)); color: var(--primary);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value"><?php echo $total_products; ?></div>
                        <div class="stat-trend">
                            <i class="fas fa-chart-line"></i>
                            <span>All inventory items</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="filterByStatus('active')">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Active Products</div>
                        <div class="stat-value"><?php echo $active_count; ?></div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>Available for sale</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='../inventory/low-stock.php'">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); color: #f59e0b;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Low Stock</div>
                        <div class="stat-value"><?php echo $low_stock_count; ?></div>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-arrow-down"></i>
                            <span>Need restocking</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='../reports/inventory-value.php'">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05)); color: #3b82f6;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Stock Value</div>
                        <div class="stat-value">$<?php echo number_format($total_stock_value, 0); ?></div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-chart-line"></i>
                            <span>Current inventory worth</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-actions-admin">
            <a href="add.php" class="btn-gradient-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
            <a href="categories.php" class="btn-gradient-secondary">
                <i class="fas fa-tags"></i> Categories
            </a>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="filters-card-admin">
        <div class="filters-header">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i> Filter & Search Products
            </h3>
            <div class="table-actions">
                <button class="table-btn" onclick="exportToCSV()">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
                <button class="table-btn" onclick="printProducts()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <form method="GET" action="" id="productsFilter">
            <input type="hidden" name="sort" id="sortInput" value="<?php echo htmlspecialchars($sort); ?>">
            
            <div class="filters-grid-admin">
                <div class="filter-group-admin">
                    <label class="filter-label-admin">Search Products</label>
                    <input type="text" 
                           name="search" 
                           class="filter-control-admin" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, code, or barcode...">
                </div>
                
                <div class="filter-group-admin">
                    <label class="filter-label-admin">Category</label>
                    <select name="category" class="filter-control-admin">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group-admin">
                    <label class="filter-label-admin">Status</label>
                    <select name="status" class="filter-control-admin">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>
                
                <div class="filter-group-admin">
                    <label class="filter-label-admin">Sort By</label>
                    <select name="sort" class="filter-control-admin" id="sortSelect" onchange="document.getElementById('sortInput').value=this.value; document.getElementById('productsFilter').submit();">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_asc" <?php echo $sort == 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="stock_desc" <?php echo $sort == 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-gradient-primary" style="height: 48px;">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                
                <a href="index.php" class="btn-modal-cancel" style="height: 48px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Products Table -->
    <div class="products-table-container">
        <div class="table-header">
            <h3 class="table-title">Products List</h3>
            <div style="color: #64748b; font-size: 0.95rem;">
                <i class="fas fa-info-circle"></i> 
                Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
            </div>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="empty-state-admin">
                <div class="empty-state-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3 class="empty-state-title">No Products Found</h3>
                <p class="empty-state-text">
                    <?php if ($search || $category || $status): ?>
                        No products match your search criteria. Try adjusting your filters.
                    <?php else: ?>
                        You haven't added any products yet. Start building your inventory by adding your first product.
                    <?php endif; ?>
                </p>
                <a href="add.php" class="btn-gradient-primary">
                    <i class="fas fa-plus"></i> Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <table class="enhanced-table" id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $index => $product): 
                        // Calculate stock percentage
                        $max_stock = max($product['max_stock'], 1);
                        $stock_percentage = ($product['current_stock'] / $max_stock) * 100;
                        $stock_class = $stock_percentage <= 20 ? 'stock-low' : 
                                      ($stock_percentage <= 50 ? 'stock-medium' : 'stock-high');
                        $stock_color = $stock_percentage <= 20 ? '#ef4444' : 
                                      ($stock_percentage <= 50 ? '#f59e0b' : '#10b981');
                        
                        // Status badge class
                        $status_class = 'status-' . $product['status'];
                        
                        // Product image
                        $image_url = $product['image_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($product['product_name']) . '&background=7C3AED&color=fff&size=256';
                    ?>
                    <tr id="product-row-<?php echo $product['product_id']; ?>">
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="product-cell">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                         class="product-image-sm">
                                <?php else: ?>
                                    <div class="product-image-sm">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-info">
                                    <div class="product-name-sm"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-code-sm">SKU: <?php echo htmlspecialchars($product['product_code']); ?></div>
                                    <?php if ($product['description']): ?>
                                        <div class="product-code-sm" style="margin-top: 0.25rem; font-size: 0.85rem; color: #94a3b8;">
                                            <?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($product['category']): ?>
                                <span class="category-badge-sm"><?php echo htmlspecialchars($product['category']); ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Uncategorized</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="price-display">
                                <span class="current-price">$<?php echo number_format($product['unit_price'], 2); ?></span>
                                <span class="cost-price">Cost: $<?php echo number_format($product['cost_price'] ?? 0, 2); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="stock-display">
                                <div class="stock-info-sm">
                                    <span><?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?></span>
                                    <span><?php echo round($stock_percentage); ?>%</span>
                                </div>
                                <div class="stock-bar">
                                    <div class="stock-level-bar" style="width: <?php echo min($stock_percentage, 100); ?>%; background: <?php echo $stock_color; ?>;"></div>
                                </div>
                                <div class="stock-info-sm">
                                    <span style="font-size: 0.8rem;">Min: <?php echo $product['min_stock']; ?></span>
                                    <span style="font-size: 0.8rem;">Max: <?php echo $product['max_stock']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="view.php?id=<?php echo $product['product_id']; ?>" 
                                   class="action-btn btn-view"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                    <span class="tooltip">View Details</span>
                                </a>
                                
                                <a href="edit.php?id=<?php echo $product['product_id']; ?>" 
                                   class="action-btn btn-edit"
                                   title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                    <span class="tooltip">Edit Product</span>
                                </a>
                                
                                <button onclick="showDeleteModal(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['product_name']); ?>')" 
                                        class="action-btn btn-delete"
                                        title="Delete Product">
                                    <i class="fas fa-trash"></i>
                                    <span class="tooltip">Delete Product</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions-grid">
        <a href="add.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-plus"></i>
            </div>
            <div class="action-title">Add Product</div>
            <div class="action-desc">Add new product to inventory</div>
        </a>
        
        <a href="../inventory/stock-adjustment.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="action-title">Adjust Stock</div>
            <div class="action-desc">Update stock levels</div>
        </a>
        
        <a href="../inventory/low-stock.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="action-title">Low Stock</div>
            <div class="action-desc">View items needing restock</div>
        </a>
        
        <a href="categories.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-tags"></i>
            </div>
            <div class="action-title">Categories</div>
            <div class="action-desc">Manage product categories</div>
        </a>
        
        <a href="suppliers.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="action-title">Suppliers</div>
            <div class="action-desc">Manage suppliers</div>
        </a>
        
        <a href="../reports/products.php" class="quick-action-card">
            <div class="action-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-title">Reports</div>
            <div class="action-desc">View product reports</div>
        </a>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="confirmation-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
            </h3>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 10px;">
                <p style="margin: 0; color: #ef4444; font-weight: 500;">
                    <i class="fas fa-exclamation-circle"></i> 
                    This action cannot be undone. The product will be permanently deleted.
                </p>
            </div>
            
            <p>Are you sure you want to delete <strong id="productNameToDelete"></strong>?</p>
            
            <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg); border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
                    <span style="font-weight: 500;">Deletion will:</span>
                </div>
                <ul style="color: var(--text-light); margin: 0.5rem 0 0 1rem;">
                    <li>Remove product from inventory</li>
                    <li>Delete all sales records for this product</li>
                    <li>Remove product images and data</li>
                    <li>Affect sales reports and analytics</li>
                </ul>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                    Type <span style="color: #ef4444;">DELETE</span> to confirm:
                </label>
                <input type="text" 
                       id="deleteConfirmation" 
                       class="filter-control-admin" 
                       placeholder="Type DELETE here"
                       style="border-color: #ef4444;">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn-modal-danger" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Delete Product
            </button>
        </div>
    </div>
</div>

<script>
    // Product deletion variables
    let productIdToDelete = null;
    let productNameToDelete = '';
    
    // Show delete confirmation modal
    function showDeleteModal(productId, productName) {
        productIdToDelete = productId;
        productNameToDelete = productName;
        
        const modal = document.getElementById('deleteModal');
        const modalContent = modal.querySelector('.modal-content');
        const productNameSpan = document.getElementById('productNameToDelete');
        
        productNameSpan.textContent = productName;
        document.getElementById('deleteConfirmation').value = '';
        
        modal.style.display = 'flex';
        setTimeout(() => {
            modalContent.classList.add('show');
        }, 10);
    }
    
    // Close delete modal
    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        const modalContent = modal.querySelector('.modal-content');
        
        modalContent.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
    
    // Confirm and delete product
    function confirmDelete() {
        const confirmation = document.getElementById('deleteConfirmation').value;
        
        if (confirmation !== 'DELETE') {
            alert('Please type "DELETE" to confirm deletion.');
            document.getElementById('deleteConfirmation').focus();
            return;
        }
        
        // Show loading state
        const deleteBtn = document.querySelector('.btn-modal-danger');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        deleteBtn.disabled = true;
        
        // Send AJAX request to delete product
        fetch('ajax/delete_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productIdToDelete}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showNotification('success', `Product "${productNameToDelete}" has been deleted successfully.`);
                
                // Remove row from table
                const row = document.getElementById(`product-row-${productIdToDelete}`);
                if (row) {
                    row.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                    setTimeout(() => {
                        row.remove();
                        
                        // Update stats
                        updateStatsAfterDeletion();
                        
                        // Check if table is empty
                        const tableBody = document.querySelector('#productsTable tbody');
                        if (tableBody.children.length === 0) {
                            location.reload();
                        }
                    }, 500);
                }
                
                // Close modal
                closeDeleteModal();
            } else {
                showNotification('error', data.message || 'Failed to delete product.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Network error. Please try again.');
        })
        .finally(() => {
            // Restore button
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    }
    
    // Update stats after deletion
    function updateStatsAfterDeletion() {
        // Update total products count
        const totalProductsStat = document.querySelector('.stat-card:nth-child(1) .stat-value');
        if (totalProductsStat) {
            const current = parseInt(totalProductsStat.textContent);
            totalProductsStat.textContent = current - 1;
        }
        
        // Update active products count (if the deleted product was active)
        // Note: This is a simplified update. In a real app, you'd need to know the product status
        const activeProductsStat = document.querySelector('.stat-card:nth-child(2) .stat-value');
        if (activeProductsStat) {
            // This would require additional logic to check the product status
            // For now, we'll just reload the page stats
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    }
    
    // Show notification
    function showNotification(type, message) {
        // Remove existing notifications
        const existing = document.querySelector('.notification-toast');
        if (existing) existing.remove();
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification-toast notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
            <button class="close-notification">&times;</button>
        `;
        
        // Add styles for notification
        notification.style.cssText = `
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
            border-left-color: ${type === 'success' ? '#10B981' : '#EF4444'};
            background: linear-gradient(135deg, ${type === 'success' ? '#10B98110' : '#EF444410'}, ${type === 'success' ? '#10B98105' : '#EF444405'});
        `;
        
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.style.transform = 'translateX(0)', 10);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(120%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button
        notification.querySelector('.close-notification').onclick = () => {
            notification.style.transform = 'translateX(120%)';
            setTimeout(() => notification.remove(), 300);
        };
    }
    
    // Export to CSV
    function exportToCSV() {
        const table = document.getElementById('productsTable');
        if (!table) {
            showNotification('error', 'No products to export');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            for (const col of cols) {
                // Remove actions column
                if (col.querySelector('.actions-cell')) {
                    continue;
                }
                
                // Clean up text content
                let text = col.innerText.replace(/,/g, '');
                
                // Handle product name
                if (col.querySelector('.product-name-sm')) {
                    text = col.querySelector('.product-name-sm').innerText;
                }
                
                // Handle price
                if (col.querySelector('.current-price')) {
                    text = col.querySelector('.current-price').innerText.replace('$', '');
                }
                
                rowData.push(text);
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
        
        showNotification('success', 'Products exported successfully');
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
                    h1 { color: #333; margin-bottom: 10px; }
                    .print-header { margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h1>Products List - EasySalles</h1>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                    <p>Total Products: ${total_products}</p>
                </div>
        `);
        
        const table = document.getElementById('productsTable');
        if (table) {
            printWindow.document.write(table.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, '')
                                                      .replace(/<a[^>]*>.*?<\/a>/g, '')
                                                      .replace(/<i[^>]*>.*?<\/i>/g, ''));
        }
        
        printWindow.document.write(`
                <div style="margin-top: 30px; font-size: 12px; color: #666;">
                    <p>EasySalles POS System - Product Management Report</p>
                </div>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }
    
    // Filter by status
    function filterByStatus(status) {
        const url = new URL(window.location.href);
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        window.location.href = url.toString();
    }
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // Initialize tooltips
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        });
    });
    
    // Image error handling
    document.querySelectorAll('.product-image-sm').forEach(img => {
        img.onerror = function() {
            if (this.tagName === 'IMG') {
                this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.alt) + '&background=7C3AED&color=fff&size=256';
            }
        };
    });
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
// Create ajax/delete_product.php file:
/*
<?php
require_once '../../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$product_id = $_POST['product_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete from sales items
    $stmt = $pdo->prepare("DELETE FROM EASYSALLES_SALES_ITEMS WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Delete from inventory log
    $stmt = $pdo->prepare("DELETE FROM EASYSALLES_INVENTORY_LOG WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Delete the product
    $stmt = $pdo->prepare("DELETE FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Product deleted successfully'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
*/
?>