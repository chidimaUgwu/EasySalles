<?php
session_start();

header('Content-Type: application/json');

$_SESSION['cart'] = [];
echo json_encode(['success' => true]);
?>