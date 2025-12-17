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
        // Insert sale record - CORRECTED VERSION
        $sql = "INSERT INTO EASYSALLES_SALES 
                (transaction_code, customer_name, customer_phone, customer_email, 
                 subtotal_amount, discount_amount, tax_amount, total_amount, final_amount,
                 payment_method, payment_status, staff_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $transaction_code, 
            $customer_name, 
            $customer_phone, 
            $customer_email,
            $subtotal, // subtotal_amount
            $discount_amount,
            $tax_amount,
            $subtotal, // total_amount = subtotal (before tax)
            $total_amount, // final_amount = total after tax
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
        
        // Clear cart after successful sale
        $_SESSION['cart'] = [];
        unset($_SESSION['sale_cart']);
        
        // Set success message - cleaned up version
        $success_message = "Sale completed successfully!<br><strong>Transaction:</strong> $transaction_code<br><strong>Total:</strong> $" . number_format($total_amount, 2);
        
        // Redirect to sales page after 3 seconds
        header("refresh:3;url=sales.php");
        
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
    
    /* Alert Styles - Cleaner */
    .alert {
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.08));
        color: #065f46;
        border: 1px solid #10B981;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
    }
    
    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.08));
        color: #7f1d1d;
        border: 1px solid #EF4444;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
    }
    
    .alert i {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .alert-content {
        flex-grow: 1;
    }
    
    .alert-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }
    
    .alert-message {
        font-size: 0.95rem;
        opacity: 0.9;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .btn-complete:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }
    
    .btn-complete:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
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
</style>

<div class="sale-record-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-cash-register"></i> Record New Sale
        </h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div class="alert-content">
                <div class="alert-title">Sale Completed Successfully!</div>
                <div class="alert-message"><?php echo $success_message; ?><br><small>Redirecting to sales page in 3 seconds...</small></div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div class="alert-content">
                <div class="alert-title">Error</div>
                <div class="alert-message"><?php echo $error; ?></div>
            </div>
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
                                 onclick="addToCart(<?php echo $product['product_id']; ?>)">
                                
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
                                    
                                    <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $product['product_id']; ?>);">
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
                
                <!-- Receipt Preview -->
                <div class="receipt-preview" id="receiptPreview" style="display: none;">
                    <div class="receipt-header">
                        <div class="receipt-title">EASYSALLES STORE</div>
                        <div class="receipt-transaction" id="receiptTransaction">Transaction: TXN-20251217-0DCE9E</div>
                    </div>
                    <div class="receipt-items" id="receiptItems">
                        <!-- Items will be added here -->
                    </div>
                    <div class="receipt-totals" id="receiptTotals">
                        <!-- Totals will be added here -->
                    </div>
                    <div class="receipt-footer">
                        Thank you for your purchase!
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn-complete" id="completeSaleBtn">
                        <i class="fas fa-check"></i> Complete Sale
                    </button>
                    
                    <button type="button" class="btn-clear" onclick="clearCart()">
                        <i class="fas fa-trash"></i> Clear Cart
                    </button>
                </div>
                
                <!-- Print Receipt Button -->
                <button type="button" class="print-receipt-btn" id="printReceiptBtn" style="display: none;">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                
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
                
                <!-- Hidden Form -->
                <form method="POST" id="saleForm" style="display: none;">
                    <input type="hidden" name="customer_name" id="formCustomerName">
                    <input type="hidden" name="customer_phone" id="formCustomerPhone">
                    <input type="hidden" name="customer_email" id="formCustomerEmail">
                    <input type="hidden" name="payment_method" id="formPaymentMethod" value="cash">
                    <input type="hidden" name="notes" id="formNotes">
                    <input type="hidden" name="discount_amount" id="formDiscount">
                    <input type="hidden" name="cart_items" id="formCartItems">
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
                id: parseInt(card.dataset.id),
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                stock: parseInt(card.dataset.stock),
                code: card.dataset.code
            });
        });
        
        // Product search
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            productCards.forEach(card => {
                const productName = card.dataset.name.toLowerCase();
                const productCode = card.dataset.code.toLowerCase();
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
        
        // Load cart from localStorage if exists
        const savedCart = localStorage.getItem('sale_cart');
        if (savedCart) {
            cart = JSON.parse(savedCart);
            updateCartDisplay();
        }
        
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
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            this.disabled = true;
            
            // Update form values and submit
            updateFormValues();
            document.getElementById('saleForm').submit();
            
            // Reset button state after 3 seconds (in case form doesn't submit)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
        
        // Print receipt button
        document.getElementById('printReceiptBtn').addEventListener('click', function() {
            printReceipt();
        });
        
        // Quick sale enter key
        document.getElementById('quickProductCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                quickAddToCart();
            }
        });
    });
    
    // Add product to cart
    window.addToCart = function(productId) {
        // Find product in products array
        const product = products.find(p => p.id === productId);
        if (!product) {
            alert('Product not found!');
            return;
        }
        
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
                    code: product.code,
                    unit_price: product.price,
                    quantity: 1
                });
            } else {
                alert('Product out of stock!');
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
        updateReceiptPreview();
    };
    
    // Quick add to cart by product code
    function quickAddToCart() {
        const input = document.getElementById('quickProductCode');
        const searchTerm = input.value.trim().toLowerCase();
        
        if (!searchTerm) {
            alert('Please enter a product code or name');
            return;
        }
        
        // Find product by code or name
        const product = products.find(p => 
            p.code.toLowerCase() === searchTerm || 
            p.name.toLowerCase().includes(searchTerm)
        );
        
        if (!product) {
            alert('Product not found!');
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
        updateReceiptPreview();
    }
    
    // Update quantity in cart
    function updateQuantity(productId, change) {
        const item = cart.find(item => item.product_id === productId);
        const product = products.find(p => p.id === productId);
        
        if (!item || !product) return;
        
        const newQuantity = item.quantity + change;
        
        if (newQuantity < 1) {
            removeFromCart(productId);
            return;
        }
        
        if (newQuantity > product.stock) {
            alert(`Only ${product.stock} items in stock!`);
            return;
        }
        
        item.quantity = newQuantity;
        updateCartDisplay();
        saveCartToStorage();
        updateReceiptPreview();
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
        
        // Update cart items in form
        document.getElementById('formCartItems').value = JSON.stringify(cart);
    }
    
    function updateReceiptPreview() {
        if (cart.length === 0) {
            document.getElementById('receiptPreview').style.display = 'none';
            document.getElementById('printReceiptBtn').style.display = 'none';
            return;
        }
        
        document.getElementById('receiptPreview').style.display = 'block';
        document.getElementById('printReceiptBtn').style.display = 'block';
        
        // Generate transaction code for receipt preview
        const now = new Date();
        const dateStr = now.toISOString().slice(0,10).replace(/-/g, '');
        const randomCode = Math.random().toString(36).substr(2, 6).toUpperCase();
        const transactionCode = 'TXN-' + dateStr + '-' + randomCode;
        
        // Update transaction code
        document.getElementById('receiptTransaction').textContent = `Transaction: ${transactionCode}`;
        
        // Update receipt items
        const receiptItems = document.getElementById('receiptItems');
        let itemsHtml = '';
        
        cart.forEach(item => {
            const itemTotal = item.unit_price * item.quantity;
            itemsHtml += `
                <div class="receipt-item">
                    <span>${item.name} x${item.quantity}</span>
                    <span>$${itemTotal.toFixed(2)}</span>
                </div>
            `;
        });
        
        receiptItems.innerHTML = itemsHtml;
        
        // Update receipt totals
        const receiptTotals = document.getElementById('receiptTotals');
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        const tax = (subtotal - discount) * 0.01;
        const grandTotal = subtotal - discount + tax;
        
        receiptTotals.innerHTML = `
            <div class="receipt-item">
                <span>Subtotal:</span>
                <span>$${subtotal.toFixed(2)}</span>
            </div>
            <div class="receipt-item">
                <span>Discount:</span>
                <span>$${discount.toFixed(2)}</span>
            </div>
            <div class="receipt-item">
                <span>Tax:</span>
                <span>$${tax.toFixed(2)}</span>
            </div>
            <div class="receipt-item" style="font-weight: bold; border-top: 1px solid #000;">
                <span>Total:</span>
                <span>$${grandTotal.toFixed(2)}</span>
            </div>
        `;
    }
    
    function saveCartToStorage() {
        localStorage.setItem('sale_cart', JSON.stringify(cart));
    }
    
    // Clear cart
    window.clearCart = function() {
        if (cart.length === 0) return;
        
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            localStorage.removeItem('sale_cart');
            updateCartDisplay();
            updateReceiptPreview();
        }
    };
    
    // Print receipt
    function printReceipt() {
        const receiptContent = document.getElementById('receiptPreview').innerHTML;
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Receipt</title>
                <style>
                    body { font-family: 'Courier New', monospace; font-size: 12px; padding: 20px; }
                    .receipt { width: 300px; margin: 0 auto; }
                    .receipt-header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                    .receipt-title { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
                    .receipt-transaction { font-size: 11px; color: #666; }
                    .receipt-item { display: flex; justify-content: space-between; margin-bottom: 5px; }
                    .receipt-totals { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; }
                    .receipt-footer { text-align: center; margin-top: 15px; font-size: 10px; color: #666; }
                </style>
            </head>
            <body>
                <div class="receipt">${receiptContent}</div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 1000);
                    };
                <\/script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    }
    
    // Image error handling
    document.querySelectorAll('.product-image').forEach(img => {
        img.onerror = function() {
            this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.alt) + '&background=7C3AED&color=fff&size=256';
        };
    });
</script>

<?php include 'includes/footer.php'; ?>