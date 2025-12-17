<?php
// ajax/clear_cart.php
session_start();
$_SESSION['cart'] = [];
echo json_encode(['success' => true]);
?>