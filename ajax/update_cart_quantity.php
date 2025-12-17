<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (isset($_SESSION['cart']['items'])) {
        foreach ($_SESSION['cart']['items'] as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        
        // Update total count
        $_SESSION['cart']['count'] = array_sum(array_column($_SESSION['cart']['items'], 'quantity'));
    }
    
    echo json_encode(['success' => true]);
}
?>