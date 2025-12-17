<?php
// sale-details.php - DEBUG VERSION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/auth.php';
require_login();
require_staff();

$page_title = 'Sale Details';
include 'includes/header.php';
require 'config/db.php';

// Get sale ID from URL
$sale_id = $_GET['id'] ?? 0;
$sale_id = (int)$sale_id;

if (!$sale_id) {
    header('Location: sales-list.php');
    exit();
}

// Get sale details
$sql = "SELECT s.*, u.full_name as staff_name, u.username as staff_username
        FROM EASYSALLES_SALES s
        LEFT JOIN EASYSALLES_USERS u ON s.staff_id = u.user_id
        WHERE s.sale_id = ?";
        
// Add security check for staff
if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
    $sql .= " AND s.staff_id = ?";
    $params = [$sale_id, $_SESSION['user_id']];
} else {
    $params = [$sale_id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sale = $stmt->fetch();

if (!$sale) {
    echo '<div class="alert alert-error">Sale not found or you don\'t have permission to view it.</div>';
    include 'includes/footer.php';
    exit();
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type, p.current_stock
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ?
              ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Get all products for adding new items
$products_sql = "SELECT product_id, product_name, product_code, unit_price, current_stock, unit_type 
                 FROM EASYSALLES_PRODUCTS 
                 WHERE status = 'active' 
                 ORDER BY product_name";
$products = $pdo->query($products_sql)->fetchAll();

// DEBUG: Show what's being posted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div class="alert alert-info">';
    echo '<h3>DEBUG: POST Data Received</h3>';
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
    echo '</div>';
    
    // Try a SIMPLE test first
    $test_sql = "UPDATE EASYSALLES_SALES SET customer_name = ? WHERE sale_id = ?";
    $test_stmt = $pdo->prepare($test_sql);
    $new_name = $_POST['customer_name'] ?? $sale['customer_name'] . ' UPDATED';
    $test_result = $test_stmt->execute([$new_name, $sale_id]);
    
    if ($test_result) {
        $success = "Simple update worked! Customer name updated to: " . htmlspecialchars($new_name);
        // Refresh sale data
        $stmt->execute($params);
        $sale = $stmt->fetch();
    } else {
        $error = "Simple update failed! Check database connection.";
    }
}
?>

<style>
/* SIMPLIFIED STYLES */
.sale-details-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: #7C3AED;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: #7C3AED;
    color: white;
}

.btn-primary:hover {
    background: #6D28D9;
}

.btn-secondary {
    background: #F1F5F9;
    color: #475569;
    border: 2px solid #CBD5E1;
}

.btn-secondary:hover {
    border-color: #7C3AED;
    color: #7C3AED;
}

.detail-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #E2E8F0;
    margin-bottom: 1.5rem;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1E293B;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #475569;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #CBD5E1;
    border-radius: 8px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #7C3AED;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}

.alert-success {
    background: #D1FAE5;
    color: #065F46;
    border-left-color: #10B981;
}

.alert-error {
    background: #FEE2E2;
    color: #991B1B;
    border-left-color: #EF4444;
}

.alert-info {
    background: #DBEAFE;
    color: #1E40AF;
    border-left-color: #3B82F6;
}

.items-table-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #E2E8F0;
    margin-bottom: 1.5rem;
    overflow-x: auto;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    text-align: left;
    padding: 1rem;
    font-weight: 600;
    color: #1E293B;
    border-bottom: 2px solid #E2E8F0;
}

.items-table td {
    padding: 1rem;
    border-bottom: 1px solid #E2E8F0;
}

.quantity-input, .price-input {
    width: 100px;
    padding: 0.5rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    text-align: center;
}

.product-select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
}

.edit-form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #E2E8F0;
}

.cancel-btn {
    background: #FEE2E2;
    color: #DC2626;
    border: 2px solid #DC2626;
}

.cancel-btn:hover {
    background: #DC2626;
    color: white;
}
</style>

<div class="sale-details-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-receipt"></i> Sale Details
        </h1>
        
        <div class="header-actions">
            <a href="sales-list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- SIMPLE TEST FORM -->
    <form method="POST" action="" id="testForm">
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-user"></i>
                <span>Customer Information</span>
            </div>
            
            <div class="form-group">
                <label class="form-label">Customer Name *</label>
                <input type="text" name="customer_name" class="form-control" 
                       value="<?php echo htmlspecialchars($sale['customer_name']); ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="customer_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($sale['customer_phone']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="customer_email" class="form-control" 
                           value="<?php echo htmlspecialchars($sale['customer_email']); ?>">
                </div>
            </div>
        </div>
        
        <!-- EXISTING ITEMS - Simple View Only -->
        <div class="items-table-container">
            <div class="card-title">
                <i class="fas fa-box"></i>
                <span>Current Items (View Only)</span>
            </div>
            
            <?php if (empty($items)): ?>
                <p>No items found</p>
            <?php else: ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Form Actions -->
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary" name="save_simple">
                <i class="fas fa-save"></i> TEST SAVE
            </button>
            
            <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn cancel-btn">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Simple JavaScript to ensure form submits
document.getElementById('testForm').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to save?')) {
        e.preventDefault();
        return false;
    }
    return true;
});
</script>

<?php include 'includes/footer.php'; ?>