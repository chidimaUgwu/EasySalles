<?php
// sale-record.php (UPDATED SECTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();
require_staff();

$page_title = 'Record New Sale';
include 'includes/header.php';
require 'config/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? 'Walk-in Customer';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = $_POST['notes'] ?? '';
    
    // Get cart items from form
    $cart_items_json = $_POST['cart_items'] ?? '[]';
    $cart_items = json_decode($cart_items_json, true);
    
    // Calculate totals
    $total_amount = 0;
    $subtotal = 0;
    
    if (!empty($cart_items) && is_array($cart_items)) {
        foreach ($cart_items as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }
    }
    
    // Calculate tax and total
    $tax_rate = 0.1; // 10% tax
    $tax_amount = $subtotal * $tax_rate;
    $total_amount = $subtotal + $tax_amount;
    
    // Generate transaction code
    $transaction_code = 'ES-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert sale record
        $sql = "INSERT INTO EASYSALLES_SALES 
                (transaction_code, customer_name, customer_phone, customer_email, 
                 total_amount, tax_amount, final_amount, payment_method, staff_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $transaction_code, 
            $customer_name, 
            $customer_phone, 
            $customer_email,
            $total_amount,
            $tax_amount,
            $total_amount, // final_amount = total_amount + tax_amount
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
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to record sale: " . $e->getMessage();
    }
}

// Get available products with categories
$products_sql = "SELECT p.*, c.category_name, c.color 
                FROM EASYSALLES_PRODUCTS p
                LEFT JOIN EASYSALLES_CATEGORIES c ON p.category = c.category_name
                WHERE p.status = 'active' AND p.current_stock > 0 
                ORDER BY p.product_name";
$products = $pdo->query($products_sql)->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM EASYSALLES_CATEGORIES ORDER BY category_name")->fetchAll();
?>

<style>
    .sale-record-container {
        max-width: 1200px;
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
    
    .sale-form-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    @media (max-width: 1024px) {
        .sale-form-container {
            grid-template-columns: 1fr;
        }
    }
    
    .form-section {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }
    
    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title i {
        color: var(--primary);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
    }
    
    .form-control {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .product-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 15px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .product-card:hover {
        border-color: var(--primary);
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.1);
    }
    
    .product-card.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .product-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text);
    }
    
    .product-price {
        color: var(--primary);
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .product-stock {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 0.5rem;
    }
    
    .cart-container {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        position: sticky;
        top: 100px;
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .cart-item:last-child {
        border-bottom: none;
    }
    
    .cart-item-info h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .cart-item-price {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .cart-item-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .quantity-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 2px solid var(--border);
        background: var(--card-bg);
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .quantity-btn:hover {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }
    
    .quantity-display {
        min-width: 40px;
        text-align: center;
        font-weight: 600;
    }
    
    .remove-btn {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }
    
    .remove-btn:hover {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
    
    .cart-total {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--border);
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    
    .grand-total {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid var(--border);
    }
    
    .submit-btn {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 2rem;
    }
    
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(124, 58, 237, 0.3);
    }
    
    .submit-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: #10B981;
        border-left-color: #10B981;
    }
    
    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border-left-color: #EF4444;
    }
    
    .empty-cart {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
    }
    
    .empty-cart i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--border);
    }
    
    .search-box {
        position: relative;
        margin-bottom: 1.5rem;
    }
    
    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
    }
    
    .search-box input {
        padding-left: 3rem;
    }
    
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .payment-method {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .payment-method:hover {
        border-color: var(--primary);
    }
    
    .payment-method.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .payment-method i {
        font-size: 1.25rem;
        color: var(--primary);
    }
</style>

<div class="sale-record-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-cash-register"></i> Record New Sale
        </h1>
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
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
                </div>
                
                <div class="products-grid" id="productsGrid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" 
                             data-id="<?php echo $product['product_id']; ?>"
                             data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                             data-price="<?php echo $product['unit_price']; ?>"
                             data-stock="<?php echo $product['current_stock']; ?>">
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-price">$<?php echo number_format($product['unit_price'], 2); ?></div>
                            <div class="product-stock">Stock: <?php echo $product['current_stock']; ?> <?php echo $product['unit_type']; ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($products)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #64748b;">
                            <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No products available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="form-section" style="margin-top: 2rem;">
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
            <div class="form-section" style="margin-top: 2rem;">
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
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea id="saleNotes" class="form-control" rows="3" 
                              placeholder="Add any notes about this sale..."></textarea>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Cart -->
        <div class="cart-container">
            <div class="section-title">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </div>
            
            <div id="cartItems">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <p class="text-muted" style="font-size: 0.9rem;">Select products from the list</p>
                </div>
            </div>
            
            <div id="cartContent" style="display: none;">
                <div id="cartItemsList"></div>
                
                <div class="cart-total">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                    
                    <div class="total-row">
                        <span>Discount:</span>
                        <span id="discount">$0.00</span>
                    </div>
                    
                    <div class="total-row">
                        <span>Tax:</span>
                        <span id="tax">$0.00</span>
                    </div>
                    
                    <div class="grand-total">
                        <span>Total:</span>
                        <span id="grandTotal">$0.00</span>
                    </div>
                </div>
                
                <form method="POST" id="saleForm">
                    <input type="hidden" name="customer_name" id="formCustomerName">
                    <input type="hidden" name="customer_phone" id="formCustomerPhone">
                    <input type="hidden" name="customer_email" id="formCustomerEmail">
                    <input type="hidden" name="payment_method" id="formPaymentMethod" value="cash">
                    <input type="hidden" name="notes" id="formNotes">
                    <input type="hidden" name="cart_items" id="formCartItems">
                    
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>
                        <i class="fas fa-check"></i> Complete Sale
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = [];
    let products = [];
    
    // Load products from server
    document.addEventListener('DOMContentLoaded', function() {
        // Get products data from PHP
        const productCards = document.querySelectorAll('.product-card');
        productCards.forEach(card => {
            products.push({
                id: card.dataset.id,
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                stock: parseInt(card.dataset.stock)
            });
        });
        
        // Product search
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            productCards.forEach(card => {
                const productName = card.dataset.name.toLowerCase();
                if (productName.includes(searchTerm)) {
                    card.style.display = 'block';
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
    });
    
    // Add product to cart
    document.addEventListener('click', function(e) {
        if (e.target.closest('.product-card')) {
            const card = e.target.closest('.product-card');
            const productId = parseInt(card.dataset.id);
            
            // Find product in products array
            const product = products.find(p => p.id == productId);
            if (!product) return;
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.product_id === productId);
            
            if (existingItem) {
                if (existingItem.quantity < product.stock) {
                    existingItem.quantity++;
                } else {
                    alert(`Only ${product.stock} items in stock!`);
                    return;
                }
            } else {
                if (product.stock > 0) {
                    cart.push({
                        product_id: productId,
                        name: product.name,
                        unit_price: product.price,
                        quantity: 1
                    });
                } else {
                    alert('Product out of stock!');
                    return;
                }
            }
            
            updateCartDisplay();
            card.classList.add('active');
            setTimeout(() => card.classList.remove('active'), 500);
        }
        
        // Remove item from cart
        if (e.target.classList.contains('remove-btn') || e.target.closest('.remove-btn')) {
            const itemId = parseInt(e.target.closest('[data-item-id]').dataset.itemId);
            cart = cart.filter(item => item.product_id !== itemId);
            updateCartDisplay();
        }
        
        // Quantity buttons
        if (e.target.classList.contains('quantity-btn')) {
            const btn = e.target;
            const itemId = parseInt(btn.closest('[data-item-id]').dataset.itemId);
            const item = cart.find(item => item.product_id === itemId);
            const product = products.find(p => p.id == itemId);
            
            if (!item || !product) return;
            
            if (btn.textContent === '+') {
                if (item.quantity < product.stock) {
                    item.quantity++;
                } else {
                    alert(`Only ${product.stock} items in stock!`);
                }
            } else if (btn.textContent === '-') {
                if (item.quantity > 1) {
                    item.quantity--;
                } else {
                    cart = cart.filter(i => i.product_id !== itemId);
                }
            }
            
            updateCartDisplay();
        }
    });
    
    function updateCartDisplay() {
        const cartItemsList = document.getElementById('cartItemsList');
        const emptyCart = document.getElementById('cartItems');
        const cartContent = document.getElementById('cartContent');
        const submitBtn = document.getElementById('submitBtn');
        
        if (cart.length === 0) {
            emptyCart.style.display = 'block';
            cartContent.style.display = 'none';
            submitBtn.disabled = true;
            return;
        }
        
        emptyCart.style.display = 'none';
        cartContent.style.display = 'block';
        submitBtn.disabled = false;
        
        let html = '';
        let subtotal = 0;
        
        cart.forEach(item => {
            const itemTotal = item.unit_price * item.quantity;
            subtotal += itemTotal;
            
            html += `
                <div class="cart-item" data-item-id="${item.product_id}">
                    <div class="cart-item-info">
                        <h4>${item.name}</h4>
                        <div class="cart-item-price">$${item.unit_price.toFixed(2)} each</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="quantity-btn">-</button>
                        <span class="quantity-display">${item.quantity}</span>
                        <button class="quantity-btn">+</button>
                        <button class="remove-btn">Remove</button>
                    </div>
                </div>
            `;
        });
        
        cartItemsList.innerHTML = html;
        
        // Calculate totals
        const discount = 0; // Can be added later
        const tax = subtotal * 0.1; // 10% tax (adjust as needed)
        const grandTotal = subtotal - discount + tax;
        
        document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('discount').textContent = `$${discount.toFixed(2)}`;
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
        
        // Update cart items in form
        document.getElementById('formCartItems').value = JSON.stringify(cart);
    }
    
    // Form submission
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        if (cart.length === 0) {
            e.preventDefault();
            alert('Please add items to cart before completing sale.');
            return;
        }
        
        if (!confirm('Are you sure you want to complete this sale?')) {
            e.preventDefault();
            return;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
    });
</script>

<?php include 'includes/footer.php'; ?>