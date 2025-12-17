<?php
// ajax/get_cart_count.php
session_start();

header('Content-Type: application/json');

$count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
}

echo json_encode(['success' => true, 'count' => $count]);
?>