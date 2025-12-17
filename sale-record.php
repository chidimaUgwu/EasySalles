<?php
// sale-record.php (FIXED VERSION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();
require_staff();

$page_title = 'Record New Sale';
include 'includes/header.php';
require 'config/db.php';

// Initialize cart from session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [
        'items' => [],
        'count' => 0
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? 'Walk-in Customer';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = $_POST['notes'] ?? '';
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    
    // Get cart items from form
    $cart_items_json = $_POST['cart_items'] ?? '[]';
    $cart_items = json_decode($cart_items_json, true);
    
    // Calculate totals
    $subtotal = 0;
    
    if (!empty($cart_items) && is_array($cart_items)) {
        foreach ($cart_items as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }
    }
    
    // Calculate tax and total
    $tax_rate = 0.01; // 1% tax as per example
    $tax_amount = round($subtotal * $tax_rate, 2);
    $total_amount = round($subtotal - $discount_amount + $tax_amount, 2);
    
    // Generate transaction code
    $transaction_code = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert sale record
        $sql = "INSERT INTO EASYSALLES_SALES 
                (transaction_code, customer_name, customer_phone, customer_email, 
                 subtotal_amount, discount_amount, tax_amount, total_amount, 
                 payment_method, staff_id, notes, sale_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $transaction_code, 
            $customer_name, 
            $customer_phone, 
            $customer_email,
            $subtotal,
            $discount_amount,
            $tax_amount,
            $total_amount,
            $payment_method,
            $_SESSION['user_id'],
            $notes
        ]);
        
        $sale_id = $pdo->lastInsertId();
        
        // Insert sale items and update inventory
        if (!empty($cart_items) && is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $unit_price = $item['unit_price'];
                $subtotal_item = $quantity * $unit_price;
                
                // Insert sale item
                $item_sql = "INSERT INTO EASYSALLES_SALE_ITEMS 
                             (sale_id, product_id, quantity, unit_price, subtotal)
                             VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $pdo->prepare($item_sql);
                $item_stmt->execute([$sale_id, $product_id, $quantity, $unit_price, $subtotal_item]);
                
                // Update product stock
                $update_sql = "UPDATE EASYSALLES_PRODUCTS 
                              SET current_stock = current_stock - ?
                              WHERE product_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$quantity, $product_id]);
                
                // Get previous stock for log
                $stock_sql = "SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?";
                $stock_stmt = $pdo->prepare($stock_sql);
                $stock_stmt->execute([$product_id]);
                $current_stock = $stock_stmt->fetchColumn();
                $new_stock = $current_stock - $quantity;
                
                // Add to inventory log
                $log_sql = "INSERT INTO EASYSALLES_INVENTORY_LOG 
                           (product_id, change_type, quantity_change, previous_stock, 
                            new_stock, reference_id, reference_type, created_by, notes)
                           VALUES (?, 'stock_out', ?, ?, ?, ?, 'sale', ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    $product_id, 
                    $quantity, 
                    $current_stock, 
                    $new_stock,
                    $sale_id,
                    $_SESSION['user_id'],
                    "Sold $quantity units in sale #$sale_id"
                ]);
            }
        }
        
        $pdo->commit();
        
        $success = "Sale recorded successfully! Transaction Code: " . $transaction_code;
        $show_receipt = true;
        $receipt_data = [
            'transaction_code' => $transaction_code,
            'customer_name' => $customer_name,
            'items' => $cart_items,
            'subtotal' => $subtotal,
            'discount' => $discount_amount,
            'tax' => $tax_amount,
            'total' => $total_amount,
            'payment_method' => $payment_method,
            'notes' => $notes
        ];
        
        // Clear cart after successful sale
        $_SESSION['cart'] = ['items' => [], 'count' => 0];
        
        // Clear localStorage if exists
        echo '<script>localStorage.removeItem("sale_cart");</script>';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to record sale: " . $e->getMessage();
    }
}

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Get available products with categories
$sql = "SELECT p.*, c.category_name, c.color 
        FROM EASYSALLES_PRODUCTS p
        LEFT JOIN EASYSALLES_CATEGORIES c ON p.category = c.category_name
        WHERE p.status = 'active' AND p.current_stock > 0";
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
$categories = $pdo->query("SELECT * FROM EASYSALLES_CATEGORIES ORDER BY category_name")->fetchAll();

// Get category counts
$category_counts = [];
foreach ($categories as $cat) {
    $count_sql = "SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE category = ? AND status = 'active' AND current_stock > 0";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$cat['category_name']]);
    $category_counts[$cat['category_name']] = $count_stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record New Sale</title>
    <style>
        /* Copy ALL the CSS styles from your original file here */
        /* I'm omitting them for brevity but make sure they're included */
        .sale-record-container {
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
        
        /* ... include all other CSS styles ... */
    </style>
</head>
<body>
    <div class="sale-record-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i> Record New Sale
            </h1>
            <a href="products.php" class="filter-btn" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="sale-form-container">
            <!-- Left Column: Products & Customer Info -->
            <div>
                <!-- Products Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-box"></i>
                        <span>Select Products</span>
                    </div>
                    
                    <!-- Category Tabs -->
                    <div class="category-tabs">
                        <a href="sale-record.php" class="category-tab <?php echo !$category ? 'active' : ''; ?>">
                            <i class="fas fa-layer-group"></i>
                            <span>All Products</span>
                            <span class="category-count">
                                <?php 
                                $all_count_sql = "SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE status = 'active' AND current_stock > 0";
                                echo $pdo->query($all_count_sql)->fetchColumn();
                                ?>
                            </span>
                        </a>
                        
                        <?php foreach ($categories as $cat): ?>
                            <a href="sale-record.php?category=<?php echo urlencode($cat['category_name']); ?>" 
                               class="category-tab <?php echo $category === $cat['category_name'] ? 'active' : ''; ?>"
                               style="color: <?php echo $cat['color']; ?>; border-color: <?php echo $cat['color']; ?>20;">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($cat['category_name']); ?></span>
                                <span class="category-count"><?php echo $category_counts[$cat['category_name']] ?? 0; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Search Box -->
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="productSearch" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search products by name, code, or description...">
                    </div>
                    
                    <!-- Products Grid -->
                    <div class="products-grid" id="productsGrid">
                        <?php if (empty($products)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h3>No Products Available</h3>
                                <p>No products in stock or match your search criteria.</p>
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
                                <div class="product-card" 
                                     data-id="<?php echo $product['product_id']; ?>"
                                     data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     data-price="<?php echo $product['unit_price']; ?>"
                                     data-stock="<?php echo $product['current_stock']; ?>"
                                     data-code="<?php echo htmlspecialchars($product['product_code']); ?>"
                                     data-category="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                                    
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
                                                <i class="fas fa-circle"></i> Stock: <?php echo $product['current_stock']; ?>
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
                                    </div>
                                    
                                    <!-- Product Details -->
                                    <div class="product-details">
                                        <div class="detail-grid">
                                            <div class="detail-item">
                                                <div class="detail-label">Current Stock</div>
                                                <div class="detail-value">
                                                    <?php echo $product['current_stock']; ?>
                                                </div>
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
                                    
                                    <!-- Price & Add to Cart -->
                                    <div class="product-price-section">
                                        <div class="price-label">Price per <?php echo $product['unit_type']; ?></div>
                                        <div class="price-value">$<?php echo number_format($product['unit_price'], 2); ?></div>
                                        
                                        <button class="add-to-cart-btn" onclick="addToCartFromProductCard(<?php echo $product['product_id']; ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="form-section" style="margin-top: 1.5rem;">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        <span>Customer Information</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Customer Name</label>
                        <input type="text" id="customerName" class="form-control" 
                               value="Walk-in Customer" placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" id="customerPhone" class="form-control" 
                               placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" id="customerEmail" class="form-control" 
                               placeholder="Enter email address">
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="form-section" style="margin-top: 1.5rem;">
                    <div class="section-title">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment Method</span>
                    </div>
                    
                    <div class="payment-methods">
                        <label class="payment-method active" data-method="cash">
                            <input type="radio" name="payment_method" value="cash" checked hidden>
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Cash</span>
                        </label>
                        
                        <label class="payment-method" data-method="card">
                            <input type="radio" name="payment_method" value="card" hidden>
                            <i class="fas fa-credit-card"></i>
                            <span>Card</span>
                        </label>
                        
                        <label class="payment-method" data-method="mobile_money">
                            <input type="radio" name="payment_method" value="mobile_money" hidden>
                            <i class="fas fa-mobile-alt"></i>
                            <span>Mobile Money</span>
                        </label>
                        
                        <label class="payment-method" data-method="credit">
                            <input type="radio" name="payment_method" value="credit" hidden>
                            <i class="fas fa-handshake"></i>
                            <span>Credit</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Cart & Order Summary -->
            <div class="cart-container">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Shopping Cart</span>
                    <span id="cartCount" style="margin-left: auto; font-size: 0.8rem; color: #64748b;">(0 items)</span>
                </div>
                
                <div id="cartItems">
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                        <p class="text-muted" style="font-size: 0.8rem;">Select products from the list</p>
                    </div>
                </div>
                
                <div id="cartContent" style="display: none;">
                    <div class="cart-items-container" id="cartItemsList">
                        <!-- Cart items will be inserted here by JavaScript -->
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <div class="summary-title">Order Summary</div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value" id="subtotal">$0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Discount:</span>
                            <div class="discount-input">
                                <input type="number" id="discountAmount" value="0" min="0" step="0.01" 
                                       onchange="updateTotals()" placeholder="0.00">
                                <span>$</span>
                            </div>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Tax:</span>
                            <span class="summary-value" id="tax">$0.00</span>
                        </div>
                        
                        <div class="summary-row total">
                            <span class="summary-label">Total:</span>
                            <span class="summary-value" id="grandTotal">$0.00</span>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea id="saleNotes" class="form-control" rows="2" 
                                  placeholder="Add any notes about this sale..."></textarea>
                    </div>
                    
                    <!-- Hidden Form -->
                    <form method="POST" id="saleForm">
                        <input type="hidden" name="customer_name" id="formCustomerName">
                        <input type="hidden" name="customer_phone" id="formCustomerPhone">
                        <input type="hidden" name="customer_email" id="formCustomerEmail">
                        <input type="hidden" name="payment_method" id="formPaymentMethod" value="cash">
                        <input type="hidden" name="notes" id="formNotes">
                        <input type="hidden" name="discount_amount" id="formDiscount">
                        <input type="hidden" name="cart_items" id="formCartItems">
                    </form>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn-complete" id="completeSaleBtn">
                            <i class="fas fa-check"></i> Complete Sale
                        </button>
                        
                        <button type="button" class="btn-clear" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </div>
                    
                    <!-- Quick Sale Section -->
                    <div class="quick-sale">
                        <div class="section-title" style="font-size: 1rem;">
                            <i class="fas fa-bolt"></i>
                            <span>Quick Sale</span>
                        </div>
                        
                        <div class="quick-sale-input">
                            <input type="text" id="quickProductCode" placeholder="Enter product code or name">
                            <button type="button" class="btn-quick-add" onclick="quickAddToCart()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cart management
        let cart = [];
        let allProducts = [];
        let transactionCode = 'TXN-' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '-' + Math.random().toString(36).substr(2, 6).toUpperCase();
        
        // Load products from PHP
        document.addEventListener('DOMContentLoaded', function() {
            // Get all products from the page
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                allProducts.push({
                    id: parseInt(card.dataset.id),
                    name: card.dataset.name,
                    price: parseFloat(card.dataset.price),
                    stock: parseInt(card.dataset.stock),
                    code: card.dataset.code,
                    category: card.dataset.category
                });
            });
            
            // Initialize cart from localStorage
            const savedCart = localStorage.getItem('sale_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                updateCartDisplay();
            }
            
            // Product search
            document.getElementById('productSearch').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                productCards.forEach(card => {
                    const productName = card.dataset.name.toLowerCase();
                    const productCode = card.dataset.code.toLowerCase();
                    
                    if (productName.includes(searchTerm) || 
                        productCode.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Payment method selection
            document.querySelectorAll('.payment-method').forEach(method => {
                method.addEventListener('click', function() {
                    document.querySelectorAll('.payment-method').forEach(m => {
                        m.classList.remove('active');
                        m.querySelector('input').checked = false;
                    });
                    this.classList.add('active');
                    this.querySelector('input').checked = true;
                    document.getElementById('formPaymentMethod').value = this.dataset.method;
                });
            });
            
            // Update form values
            document.getElementById('customerName').addEventListener('input', updateFormValues);
            document.getElementById('customerPhone').addEventListener('input', updateFormValues);
            document.getElementById('customerEmail').addEventListener('input', updateFormValues);
            document.getElementById('saleNotes').addEventListener('input', updateFormValues);
            document.getElementById('discountAmount').addEventListener('input', updateTotals);
            
            // Complete sale button
            document.getElementById('completeSaleBtn').addEventListener('click', function() {
                if (cart.length === 0) {
                    alert('Please add items to cart before completing sale.');
                    return;
                }
                
                if (!confirm('Are you sure you want to complete this sale?')) {
                    return;
                }
                
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                this.disabled = true;
                
                // Update form values and submit
                updateFormValues();
                document.getElementById('saleForm').submit();
            });
            
            // Quick sale enter key
            document.getElementById('quickProductCode').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    quickAddToCart();
                }
            });
        });
        
        // Add product to cart from product card
        function addToCartFromProductCard(productId) {
            addToCart(productId);
        }
        
        // Add product to cart
        function addToCart(productId, quantity = 1) {
            // Find product in products array
            const product = allProducts.find(p => p.id === productId);
            if (!product) {
                showNotification('error', 'Product not found!');
                return;
            }
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.product_id === productId);
            
            if (existingItem) {
                if (existingItem.quantity < product.stock) {
                    existingItem.quantity += quantity;
                } else {
                    showNotification('error', `Only ${product.stock} items in stock!`);
                    return;
                }
            } else {
                if (product.stock > 0) {
                    cart.push({
                        product_id: productId,
                        name: product.name,
                        code: product.code,
                        unit_price: product.price,
                        quantity: quantity
                    });
                } else {
                    showNotification('error', 'Product out of stock!');
                    return;
                }
            }
            
            // Highlight product card
            const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
            if (productCard) {
                productCard.classList.add('added');
                setTimeout(() => productCard.classList.remove('added'), 1000);
            }
            
            updateCartDisplay();
            saveCartToStorage();
            showNotification('success', 'Added to cart!');
            
            // Save to session via AJAX
            saveCartToSession(productId, existingItem ? existingItem.quantity : quantity);
        }
        
        // Quick add to cart by product code
        function quickAddToCart() {
            const input = document.getElementById('quickProductCode');
            const searchTerm = input.value.trim().toLowerCase();
            
            if (!searchTerm) {
                showNotification('error', 'Please enter a product code or name');
                return;
            }
            
            // Find product by code or name
            const product = allProducts.find(p => 
                p.code.toLowerCase() === searchTerm || 
                p.name.toLowerCase().includes(searchTerm)
            );
            
            if (!product) {
                showNotification('error', 'Product not found!');
                return;
            }
            
            addToCart(product.id);
            input.value = '';
            input.focus();
        }
        
        // Remove item from cart
        function removeFromCart(productId) {
            cart = cart.filter(item => item.product_id !== productId);
            updateCartDisplay();
            saveCartToStorage();
            
            // Remove from session via AJAX
            fetch('ajax/remove_from_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            });
        }
        
        // Update quantity in cart
        function updateQuantity(productId, change) {
            const item = cart.find(item => item.product_id === productId);
            const product = allProducts.find(p => p.id === productId);
            
            if (!item || !product) return;
            
            const newQuantity = item.quantity + change;
            
            if (newQuantity < 1) {
                removeFromCart(productId);
                return;
            }
            
            if (newQuantity > product.stock) {
                showNotification('error', `Only ${product.stock} items in stock!`);
                return;
            }
            
            item.quantity = newQuantity;
            updateCartDisplay();
            saveCartToStorage();
            
            // Update session via AJAX
            fetch('ajax/update_cart_quantity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${newQuantity}`
            });
        }
        
        function updateCartDisplay() {
            const cartItemsList = document.getElementById('cartItemsList');
            const emptyCart = document.getElementById('cartItems');
            const cartContent = document.getElementById('cartContent');
            const completeBtn = document.getElementById('completeSaleBtn');
            const cartCount = document.getElementById('cartCount');
            
            if (cart.length === 0) {
                emptyCart.style.display = 'block';
                cartContent.style.display = 'none';
                completeBtn.disabled = true;
                cartCount.textContent = '(0 items)';
                return;
            }
            
            emptyCart.style.display = 'none';
            cartContent.style.display = 'block';
            completeBtn.disabled = false;
            
            const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
            cartCount.textContent = `(${totalItems} items)`;
            
            let html = '';
            
            cart.forEach(item => {
                const itemTotal = item.unit_price * item.quantity;
                
                html += `
                    <div class="cart-item" data-item-id="${item.product_id}">
                        <div class="cart-item-info">
                            <h4>${item.name}</h4>
                            <div class="cart-item-price">$${item.unit_price.toFixed(2)} each</div>
                        </div>
                        <div class="cart-item-controls">
                            <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, -1)">-</button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, 1)">+</button>
                            <button class="remove-btn" onclick="removeFromCart(${item.product_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            cartItemsList.innerHTML = html;
            updateTotals();
        }
        
        function updateTotals() {
            if (cart.length === 0) {
                document.getElementById('subtotal').textContent = '$0.00';
                document.getElementById('tax').textContent = '$0.00';
                document.getElementById('grandTotal').textContent = '$0.00';
                return;
            }
            
            // Calculate subtotal
            const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
            const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const tax = (subtotal - discount) * 0.01; // 1% tax
            const grandTotal = subtotal - discount + tax;
            
            document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
            document.getElementById('grandTotal').textContent = `$${grandTotal.toFixed(2)}`;
            
            // Update form values
            updateFormValues();
        }
        
        function updateFormValues() {
            document.getElementById('formCustomerName').value = document.getElementById('customerName').value;
            document.getElementById('formCustomerPhone').value = document.getElementById('customerPhone').value;
            document.getElementById('formCustomerEmail').value = document.getElementById('customerEmail').value;
            document.getElementById('formNotes').value = document.getElementById('saleNotes').value;
            document.getElementById('formDiscount').value = parseFloat(document.getElementById('discountAmount').value) || 0;
            document.getElementById('formPaymentMethod').value = document.querySelector('.payment-method.active').dataset.method;
            
            // Update cart items in form
            document.getElementById('formCartItems').value = JSON.stringify(cart);
        }
        
        function saveCartToStorage() {
            localStorage.setItem('sale_cart', JSON.stringify(cart));
        }
        
        function saveCartToSession(productId, quantity) {
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                // Update header cart count if on products page
                if (typeof updateHeaderCartCount === 'function') {
                    updateHeaderCartCount();
                }
            })
            .catch(error => {
                console.error('Error saving cart to session:', error);
            });
        }
        
        // Clear cart
        function clearCart() {
            if (cart.length === 0) return;
            
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                localStorage.removeItem('sale_cart');
                updateCartDisplay();
                
                // Clear session cart via AJAX
                fetch('ajax/clear_cart.php');
            }
        }
        
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
        
        // Image error handling
        document.querySelectorAll('.product-image').forEach(img => {
            img.onerror = function() {
                this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.alt) + '&background=7C3AED&color=fff&size=256';
            };
        });
    </script>
</body>
</html>