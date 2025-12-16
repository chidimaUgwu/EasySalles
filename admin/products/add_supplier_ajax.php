<?php
// admin/products/add_supplier_ajax.php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$supplier_name = trim($_POST['supplier_name'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if (empty($supplier_name)) {
    echo json_encode(['success' => false, 'error' => 'Supplier name is required']);
    exit;
}

try {
    // Check if supplier already exists
    $stmt = $pdo->prepare("SELECT supplier_id FROM EASYSALLES_SUPPLIERS WHERE supplier_name = ?");
    $stmt->execute([$supplier_name]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Supplier already exists']);
        exit;
    }
    
    // Insert new supplier
    $stmt = $pdo->prepare("
        INSERT INTO EASYSALLES_SUPPLIERS 
        (supplier_name, contact_person, phone, email, status, created_at) 
        VALUES (?, ?, ?, ?, 'active', NOW())
    ");
    
    $result = $stmt->execute([
        $supplier_name,
        $contact_person,
        $phone,
        $email
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add supplier']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
