<?php
// sale-record.php (INTEGRATED WITH PRODUCTS DESIGN)
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
    $_SESSION['cart'] = [];
}

$cart_items = $_SESSION['cart'];

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
        $_SESSION['cart'] = [];
        
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

<style>
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
        padding: 1.5rem;
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
    
    /* Product Grid Styles - Compact Version */
    .category-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
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
        color: var(--text);
        background: var(--bg);
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
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
        padding-right: 0.5rem;
    }
    
    .products-grid::-webkit-scrollbar {
        width: 6px;
    }
    
    .products-grid::-webkit-scrollbar-track {
        background: var(--border);
        border-radius: 3px;
    }
    
    .products-grid::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 3px;
    }
    
    .product-card {
        background: var(--card-bg);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        cursor: pointer;
        height: 280px; /* Fixed height for compact design */
    }
    
    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15);
        border-color: var(--primary);
    }
    
    .product-card.added {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .product-image-container {
        height: 100px;
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
        font-size: 2rem;
    }
    
    .product-header {
        padding: 0.75rem 0.75rem 0.5rem;
    }
    
    .product-code {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        color: var(--text);
        font-size: 0.7rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(124, 58, 237, 0.05));
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        display: inline-block;
        margin-bottom: 0.3rem;
    }
    
    .product-name {
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.3rem;
        color: var(--text);
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.5em;
    }
    
    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.6rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        width: fit-content;
    }
    
    .product-details {
        padding: 0 0.75rem;
        flex-grow: 1;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .detail-item {
        text-align: center;
        padding: 0.4rem;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .detail-label {
        font-size: 0.65rem;
        color: #64748b;
        margin-bottom: 0.1rem;
    }
    
    .detail-value {
        font-weight: 600;
        color: var(--text);
        font-size: 0.8rem;
    }
    
    .stock-indicator {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        margin: 0.5rem 0;
        overflow: hidden;
    }
    
    .stock-level {
        height: 100%;
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    
    .stock-low { background: #EF4444; }
    .stock-medium { background: #F59E0B; }
    .stock-high { background: #10B981; }
    
    .stock-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.65rem;
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .product-price-section {
        padding: 0.75rem;
        border-top: 1px solid var(--border);
        text-align: center;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01));
    }
    
    .price-label {
        font-size: 0.7rem;
        color: #64748b;
        margin-bottom: 0.2rem;
    }
    
    .price-value {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .add-to-cart-btn {
        width: 100%;
        padding: 0.5rem;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }
    
    .add-to-cart-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
    }
    
    .add-to-cart-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
        grid-column: 1 / -1;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--border);
    }
    
    /* Cart Styles */
    .cart-container {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        position: sticky;
        top: 100px;
        max-height: calc(100vh - 150px);
        overflow-y: auto;
    }
    
    .cart-items-container {
        max-height: 200px;
        overflow-y: auto;
        padding-right: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .cart-items-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .cart-items-container::-webkit-scrollbar-track {
        background: var(--border);
        border-radius: 3px;
    }
    
    .cart-items-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 3px;
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border);
    }
    
    .cart-item:last-child {
        border-bottom: none;
    }
    
    .cart-item-info h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
    
    .cart-item-price {
        color: #64748b;
        font-size: 0.8rem;
    }
    
    .cart-item-controls {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    
    .quantity-btn {
        width: 24px;
        height: 24px;
        border-radius: 5px;
        border: 1px solid var(--border);
        background: var(--card-bg);
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.8rem;
    }
    
    .quantity-btn:hover {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }
    
    .quantity-display {
        min-width: 30px;
        text-align: center;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .remove-btn {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border: none;
        padding: 0.3rem 0.6rem;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }
    
    .remove-btn:hover {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
    
    /* Order Summary Styles */
    .order-summary {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(236, 72, 153, 0.01));
        border-radius: 12px;
        padding: 1rem;
        margin: 1rem 0;
        border: 1px solid var(--border);
    }
    
    .summary-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text);
        text-align: center;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        padding: 0.3rem 0;
        border-bottom: 1px dashed var(--border);
    }
    
    .summary-row.total {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
        border-bottom: 2px solid var(--primary);
        margin-top: 0.5rem;
        padding-top: 0.5rem;
    }
    
    .summary-label {
        color: #64748b;
    }
    
    .summary-value {
        font-weight: 600;
        color: var(--text);
    }
    
    .discount-input {
        display: flex;
        gap: 0.5rem;
        margin: 0.5rem 0;
    }
    
    .discount-input input {
        flex: 1;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        text-align: right;
    }
    
    /* Receipt Preview Styles */
    .receipt-preview {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        margin: 1rem 0;
        border: 2px solid var(--border);
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .receipt-header {
        text-align: center;
        margin-bottom: 1rem;
        border-bottom: 1px dashed #000;
        padding-bottom: 0.5rem;
    }
    
    .receipt-title {
        font-weight: bold;
        font-size: 1.2rem;
        margin-bottom: 0.25rem;
    }
    
    .receipt-transaction {
        font-size: 0.9rem;
        color: #666;
    }
    
    .receipt-items {
        margin-bottom: 1rem;
    }
    
    .receipt-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    
    .receipt-totals {
        border-top: 1px dashed #000;
        padding-top: 0.5rem;
    }
    
    .receipt-footer {
        text-align: center;
        margin-top: 1rem;
        font-size: 0.8rem;
        color: #666;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .btn-complete {
        background: linear-gradient(135deg, #10B981, #059669);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-complete:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }
    
    .btn-clear {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: #EF4444;
        border: 2px solid #EF4444;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-clear:hover {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
    
    /* Quick Sale Section */
    .quick-sale {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--border);
    }
    
    .quick-sale-input {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .quick-sale-input input {
        flex: 1;
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: 8px;
    }
    
    .btn-quick-add {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-quick-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text);
        font-size: 0.9rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }
    
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .payment-method {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    
    .payment-method:hover {
        border-color: var(--primary);
    }
    
    .payment-method.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.02));
    }
    
    .payment-method i {
        font-size: 1rem;
        color: var(--primary);
    }
    
    .empty-cart {
        text-align: center;
        padding: 1rem;
        color: #64748b;
    }
    
    .empty-cart i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: var(--border);
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
    
    .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .product-status {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
    }
    
    /* Print Receipt Button */
    .print-receipt-btn {
        width: 100%;
        padding: 0.75rem;
        background: linear-gradient(135deg, #3B82F6, #1D4ED8);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .print-receipt-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }
    
    /* Notification styles */
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
</style>

