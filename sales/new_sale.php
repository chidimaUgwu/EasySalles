<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();

// Start/Get cart from session
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $product_id = $_POST['product_id'];
        $quantity = intval($_POST['quantity']);
        
        // Get product details
        $product = Database::query(
            "SELECT id, product_code, name, price, current_stock 
             FROM easysalles_products 
             WHERE id = ? AND is_active = 1",
            [$product_id]
        )->fetch();
        
        if ($product) {
            // Check stock
            if ($product['current_stock'] < $quantity) {
                $_SESSION['error'] = "Insufficient stock! Available: " . $product['current_stock'];
            } else {
                // Add to cart
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product['id'],
                        'code' => $product['product_code'],
                        'name' => $product['name'],
                        'price' => floatval($product['price']),
                        'quantity' => $quantity
                    ];
                }
                $_SESSION['success'] = "Product added to cart!";
            }
        }
    }
    
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            $quantity = intval($quantity);
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
        }
    }
    
    if (isset($_POST['remove_item'])) {
        $product_id = $_POST['product_id'];
        unset($_SESSION['cart'][$product_id]);
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_POST['process_sale'])) {
        if (empty($_SESSION['cart'])) {
            $_SESSION['error'] = "Cart is empty!";
        } else {
            // Generate transaction ID
            $transaction_id = generateTransactionId();
            
            // Calculate totals
            $subtotal = 0;
            $tax_rate = 0.00; // Get from settings
            $discount = 0.00;
            
            foreach ($_SESSION['cart'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $tax_amount = $subtotal * $tax_rate;
            $total_amount = $subtotal + $tax_amount - $discount;
            
            // Start transaction
            try {
                Database::getInstance()->beginTransaction();
                
                // Create sale record
                Database::query(
                    "INSERT INTO easysalles_sales 
                    (transaction_id, user_id, total_amount, discount_amount, tax_amount, 
                     final_amount, payment_method, sale_date, sale_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())",
                    [$transaction_id, $_SESSION['user_id'], $subtotal, $discount, 
                     $tax_amount, $total_amount, 'cash']
                );
                
                $sale_id = Database::getInstance()->lastInsertId();
                
                // Add sale items and update stock
                foreach ($_SESSION['cart'] as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    
                    // Add sale item
                    Database::query(
                        "INSERT INTO easysalles_sale_items 
                        (sale_id, product_id, quantity, unit_price, subtotal) 
                        VALUES (?, ?, ?, ?, ?)",
                        [$sale_id, $item['id'], $item['quantity'], $item['price'], $item_total]
                    );
                    
                    // Update product stock
                    Database::query(
                        "UPDATE easysalles_products 
                         SET current_stock = current_stock - ? 
                         WHERE id = ?",
                        [$item['quantity'], $item['id']]
                    );
                    
                    // Log stock history
                    Database::query(
                        "INSERT INTO easysalles_stock_history 
                        (product_id, previous_stock, new_stock, change_type, reference_id, notes, changed_by) 
                        VALUES (?, (SELECT current_stock + ? FROM easysalles_products WHERE id = ?), 
                                (SELECT current_stock FROM easysalles_products WHERE id = ?), 
                                'sale', ?, 'Sale: $transaction_id', ?)",
                        [$item['id'], $item['quantity'], $item['id'], $item['id'], $sale_id, $_SESSION['user_id']]
                    );
                }
                
                // Increment customer count (using default walk-in customer)
                Database::query(
                    "UPDATE easysalles_customers 
                     SET visit_count = visit_count + 1, 
                         last_visit = CURDATE() 
                     WHERE customer_code = 'WALK-IN-000'"
                );
                
                // Commit transaction
                Database::getInstance()->commit();
                
                // Store receipt data and clear cart
                $_SESSION['receipt'] = [
                    'transaction_id' => $transaction_id,
                    'sale_id' => $sale_id,
                    'total' => $total_amount,
                    'items' => $_SESSION['cart'],
                    'date' => date('Y-m-d H:i:s')
                ];
                
                $_SESSION['cart'] = [];
                $_SESSION['success'] = "Sale completed successfully! Transaction ID: $transaction_id";
                
                // Redirect to receipt
                header('Location: receipt.php?id=' . $sale_id);
                exit();
                
            } catch (Exception $e) {
                Database::getInstance()->rollBack();
                $_SESSION['error'] = "Error processing sale: " . $e->getMessage();
            }
        }
    }
}

// Get active products for quick add
$products = Database::query(
    "SELECT p.*, c.name as category_name 
     FROM easysalles_products p 
     LEFT JOIN easysalles_categories c ON p.category_id = c.id 
     WHERE p.is_active = 1 AND p.current_stock > 0 
     ORDER BY p.name 
     LIMIT 100"
)->fetchAll();

// Calculate cart totals
$cart_total = 0;
$cart_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_items += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sales-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 1.5rem;
            height: calc(100vh - 70px);
        }
        
        .products-section {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .cart-section {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        .section-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .product-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }
        
        .product-image {
            width: 64px;
            height: 64px;
            margin: 0 auto 0.75rem;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .product-price {
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .product-stock {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        
        .stock-badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stock-good { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stock-low { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .cart-item-price {
            font-size: 0.875rem;
            color: var(--text-light);
        }
        
        .cart-item-total {
            font-weight: 600;
            color: var(--primary);
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg);
            border-radius: var(--radius-sm);
            padding: 0.25rem;
        }
        
        .quantity-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: var(--surface);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
        }
        
        .quantity-btn:hover {
            background: var(--border);
        }
        
        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 600;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        
        .totals-table td {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .totals-table tr:last-child td {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .payment-btn {
            padding: 0.75rem;
            border: 2px solid var(--border);
            background: var(--surface);
            border-radius: var(--radius-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .payment-btn:hover {
            border-color: var(--primary);
            background: rgba(124, 58, 237, 0.05);
        }
        
        .payment-btn.active {
            border-color: var(--primary);
            background: rgba(124, 58, 237, 0.1);
        }
        
        .quick-search {
            position: sticky;
            top: 0;
            background: var(--surface);
            padding-bottom: 1rem;
            z-index: 10;
        }
        
        .categories-filter {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .category-chip {
            padding: 0.5rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.875rem;
            white-space: nowrap;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .category-chip:hover {
            background: var(--surface);
            border-color: var(--primary);
        }
        
        .category-chip.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
    </style>
</head>
<body class="dashboard-layout">
    <!-- Include Sidebar -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="../dashboard.php" class="btn btn-secondary">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                    Back
                </a>
                <h1 style="margin: 0;">New Sale</h1>
            </div>
            <div class="user-menu">
                <?php include '../includes/user_menu.php'; ?>
            </div>
        </header>
        
        <div class="content-area" style="padding: 0;">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" style="margin: 1rem;">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error" style="margin: 1rem;">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="sales-container">
                <!-- Left: Products -->
                <div class="products-section">
                    <div class="section-header">
                        <h2>Products</h2>
                        <span><?php echo count($products); ?> products available</span>
                    </div>
                    
                    <div class="section-body">
                        <!-- Quick Search -->
                        <div class="quick-search">
                            <input type="text" id="productSearch" placeholder="Search products by name or barcode..." 
                                   style="width: 100%; margin-bottom: 1rem;">
                            
                            <!-- Categories Filter -->
                            <?php 
                            $categories = Database::query(
                                "SELECT DISTINCT c.id, c.name 
                                 FROM easysalles_categories c 
                                 JOIN easysalles_products p ON c.id = p.category_id 
                                 WHERE p.is_active = 1 AND p.current_stock > 0 
                                 ORDER BY c.name"
                            )->fetchAll();
                            ?>
                            <div class="categories-filter">
                                <div class="category-chip active" data-category="all">All</div>
                                <?php foreach ($categories as $cat): ?>
                                    <div class="category-chip" data-category="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Products Grid -->
                        <div class="products-grid" id="productsGrid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card" 
                                     data-id="<?php echo $product['id']; ?>"
                                     data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                     data-price="<?php echo $product['price']; ?>"
                                     data-stock="<?php echo $product['current_stock']; ?>"
                                     data-category="<?php echo $product['category_id'] ?? 0; ?>">
                                    <div class="product-image">
                                        <?php echo getInitials($product['name']); ?>
                                    </div>
                                    <div class="product-name">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </div>
                                    <div class="product-price">
                                        <?php echo formatCurrency($product['price']); ?>
                                    </div>
                                    <div class="product-stock">
                                        <span class="stock-badge <?php echo $product['current_stock'] <= 10 ? 'stock-low' : 'stock-good'; ?>">
                                            <?php echo $product['current_stock']; ?> in stock
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($products)): ?>
                            <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                                <svg style="width: 64px; height: 64px; fill: var(--text-lighter); margin-bottom: 1rem;" viewBox="0 0 24 24">
                                    <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                                </svg>
                                <h3>No products available</h3>
                                <p>Add products in the product management section</p>
                                <a href="../admin/products.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    Go to Products
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right: Cart -->
                <div class="cart-section">
                    <div class="section-header">
                        <h2>Cart</h2>
                        <span><?php echo $cart_items; ?> items</span>
                    </div>
                    
                    <form method="POST" id="cartForm">
                        <div class="section-body">
                            <?php if (empty($_SESSION['cart'])): ?>
                                <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                                    <svg style="width: 64px; height: 64px; fill: var(--text-lighter); margin-bottom: 1rem;" viewBox="0 0 24 24">
                                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                                    </svg>
                                    <h3>Cart is empty</h3>
                                    <p>Add products from the left panel</p>
                                </div>
                            <?php else: ?>
                                <div id="cartItems">
                                    <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                        <div class="cart-item" id="cart-item-<?php echo $product_id; ?>">
                                            <div class="cart-item-image">
                                                <?php echo getInitials($item['name']); ?>
                                            </div>
                                            <div class="cart-item-details">
                                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="cart-item-price">
                                                    <?php echo formatCurrency($item['price']); ?> each
                                                </div>
                                            </div>
                                            <div class="quantity-control">
                                                <button type="button" class="quantity-btn" 
                                                        onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                <input type="number" name="quantity[<?php echo $product_id; ?>]" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       class="quantity-input" min="1" 
                                                       onchange="updateCart()">
                                                <button type="button" class="quantity-btn" 
                                                        onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                            </div>
                                            <div class="cart-item-total">
                                                <?php echo formatCurrency($item['price'] * $item['quantity']); ?>
                                            </div>
                                            <button type="submit" name="remove_item" 
                                                    value="<?php echo $product_id; ?>" 
                                                    class="btn btn-danger btn-sm">
                                                <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="section-footer">
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <table class="totals-table">
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td style="text-align: right;"><?php echo formatCurrency($cart_total); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tax (0%):</td>
                                        <td style="text-align: right;">$0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Discount:</td>
                                        <td style="text-align: right;">$0.00</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total:</strong></td>
                                        <td style="text-align: right; color: var(--primary);">
                                            <strong><?php echo formatCurrency($cart_total); ?></strong>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div class="payment-methods">
                                    <div class="payment-btn active" data-method="cash">
                                        <div>💵 Cash</div>
                                        <small>Pay with cash</small>
                                    </div>
                                    <div class="payment-btn" data-method="card">
                                        <div>💳 Card</div>
                                        <small>Credit/Debit card</small>
                                    </div>
                                    <div class="payment-btn" data-method="mobile_money">
                                        <div>📱 Mobile</div>
                                        <small>Mobile money</small>
                                    </div>
                                    <div class="payment-btn" data-method="bank_transfer">
                                        <div>🏦 Transfer</div>
                                        <small>Bank transfer</small>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_method" id="paymentMethod" value="cash">
                                
                                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                    <button type="submit" name="clear_cart" class="btn btn-danger" style="flex: 1;">
                                        Clear Cart
                                    </button>
                                    <button type="submit" name="update_cart" class="btn btn-secondary" style="flex: 1;">
                                        Update Cart
                                    </button>
                                    <button type="submit" name="process_sale" class="btn btn-primary" style="flex: 2;">
                                        <svg class="icon" viewBox="0 0 24 24"><path d="M5 13H16.17l-3.59 3.59L14 18l6-6-6-6-1.41 1.41L16.17 11H5v2z"/></svg>
                                        Process Sale
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Product Quick View Modal -->
    <div class="modal" id="quickViewModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="modalProductName"></h3>
                <button class="modal-close" onclick="closeModal('quickViewModal')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <form method="POST" id="quickViewForm">
                <input type="hidden" name="product_id" id="modalProductId">
                <div class="modal-body">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div class="product-image" style="width: 80px; height: 80px; margin: 0 auto 1rem;" id="modalProductImage"></div>
                        <div class="product-price" style="font-size: 1.5rem;" id="modalProductPrice"></div>
                        <div class="product-stock" id="modalProductStock"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <button type="button" class="btn btn-secondary" onclick="adjustModalQuantity(-1)">-</button>
                            <input type="number" name="quantity" id="modalQuantity" value="1" min="1" 
                                   style="flex: 1; text-align: center;" onchange="validateModalQuantity()">
                            <button type="button" class="btn btn-secondary" onclick="adjustModalQuantity(1)">+</button>
                        </div>
                    </div>
                    
                    <div style="background: var(--bg); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
                        <strong>Subtotal: <span id="modalSubtotal">$0.00</span></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('quickViewModal')">Cancel</button>
                    <button type="submit" name="add_to_cart" class="btn btn-primary">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M11 9h2V6h3V4h-3V1h-2v3H8v2h3v3zm-4 9c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-9.83-3.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.86-7.01L19.42 4h-.01l-1.1 2-2.76 5H8.53l-.13-.27L6.16 6l-.95-2-.94-2H1v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.13 0-.25-.11-.25-.25z"/></svg>
                        Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // Cart management
    let currentModalProduct = null;
    
    // Product click handler
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            const productPrice = parseFloat(this.dataset.price);
            const productStock = parseInt(this.dataset.stock);
            
            openQuickView(productId, productName, productPrice, productStock);
        });
    });
    
    function openQuickView(productId, productName, productPrice, productStock) {
        currentModalProduct = { id: productId, price: productPrice, stock: productStock };
        
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalProductPrice').textContent = formatCurrency(productPrice);
        
        // Set product image initials
        const initials = productName.split(' ').map(w => w.charAt(0)).join('').substring(0, 2).toUpperCase();
        document.getElementById('modalProductImage').textContent = initials;
        
        // Set stock info
        let stockClass = 'stock-good';
        let stockText = productStock + ' in stock';
        if (productStock <= 0) {
            stockClass = 'stock-out_of_stock';
            stockText = 'Out of stock';
        } else if (productStock <= 10) {
            stockClass = 'stock-low';
            stockText = 'Low stock: ' + productStock;
        }
        document.getElementById('modalProductStock').innerHTML = 
            `<span class="stock-badge ${stockClass}">${stockText}</span>`;
        
        // Reset quantity
        document.getElementById('modalQuantity').value = 1;
        document.getElementById('modalQuantity').max = productStock;
        updateModalSubtotal();
        
        openModal('quickViewModal');
    }
    
    function adjustModalQuantity(change) {
        const input = document.getElementById('modalQuantity');
        let newValue = parseInt(input.value) + change;
        
        if (newValue < 1) newValue = 1;
        if (newValue > currentModalProduct.stock) newValue = currentModalProduct.stock;
        
        input.value = newValue;
        updateModalSubtotal();
    }
    
    function validateModalQuantity() {
        const input = document.getElementById('modalQuantity');
        let value = parseInt(input.value);
        
        if (isNaN(value) || value < 1) value = 1;
        if (value > currentModalProduct.stock) value = currentModalProduct.stock;
        
        input.value = value;
        updateModalSubtotal();
    }
    
    function updateModalSubtotal() {
        const quantity = parseInt(document.getElementById('modalQuantity').value) || 0;
        const subtotal = currentModalProduct.price * quantity;
        document.getElementById('modalSubtotal').textContent = formatCurrency(subtotal);
    }
    
    // Cart functions
    function updateQuantity(productId, change) {
        const input = document.querySelector(`input[name="quantity[${productId}]"]`);
        let newValue = parseInt(input.value) + change;
        
        if (newValue < 1) newValue = 1;
        
        input.value = newValue;
        updateCart();
    }
    
    function updateCart() {
        // This would update cart via AJAX in real implementation
        // For now, just submit the form
        document.getElementById('cartForm').submit();
    }
    
    // Payment method selection
    document.querySelectorAll('.payment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('paymentMethod').value = this.dataset.method;
        });
    });
    
    // Product search and filter
    const productSearch = document.getElementById('productSearch');
    const productsGrid = document.getElementById('productsGrid');
    const categoryChips = document.querySelectorAll('.category-chip');
    
    if (productSearch) {
        productSearch.addEventListener('input', function() {
            filterProducts();
        });
    }
    
    categoryChips.forEach(chip => {
        chip.addEventListener('click', function() {
            categoryChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            filterProducts();
        });
    });
    
    function filterProducts() {
        const searchTerm = productSearch.value.toLowerCase();
        const selectedCategory = document.querySelector('.category-chip.active').dataset.category;
        
        document.querySelectorAll('.product-card').forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const category = card.dataset.category;
            
            const matchesSearch = name.includes(searchTerm);
            const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
            
            card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
        });
    }
    
    // Helper function to format currency
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape to close modal
        if (e.key === 'Escape') {
            closeModal('quickViewModal');
        }
        
        // Ctrl+F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            productSearch.focus();
        }
        
        // F2 to process sale
        if (e.key === 'F2') {
            e.preventDefault();
            document.querySelector('button[name="process_sale"]').click();
        }
    });
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Focus search on page load
        productSearch.focus();
        
        // Add enter key support for quick view
        document.getElementById('modalQuantity').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('#quickViewForm button[type="submit"]').click();
            }
        });
    });
    </script>
</body>
</html>
