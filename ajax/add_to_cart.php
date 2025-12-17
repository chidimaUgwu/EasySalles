<?php
// ajax/add_to_cart.php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    // Validate product
    $stmt = $pdo->prepare("SELECT * FROM EASYSALLES_PRODUCTS WHERE product_id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Check stock
    if ($product['current_stock'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
        exit;
    }
    
    // Add to cart or update quantity
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'product_id' => $product_id,
            'name' => $product['product_name'],
            'price' => $product['unit_price'],
            'quantity' => $quantity,
            'code' => $product['product_code'],
            'unit_type' => $product['unit_type'],
            'stock' => $product['current_stock']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Added to cart',
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}