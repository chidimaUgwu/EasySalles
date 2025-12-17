<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = ['items' => [], 'count' => 0];
    }
    
    // Validate product exists and has stock
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
    
    // Initialize cart items array if not exists
    if (!isset($_SESSION['cart']['items'])) {
        $_SESSION['cart']['items'] = [];
    }
    
    // Check if product already in cart
    $found = false;
    foreach ($_SESSION['cart']['items'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    // If not found, add new item
    if (!$found) {
        $_SESSION['cart']['items'][] = [
            'product_id' => $product_id,
            'name' => $product['product_name'],
            'price' => $product['unit_price'],
            'quantity' => $quantity,
            'code' => $product['product_code']
        ];
    }
    
    // Update total count
    $_SESSION['cart']['count'] = array_sum(array_column($_SESSION['cart']['items'], 'quantity'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Added to cart successfully',
        'count' => $_SESSION['cart']['count']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>