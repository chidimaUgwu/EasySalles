<?php
// admin/products/suppliers.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Suppliers";
require_once '../includes/header.php';

// Get suppliers
$suppliers = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            COUNT(p.product_id) as product_count,
            SUM(p.current_stock) as total_stock
        FROM EASYSALLES_SUPPLIERS s
        LEFT JOIN EASYSALLES_PRODUCTS p ON s.supplier_name = p.supplier
        GROUP BY s.supplier_id
        ORDER BY s.supplier_name
    ");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get supplier statistics
$stats = [
    'total_suppliers' => count($suppliers),
    'active_suppliers' => 0,
    'total_products' => 0
];

foreach ($suppliers as $supplier) {
    if ($supplier['status'] == 'active') $stats['active_suppliers']++;
    $stats['total_products'] += $supplier['product_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EasySalles</title>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Suppliers</h2>
                <p>Manage product suppliers and vendor information</p>
            </div>
            <div class="page-actions">
                <a href="index.php" class="btn btn-outline">
                    ← Back to Products
                </a>
                <a href="add_supplier.php" class="btn btn-primary">
                    ➕ Add Supplier
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="row" style="margin-bottom: 1.5rem;">
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--primary); margin: 0;"><?php echo $stats['total_suppliers']; ?></h1>
                        <small class="text-muted">Total Suppliers</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--success); margin: 0;"><?php echo $stats['active_suppliers']; ?></h1>
                        <small class="text-muted">Active Suppliers</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--warning); margin: 0;"><?php echo $stats['total_products']; ?></h1>
                        <small class="text-muted">Supplied Products</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($suppliers)): ?>
            <div class="card">
                <div style="text-align: center; padding: 4rem;">
                    <span style="font-size: 4rem; color: var(--text-light); opacity: 0.5;">🚚</span>
                    <h3 style="margin: 1rem 0;">No Suppliers Found</h3>
                    <p class="text-muted">Start by adding your first supplier</p>
                    <a href="add_supplier.php" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Add Your First Supplier
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Suppliers Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Suppliers</h3>
                </div>
                <div style="padding: 1.5rem;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Supplier Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($supplier['contact_person']): ?>
                                        <small><?php echo htmlspecialchars($supplier['contact_person']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No contact
