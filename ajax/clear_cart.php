<?php
session_start();

header('Content-Type: application/json');

$_SESSION['cart'] = ['items' => [], 'count' => 0];
echo json_encode(['success' => true]);
?>