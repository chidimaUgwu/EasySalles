<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    
    if (isset($_SESSION['cart']['items'])) {
        // Remove item from cart
        $_SESSION['cart']['items'] = array_filter($_SESSION['cart']['items'], function($item) use ($product_id) {
            return $item['product_id'] != $product_id;
        });
        
        // Reset array keys
        $_SESSION['cart']['items'] = array_values($_SESSION['cart']['items']);
        
        // Update total count
        $_SESSION['cart']['count'] = array_sum(array_column($_SESSION['cart']['items'], 'quantity'));
    }
    
    echo json_encode(['success' => true, 'count' => $_SESSION['cart']['count'] ?? 0]);
}
?>