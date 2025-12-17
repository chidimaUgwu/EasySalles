<?php
session_start();

header('Content-Type: application/json');

$count = 0;
if (isset($_SESSION['cart']['count'])) {
    $count = $_SESSION['cart']['count'];
}

echo json_encode(['success' => true, 'count' => $count]);
?>