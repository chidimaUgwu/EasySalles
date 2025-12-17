<?php
// admin/products/view.php
$page_title = "Product Details";
require_once '../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = intval($_GET['id']);

// Get product details - FIXED QUERY
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               s.supplier_name, 
               s.contact_person, 
               s.phone as supplier_phone,
               s.email as supplier_email
        FROM EASYSALLES_PRODUCTS p
        LEFT JOIN EASYSALLES_SUPPLIERS s ON p.supplier = s.supplier_name
        WHERE p.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo '<div class="alert alert-error">Product not found!</div>';
        echo '<a href="index.php" class="btn btn-primary">Back to Products</a>';
        require_once '../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error: ' . $e->getMessage() . '</div>';
    require_once '../includes/footer.php';
    exit;
}

// Get inventory history
$inventory_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM EASYSALLES_INVENTORY_LOG 
        WHERE product_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$product_id]);
    $inventory_history = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get recent sales - FIXED TABLE NAME
$recent_sales = [];
try {
    $stmt = $pdo->prepare("
        SELECT si.*, s.sale_date, s.transaction_code, s.customer_name
        FROM EASYSALLES_SALE_ITEMS si
        JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
        WHERE si.product_id = ? 
        ORDER BY s.sale_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$product_id]);
    $recent_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet or no sales
}

// Calculate stock percentage
$max_stock = $product['max_stock'] > 0 ? $product['max_stock'] : 100;
$stock_percent = ($product['current_stock'] / $max_stock) * 100;
$stock_status = 'success';
if ($product['current_stock'] <= $product['min_stock']) {
    $stock_status = 'error';
} elseif ($stock_percent < 30) {
    $stock_status = 'warning';
}

// Get total sales stats - FIXED COLUMN NAMES
$sales_stats = ['total_sold' => 0, 'total_revenue' => 0, 'avg_sale_price' => 0, 'sale_count' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(si.quantity) as total_sold,
            SUM(si.subtotal) as total_revenue,
            AVG(si.unit_price) as avg_sale_price,
            COUNT(DISTINCT s.sale_id) as sale_count
        FROM EASYSALLES_SALE_ITEMS si
        JOIN EASYSALLES_SALES s ON si.sale_id = s.sale_id
        WHERE si.product_id = ? AND s.payment_status = 'paid'
    ");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch();
    if ($result) {
        $sales_stats = $result;
    }
} catch (PDOException $e) {
    // Table might not exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EasySalles</title>
    <style>
        :root {
            --primary: #06B6D4;
            --primary-light: #e6f7ff;
            --secondary: #7C3AED;
            --success: #10B981;
            --success-light: #d1fae5;
            --warning: #F59E0B;
            --warning-light: #fef3c7;
            --error: #EF4444;
            --error-light: #fee2e2;
            --info: #3B82F6;
            --accent: #8B5CF6;
            --accent-light: #f5f3ff;
            --bg: #f8fafc;
            --card-bg: white;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            overflow-y: auto;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            overflow: visible;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title h2 {
            font-size: 1.5rem;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .page-title p {
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .btn:hover {
            background: var(--bg);
            border-color: var(--primary);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: #0891b2;
            border-color: #0891b2;
        }
        
        .btn-outline {
            background: transparent;
            border-color: var(--border);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        /* MAIN CONTENT LAYOUT FIX */
        .main-content {
            display: flex;
            flex-direction: column;
            width: 100%;
            min-height: calc(100vh - 150px);
            overflow: visible;
        }
        
        .dashboard-row {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 1.5rem;
            margin: 1.5rem 0;
            width: 100%;
            overflow: visible;
        }
        
        .col-8 {
            grid-column: span 8;
            width: 100%;
            overflow: visible;
        }
        
        .col-4 {
            grid-column: span 4;
            width: 100%;
            overflow: visible;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
            width: 100%;
            min-height: fit-content;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .badge-success {
            background: var(--success-light);
            color: var(--success);
        }
        
        .badge-warning {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .badge-error {
            background: var(--error-light);
            color: var(--error);
        }
        
        .badge-primary {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .badge-secondary {
            background: #ede9fe;
            color: var(--secondary);
        }
        
        .badge-info {
            background: #dbeafe;
            color: var(--info);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid transparent;
        }
        
        .alert-error {
            background: var(--error-light);
            color: var(--error);
            border-color: var(--error);
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .table th {
            font-weight: 600;
            color: var(--text-light);
            background: var(--bg);
        }
        
        .text-muted {
            color: var(--text-light);
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Product info grid fix */
        .product-info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            width: 100%;
        }
        
        .product-image-container {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-details {
            width: 100%;
            overflow: visible;
        }
        
        /* Price stats grid */
        .price-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 2rem;
            width: 100%;
        }
        
        .price-stat-box {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Quick actions grid */
        .quick-actions-grid {
            display: grid;
            gap: 0.8rem;
            width: 100%;
        }
        
        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
            
            .col-8, .col-4 {
                grid-column: span 12;
            }
        }
        
        @media (max-width: 992px) {
            .product-info-grid {
                grid-template-columns: 1fr;
            }
            
            .price-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .page-header {
                flex-direction: column;
                text-align: left;
                align-items: flex-start;
            }
            
            .page-actions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .price-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .btn-group {
                width: 100%;
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .page-title h2 {
                font-size: 1.25rem;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Product Details</h2>
                <p>View complete product information and history</p>
            </div>
            <div class="page-actions">
                <a href="index.php" class="btn btn-outline">
                    ← Back to Products
                </a>
                <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                    ✏️ Edit Product
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Print
                </button>
            </div>
        </div>

        <div class="page-header">
            <div class="dashboard-row">
                <div class="col-8">
                    <!-- Product Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                📦 Product Information
                            </h3>
                            <div class="btn-group">
                                <span class="badge <?php echo $product['status'] == 'active' ? 'badge-success' : ($product['status'] == 'inactive' ? 'badge-warning' : 'badge-error'); ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="padding: 1.5rem;">
                            <div class="product-info-grid">
                                <div class="product-image-container">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="text-align: center; color: var(--primary);">
                                            <span style="font-size: 3rem;">📦</span>
                                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">No Image</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <h2 style="margin-top: 0; word-wrap: break-word;"><?php echo htmlspecialchars($product['product_name']); ?></h2>
                                    <p class="text-muted">SKU: <?php echo htmlspecialchars($product['product_code']); ?></p>
                                    
                                    <div style="display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap;">
                                        <?php if (!empty($product['category'])): ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($product['category']); ?></span>
                                        <?php endif; ?>
                                        <span class="badge badge-secondary"><?php echo ucfirst($product['unit_type']); ?></span>
                                        <?php if (!empty($product['barcode'])): ?>
                                            <span class="badge badge-info">Barcode: <?php echo htmlspecialchars($product['barcode']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($product['description'])): ?>
                                        <div style="margin: 1rem 0; padding: 1rem; background: var(--bg); border-radius: 10px;">
                                            <h4>Description</h4>
                                            <p style="word-wrap: break-word;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Price and Stock Information -->
                            <div class="price-stats-grid">
                                <div class="price-stat-box" style="background: var(--success-light);">
                                    <small class="text-muted">Selling Price</small>
                                    <h3 style="color: var(--success); margin: 0.5rem 0;">$<?php echo number_format($product['unit_price'], 2); ?></h3>
                                    <small>Per <?php echo $product['unit_type']; ?></small>
                                </div>
                                <div class="price-stat-box" style="background: var(--warning-light);">
                                    <small class="text-muted">Cost Price</small>
                                    <h3 style="color: var(--warning); margin: 0.5rem 0;">$<?php echo number_format($product['cost_price'] ?? 0, 2); ?></h3>
                                    <small>
                                        Profit: $<?php echo number_format($product['unit_price'] - ($product['cost_price'] ?? 0), 2); ?>
                                        <?php if ($product['cost_price'] > 0): ?>
                                            (<?php echo number_format((($product['unit_price'] - $product['cost_price']) / $product['cost_price']) * 100, 1); ?>%)
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="price-stat-box" style="background: var(--primary-light);">
                                    <small class="text-muted">Current Stock Value</small>
                                    <h3 style="color: var(--primary); margin: 0.5rem 0;">$<?php echo number_format($product['current_stock'] * $product['unit_price'], 2); ?></h3>
                                    <small><?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?> in stock</small>
                                </div>
                            </div>
                            
                            <!-- Stock Progress Bar -->
                            <div style="margin-top: 2rem; width: 100%;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Stock Level</span>
                                    <span><?php echo $product['current_stock']; ?> / <?php echo $product['max_stock']; ?> <?php echo $product['unit_type']; ?></span>
                                </div>
                                <div style="height: 10px; background: var(--border); border-radius: 5px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo min($stock_percent, 100); ?>%; 
                                        background: var(--<?php echo $stock_status; ?>); border-radius: 5px;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                    <small>Min: <?php echo $product['min_stock']; ?></small>
                                    <small>Max: <?php echo $product['max_stock']; ?></small>
                                </div>
                                <?php if ($stock_status == 'error'): ?>
                                    <div style="color: var(--error); margin-top: 0.5rem;">
                                        ⚠️ Low stock alert! Reorder needed.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Supplier Information -->
                    <?php if (!empty($product['supplier']) || !empty($product['supplier_name'])): ?>
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3 class="card-title">
                                🚚 Supplier Information
                            </h3>
                        </div>
                        <div style="padding: 1.5rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div>
                                    <p><strong>Supplier:</strong> 
                                        <?php echo htmlspecialchars($product['supplier'] ?? $product['supplier_name']); ?>
                                    </p>
                                    <?php if (!empty($product['contact_person'])): ?>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['contact_person']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($product['supplier_email'])): ?>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($product['supplier_email']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (!empty($product['supplier_phone'])): ?>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($product['supplier_phone']); ?></p>
                                    <?php endif; ?>
                                    <a href="suppliers.php" class="btn btn-outline" style="margin-top: 1rem;">
                                        🔗 View Supplier Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Sales -->
                    <?php if (!empty($recent_sales)): ?>
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3 class="card-title">
                                🛒 Recent Sales
                            </h3>
                        </div>
                        <div style="padding: 1.5rem;">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Transaction</th>
                                            <th>Customer</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sales as $sale): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                            <td>
                                                <a href="../sales/view.php?id=<?php echo $sale['sale_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                                    <?php echo htmlspecialchars($sale['transaction_code']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                            <td><?php echo $sale['quantity']; ?></td>
                                            <td>$<?php echo number_format($sale['unit_price'], 2); ?></td>
                                            <td><strong>$<?php echo number_format($sale['subtotal'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="../sales/index.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                                    View All Sales for This Product
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-4">
                    <!-- Quick Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                📊 Sales Statistics
                            </h3>
                        </div>
                        <div style="padding: 1.5rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                <div style="text-align: center;">
                                    <small class="text-muted">Total Sold</small>
                                    <h2 style="margin: 0.5rem 0;"><?php echo $sales_stats['total_sold'] ?? 0; ?></h2>
                                    <small><?php echo $product['unit_type']; ?></small>
                                </div>
                                <div style="text-align: center;">
                                    <small class="text-muted">Total Revenue</small>
                                    <h2 style="margin: 0.5rem 0; color: var(--success);">$<?php echo number_format($sales_stats['total_revenue'] ?? 0, 2); ?></h2>
                                    <small>From sales</small>
                                </div>
                            </div>
                            
                            <div style="margin-top: 1.5rem;">
                                <small class="text-muted">Average Sale Price</small>
                                <h4 style="margin: 0.5rem 0;">$<?php echo number_format($sales_stats['avg_sale_price'] ?? $product['unit_price'], 2); ?></h4>
                                
                                <small class="text-muted">Number of Sales</small>
                                <h4 style="margin: 0.5rem 0;"><?php echo $sales_stats['sale_count'] ?? 0; ?></h4>
                                
                                <small class="text-muted">Created On</small>
                                <h4 style="margin: 0.5rem 0;"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></h4>
                                
                                <?php if (!empty($product['updated_at']) && $product['updated_at'] != $product['created_at']): ?>
                                    <small class="text-muted">Last Updated</small>
                                    <h4 style="margin: 0.5rem 0;"><?php echo date('M d, Y H:i', strtotime($product['updated_at'])); ?></h4>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inventory History -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3 class="card-title">
                                📋 Recent Stock Changes
                            </h3>
                        </div>
                        <div style="padding: 1.5rem; max-height: 300px; overflow-y: auto;">
                            <?php if (empty($inventory_history)): ?>
                                <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                                    <span style="font-size: 3rem; opacity: 0.3;">📋</span>
                                    <p style="margin-top: 1rem;">No inventory history recorded</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($inventory_history as $history): ?>
                                    <div style="margin-bottom: 1rem; padding: 0.8rem; background: var(--bg); border-radius: 8px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                                            <strong style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $history['change_type']); ?></strong>
                                            <span class="badge <?php echo $history['change_type'] == 'stock_in' ? 'badge-success' : 'badge-error'; ?>">
                                                <?php echo $history['change_type'] == 'stock_in' ? '+' : '-'; ?>
                                                <?php echo $history['quantity_change']; ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-light);">
                                            <?php echo date('M d, H:i', strtotime($history['created_at'])); ?>
                                            <?php if (!empty($history['notes'])): ?>
                                                · <?php echo htmlspecialchars($history['notes']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.85rem; margin-top: 0.3rem;">
                                            <small>Stock: <?php echo $history['previous_stock']; ?> → <?php echo $history['new_stock']; ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3 class="card-title">
                                ⚡ Quick Actions
                            </h3>
                        </div>
                        <div style="padding: 1.5rem;">
                            <div class="quick-actions-grid">
                                <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                                    ✏️ Edit Product
                                </a>
                                
                                <a href="../inventory/stock-adjustment.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                                    🔄 Adjust Stock
                                </a>
                                
                                <a href="../sales/create.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline">
                                    🛍️ Sell This Product
                                </a>
                                
                                <button onclick="shareProduct()" class="btn btn-outline">
                                    🔗 Share Product
                                </button>
                                
                                <a href="delete.php?id=<?php echo $product_id; ?>" 
                                   class="btn btn-outline" 
                                   style="color: var(--error); border-color: var(--error);"
                                   onclick="return confirm('⚠️ Are you sure you want to delete this product? This action cannot be undone.')">
                                    🗑️ Delete Product
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Print-friendly function
        function printProductDetails() {
            window.print();
        }
        
        // Share product link
        function shareProduct() {
            const url = window.location.href;
            const title = "Check out this product: <?php echo htmlspecialchars($product['product_name']); ?>";
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            }
        }
        
        // Stock level alert
        const stockPercent = <?php echo $stock_percent; ?>;
        if (stockPercent < 15) {
            // Show warning if stock is very low
            setTimeout(() => {
                alert('⚠️ Warning: Stock is very low! Consider reordering.');
            }, 1000);
        }
        
        // Fix for responsive tables on mobile
        function initResponsiveTables() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                if (table.offsetWidth > table.parentElement.offsetWidth) {
                    table.parentElement.classList.add('has-scroll');
                }
            });
        }
        
        // Initialize on load and resize
        window.addEventListener('load', initResponsiveTables);
        window.addEventListener('resize', initResponsiveTables);
        
        // Auto-refresh stock info every 30 seconds
        setInterval(() => {
            // In a real app, you would fetch updated stock via AJAX
            console.log('Auto-refresh stock info...');
        }, 30000);
    </script>

    <style>
        @media print {
            .page-actions, .btn, .badge {
                display: none !important;
            }
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .dashboard-row {
                display: block !important;
            }
            .col-8, .col-4 {
                width: 100% !important;
                margin-bottom: 20px !important;
            }
            .product-info-grid {
                display: block !important;
            }
            .price-stats-grid {
                display: block !important;
            }
        }
        
        /* Additional responsive fixes */
        .has-scroll {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Ensure all content is visible */
        .main-content > * {
            overflow: visible !important;
        }
    </style>
</body>
</html>
