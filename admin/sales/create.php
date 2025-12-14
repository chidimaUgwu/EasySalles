<?php
// admin/sales/create.php
$page_title = "Create New Sale";
require_once '../includes/header.php';

// Generate transaction code
$transaction_code = "TXN-" . date('Ymd') . "-" . strtoupper(substr(md5(uniqid()), 0, 6));

// Get all active products
$products = [];
try {
    $stmt = $pdo->query("SELECT product_id, product_name, product_code, unit_price, current_stock, unit_type 
                         FROM EASYSALLES_PRODUCTS 
                         WHERE status = 'active' AND current_stock > 0
                         ORDER BY product_name");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get active staff
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, full_name FROM EASYSALLES_USERS 
                         WHERE role = 2 AND status = 'active'
                         ORDER BY username");
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue if table doesn't exist
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? 'Walk-in Customer';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $staff_id = $_POST['staff_id'] ?? $_SESSION['user_id'];
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_status = $_POST['payment_status'] ?? 'paid';
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    // Get cart items from POST
    $cart_items = [];
    if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
        foreach ($_POST['product_id'] as $index => $product_id) {
            if (!empty($product_id) && isset($_POST['quantity'][$index])) {
                $quantity = intval($_POST['quantity'][$index]);
                if ($quantity > 0) {
                    $cart_items[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity
                    ];
                }
            }
        }
    }
    
    // Validation
    if (empty($cart_items)) {
        $error = "Please add at least one product to the sale.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Calculate totals
            $subtotal = 0;
            $item_details = [];
            
            foreach ($cart_items as $item) {
                // Get product details
                $stmt = $pdo->prepare("SELECT product_name, unit_price, current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    throw new Exception("Product not found");
                }
                
                if ($product['current_stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$product['product_name']}. Available: {$product['current_stock']}");
                }
                
                $item_total = $product['unit_price'] * $item['quantity'];
                $subtotal += $item_total;
                
                $item_details[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'total' => $item_total
                ];
            }
            
            $final_amount = $subtotal - $discount_amount + $tax_amount;
            
            // Insert sale record
            $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SALES 
                (transaction_code, customer_name, customer_phone, customer_email,
                 total_amount, discount_amount, tax_amount, final_amount,
                 payment_method, payment_status, staff_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $transaction_code,
                $customer_name,
                $customer_phone,
                $customer_email,
                $subtotal,
                $discount_amount,
                $tax_amount,
                $final_amount,
                $payment_method,
                $payment_status,
                $staff_id,
                $notes
            ]);
            
            $sale_id = $pdo->lastInsertId();
            
            // Insert sale items and update stock
            foreach ($cart_items as $item) {
                // Get product price
                $stmt = $pdo->prepare("SELECT unit_price FROM EASYSALLES_PRODUCTS WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $product_price = $stmt->fetch()['unit_price'];
                
                // Insert sale item
                $stmt = $pdo->prepare("INSERT INTO EASYSALLES_SALE_ITEMS 
                    (sale_id, product_id, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $sale_id,
                    $item['product_id'],
                    $item['quantity'],
                    $product_price,
                    $product_price * $item['quantity']
                ]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE EASYSALLES_PRODUCTS 
                    SET current_stock = current_stock - ? 
                    WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
                
                // Log inventory change
                $stmt = $pdo->prepare("INSERT INTO EASYSALLES_INVENTORY_LOG 
                    (product_id, change_type, quantity_change, previous_stock, new_stock,
                     reference_id, reference_type, created_by) 
                    VALUES (?, 'stock_out', ?, 
                    (SELECT current_stock + ? FROM EASYSALLES_PRODUCTS WHERE product_id = ?),
                    (SELECT current_stock FROM EASYSALLES_PRODUCTS WHERE product_id = ?),
                    ?, 'sale', ?)");
                $stmt->execute([
                    $item['product_id'],
                    -$item['quantity'],
                    $item['quantity'],
                    $item['product_id'],
                    $item['product_id'],
                    $sale_id,
                    $_SESSION['user_id']
                ]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Sale completed successfully! Transaction ID: $transaction_code";
            
            // Generate new transaction code for next sale
            $transaction_code = "TXN-" . date('Ymd') . "-" . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Show success with receipt link
            echo '<script>
                showToast("Sale recorded successfully!", "success");
                setTimeout(function() {
                    window.open("receipt.php?id=' . $sale_id . '", "_blank");
                }, 1500);
            </script>';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Create New Sale</h2>
        <p>Process customer purchases and record transactions</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Sales
        </a>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sale Information</h3>
                <span class="badge badge-primary" id="transactionBadge">
                    <i class="fas fa-receipt"></i> <?php echo $transaction_code; ?>
                </span>
            </div>
            <div style="padding: 1.5rem;">
                <?php if ($error): ?>
                    <div class="message error-message" style="background: var(--error-light); color: var(--error); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success-message" style="background: var(--success-light); color: var(--success); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <div style="margin-top: 0.5rem;">
                            <a href="create.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                                <i class="fas fa-plus"></i> New Sale
                            </a>
                            <a href="receipt.php?id=<?php echo isset($sale_id) ? $sale_id : ''; ?>" 
                               target="_blank"
                               class="btn btn-primary" 
                               style="padding: 0.5rem 1rem; margin-left: 0.5rem;">
                                <i class="fas fa-print"></i> Print Receipt
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Customer Information -->
                <div class="card" style="background: var(--bg); margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h4 class="card-title">Customer Information</h4>
                    </div>
                    <div style="padding: 1rem;">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" 
                                           id="customerName" 
                                           class="form-control" 
                                           value="Walk-in Customer"
                                           placeholder="Customer name">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number (Optional)</label>
                                    <input type="tel" 
                                           id="customerPhone" 
                                           class="form-control" 
                                           placeholder="Customer phone">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Selection -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-shopping-cart"></i> Add Products
                        </h4>
                    </div>
                    <div style="padding: 1rem;">
                        <div id="productSearchContainer" style="margin-bottom: 1rem;">
                            <input type="text" 
                                   id="productSearch" 
                                   class="form-control" 
                                   placeholder="Search products by name or code..."
                                   onkeyup="searchProducts()">
                        </div>
                        
                        <!-- Product Grid -->
                        <div id="productGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card" 
                                     data-id="<?php echo $product['product_id']; ?>"
                                     data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     data-price="<?php echo $product['unit_price']; ?>"
                                     data-stock="<?php echo $product['current_stock']; ?>"
                                     onclick="addToCart(this)">
                                    <div style="background: var(--primary-light); padding: 1rem; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s;">
                                        <div style="font-size: 0.9rem; color: var(--text); margin-bottom: 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </div>
                                        <div style="color: var(--success); font-weight: 600;">
                                            $<?php echo number_format($product['unit_price'], 2); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.3rem;">
                                            Stock: <?php echo $product['current_stock']; ?> <?php echo htmlspecialchars($product['unit_type']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Shopping Cart -->
                        <div id="cartContainer">
                            <h5 style="margin-bottom: 1rem;">
                                <i class="fas fa-shopping-basket"></i> Shopping Cart
                                <span id="cartCount" class="badge badge-primary">0 items</span>
                            </h5>
                            
                            <div id="cartItems" style="background: var(--bg); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; max-height: 300px; overflow-y: auto;">
                                <div id="emptyCartMessage" style="text-align: center; padding: 2rem; color: var(--text-light);">
                                    <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <p>No products added yet. Click on products above to add them.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="card" style="background: var(--bg);">
                    <div class="card-header">
                        <h4 class="card-title">Payment Information</h4>
                    </div>
                    <div style="padding: 1rem;">
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Payment Method</label>
                                    <select id="paymentMethod" class="form-control">
                                        <option value="cash">Cash</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Payment Status</label>
                                    <select id="paymentStatus" class="form-control">
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Staff Member</label>
                                    <select id="staffId" class="form-control">
                                        <?php foreach ($staff_members as $staff): ?>
                                            <option value="<?php echo $staff['user_id']; ?>" 
                                                <?php echo $staff['user_id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($staff['full_name'] ?: $staff['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <!-- Order Summary -->
        <div class="card" style="position: sticky; top: 100px;">
            <div class="card-header">
                <h3 class="card-title">Order Summary</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div id="orderSummary" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">$0.00</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                        <span>Discount:</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="number" 
                                   id="discountInput" 
                                   style="width: 80px; padding: 0.3rem; border: 1px solid var(--border); border-radius: 5px;"
                                   min="0" 
                                   step="0.01"
                                   onchange="updateTotals()">
                            <span>$</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                        <span>Tax:</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="number" 
                                   id="taxInput" 
                                   style="width: 80px; padding: 0.3rem; border: 1px solid var(--border); border-radius: 5px;"
                                   min="0" 
                                   step="0.01"
                                   onchange="updateTotals()">
                            <span>$</span>
                        </div>
                    </div>
                    
                    <hr style="margin: 1rem 0;">
                    
                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 600;">
                        <span>Total:</span>
                        <span id="totalAmount" style="color: var(--success);">$0.00</span>
                    </div>
                </div>
                
                <!-- Notes -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea id="notesInput" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Add any notes about this sale..."></textarea>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <button type="button" 
                            class="btn btn-primary" 
                            style="padding: 1rem;"
                            onclick="completeSale()"
                            id="completeSaleBtn">
                        <i class="fas fa-check-circle"></i> Complete Sale
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline" 
                            onclick="clearCart()">
                        <i class="fas fa-trash"></i> Clear Cart
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline" 
                            onclick="quickSale()">
                        <i class="fas fa-bolt"></i> Quick Sale
                    </button>
                </div>
                
                <!-- Receipt Preview -->
                <div id="receiptPreview" style="margin-top: 2rem; display: none;">
                    <h5 style="color: var(--primary); margin-bottom: 1rem;">
                        <i class="fas fa-receipt"></i> Receipt Preview
                    </h5>
                    <div style="background: white; border: 2px dashed var(--border); border-radius: 10px; padding: 1rem; font-family: monospace;">
                        <div style="text-align: center; margin-bottom: 1rem;">
                            <strong>EASYSALLES STORE</strong><br>
                            <small>Transaction: <?php echo $transaction_code; ?></small>
                        </div>
                        <div id="receiptItems"></div>
                        <hr style="margin: 0.5rem 0;">
                        <div id="receiptTotals"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form for Submission -->
<form method="POST" action="" id="saleForm" style="display: none;">
    <input type="hidden" name="customer_name" id="formCustomerName">
    <input type="hidden" name="customer_phone" id="formCustomerPhone">
    <input type="hidden" name="customer_email" id="formCustomerEmail">
    <input type="hidden" name="staff_id" id="formStaffId">
    <input type="hidden" name="payment_method" id="formPaymentMethod">
    <input type="hidden" name="payment_status" id="formPaymentStatus">
    <input type="hidden" name="discount_amount" id="formDiscountAmount">
    <input type="hidden" name="tax_amount" id="formTaxAmount">
    <input type="hidden" name="notes" id="formNotes">
    <div id="cartFormFields"></div>
</form>

<script>
    // Shopping cart data
    let cart = [];
    let cartTotal = 0;
    
    // Search products
    function searchProducts() {
        const searchTerm = document.getElementById('productSearch').value.toLowerCase();
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach(card => {
            const productName = card.getAttribute('data-name').toLowerCase();
            const productId = card.getAttribute('data-id').toLowerCase();
            
            if (productName.includes(searchTerm) || productId.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Add product to cart
    function addToCart(element) {
        const productId = element.getAttribute('data-id');
        const productName = element.getAttribute('data-name');
        const productPrice = parseFloat(element.getAttribute('data-price'));
        const productStock = parseInt(element.getAttribute('data-stock'));
        
        // Check if already in cart
        const existingItem = cart.find(item => item.id == productId);
        
        if (existingItem) {
            if (existingItem.quantity < productStock) {
                existingItem.quantity += 1;
                existingItem.total = existingItem.quantity * productPrice;
            } else {
                showToast('Cannot add more items. Stock limit reached.', 'error');
                return;
            }
        } else {
            if (productStock > 0) {
                cart.push({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    quantity: 1,
                    total: productPrice,
                    stock: productStock
                });
            } else {
                showToast('Product out of stock', 'error');
                return;
            }
        }
        
        updateCartDisplay();
        updateTotals();
    }
    
    // Update cart display
    function updateCartDisplay() {
        const cartItemsContainer = document.getElementById('cartItems');
        const emptyCartMessage = document.getElementById('emptyCartMessage');
        const cartCount = document.getElementById('cartCount');
        const cartFormFields = document.getElementById('cartFormFields');
        
        if (cart.length === 0) {
            emptyCartMessage.style.display = 'block';
            cartItemsContainer.innerHTML = '';
            cartCount.textContent = '0 items';
            document.getElementById('receiptPreview').style.display = 'none';
            return;
        }
        
        emptyCartMessage.style.display = 'none';
        cartCount.textContent = cart.length + ' item' + (cart.length > 1 ? 's' : '');
        
        // Build cart items HTML
        let cartHTML = '';
        cartFormFields.innerHTML = '';
        
        cart.forEach((item, index) => {
            // Add to display
            cartHTML += `
                <div style="background: white; padding: 0.8rem; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 0.9rem;">${item.name}</strong><br>
                            <small class="text-muted">$${item.price.toFixed(2)} each</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <button type="button" 
                                        onclick="updateQuantity(${index}, -1)"
                                        style="width: 25px; height: 25px; border-radius: 50%; background: var(--error-light); color: var(--error); border: none; cursor: pointer;">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span style="font-weight: 600; min-width: 30px; text-align: center;">${item.quantity}</span>
                                <button type="button" 
                                        onclick="updateQuantity(${index}, 1)"
                                        ${item.quantity >= item.stock ? 'disabled' : ''}
                                        style="width: 25px; height: 25px; border-radius: 50%; background: var(--success-light); color: var(--success); border: none; cursor: pointer;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <span style="color: var(--success); font-weight: 600;">$${item.total.toFixed(2)}</span>
                            <button type="button" 
                                    onclick="removeFromCart(${index})"
                                    style="background: none; border: none; color: var(--error); cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add hidden form fields
            cartFormFields.innerHTML += `
                <input type="hidden" name="product_id[]" value="${item.id}">
                <input type="hidden" name="quantity[]" value="${item.quantity}">
            `;
        });
        
        cartItemsContainer.innerHTML = cartHTML;
        
        // Update receipt preview
        updateReceiptPreview();
    }
    
    // Update item quantity
    function updateQuantity(index, change) {
        if (cart[index]) {
            const newQuantity = cart[index].quantity + change;
            
            if (newQuantity < 1) {
                removeFromCart(index);
            } else if (newQuantity > cart[index].stock) {
                showToast('Cannot exceed available stock', 'error');
            } else {
                cart[index].quantity = newQuantity;
                cart[index].total = newQuantity * cart[index].price;
                updateCartDisplay();
                updateTotals();
            }
        }
    }
    
    // Remove item from cart
    function removeFromCart(index) {
        cart.splice(index, 1);
        updateCartDisplay();
        updateTotals();
    }
    
    // Update totals
    function updateTotals() {
        // Calculate subtotal
        cartTotal = cart.reduce((sum, item) => sum + item.total, 0);
        
        // Get discount and tax
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const tax = parseFloat(document.getElementById('taxInput').value) || 0;
        
        // Calculate total
        const total = cartTotal - discount + tax;
        
        // Update display
        document.getElementById('subtotalAmount').textContent = '$' + cartTotal.toFixed(2);
        document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);
        
        // Update form fields
        document.getElementById('formDiscountAmount').value = discount;
        document.getElementById('formTaxAmount').value = tax;
    }
    
    // Update receipt preview
    function updateReceiptPreview() {
        if (cart.length === 0) {
            document.getElementById('receiptPreview').style.display = 'none';
            return;
        }
        
        document.getElementById('receiptPreview').style.display = 'block';
        
        let receiptItemsHTML = '';
        cart.forEach(item => {
            receiptItemsHTML += `
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.3rem;">
                    <span>${item.name} x${item.quantity}</span>
                    <span>$${item.total.toFixed(2)}</span>
                </div>
            `;
        });
        
        document.getElementById('receiptItems').innerHTML = receiptItemsHTML;
        
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const tax = parseFloat(document.getElementById('taxInput').value) || 0;
        const total = cartTotal - discount + tax;
        
        document.getElementById('receiptTotals').innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                <span>Subtotal:</span>
                <span>$${cartTotal.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                <span>Discount:</span>
                <span>$${discount.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                <span>Tax:</span>
                <span>$${tax.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: bold;">
                <span>Total:</span>
                <span>$${total.toFixed(2)}</span>
            </div>
        `;
    }
    
    // Clear cart
    function clearCart() {
        if (cart.length > 0 && confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            updateCartDisplay();
            updateTotals();
            showToast('Cart cleared', 'info');
        }
    }
    
    // Quick sale (preset values)
    function quickSale() {
        document.getElementById('customerName').value = 'Walk-in Customer';
        document.getElementById('customerPhone').value = '';
        document.getElementById('paymentMethod').value = 'cash';
        document.getElementById('paymentStatus').value = 'paid';
        document.getElementById('discountInput').value = '0';
        document.getElementById('taxInput').value = '0';
        updateTotals();
        showToast('Quick sale mode activated', 'info');
    }
    
    // Complete sale
    function completeSale() {
        if (cart.length === 0) {
            showToast('Please add products to the cart', 'error');
            return;
        }
        
        if (cartTotal <= 0) {
            showToast('Total amount must be greater than 0', 'error');
            return;
        }
        
        // Validate stock availability
        for (const item of cart) {
            if (item.quantity > item.stock) {
                showToast(`Insufficient stock for ${item.name}. Available: ${item.stock}`, 'error');
                return;
            }
        }
        
        // Set form values
        document.getElementById('formCustomerName').value = document.getElementById('customerName').value;
        document.getElementById('formCustomerPhone').value = document.getElementById('customerPhone').value;
        document.getElementById('formStaffId').value = document.getElementById('staffId').value;
        document.getElementById('formPaymentMethod').value = document.getElementById('paymentMethod').value;
        document.getElementById('formPaymentStatus').value = document.getElementById('paymentStatus').value;
        document.getElementById('formNotes').value = document.getElementById('notesInput').value;
        
        // Show confirmation
        if (confirm('Complete this sale for $' + document.getElementById('totalAmount').textContent.replace('$', '') + '?')) {
            // Disable button and show loading
            const btn = document.getElementById('completeSaleBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
            
            // Submit form
            document.getElementById('saleForm').submit();
        }
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Set today's date in transaction badge
        const today = new Date();
        document.getElementById('transactionBadge').innerHTML = 
            `<i class="fas fa-receipt"></i> TXN-${today.getFullYear()}${(today.getMonth()+1).toString().padStart(2,'0')}${today.getDate().toString().padStart(2,'0')}-${Math.random().toString(36).substr(2, 6).toUpperCase()}`;
        
        // Initialize form fields
        document.getElementById('formDiscountAmount').value = '0';
        document.getElementById('formTaxAmount').value = '0';
        
        // Update totals on input
        document.getElementById('discountInput').addEventListener('input', updateTotals);
        document.getElementById('taxInput').addEventListener('input', updateTotals);
        
        // Focus on product search
        document.getElementById('productSearch').focus();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to complete sale
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                completeSale();
            }
            
            // Escape to clear cart
            if (e.key === 'Escape') {
                clearCart();
            }
            
            // F2 for quick sale
            if (e.key === 'F2') {
                e.preventDefault();
                quickSale();
            }
        });
    });
    
    // Real-time stock check
    function checkStockAvailability() {
        cart.forEach(item => {
            fetch(`check_stock.php?product_id=${item.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.available_stock < item.quantity) {
                        showToast(`Stock updated for ${item.name}. Available: ${data.available_stock}`, 'warning');
                        item.stock = data.available_stock;
                        updateCartDisplay();
                    }
                });
        });
    }
    
    // Check stock every 30 seconds
    setInterval(checkStockAvailability, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>
