<?php
// sale-details.php - WORKING VERSION WITH ITEM EDITING
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
    echo '<div class="alert alert-error">Sale not found or no permission.</div>';
    include 'includes/footer.php';
    exit();
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.product_code, p.unit_type, p.current_stock
              FROM EASYSALLES_SALE_ITEMS si
              JOIN EASYSALLES_PRODUCTS p ON si.product_id = p.product_id
              WHERE si.sale_id = ? ORDER BY si.item_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

// Get products for adding
$products_sql = "SELECT product_id, product_name, product_code, unit_price, current_stock 
                 FROM EASYSALLES_PRODUCTS WHERE status = 'active' ORDER BY product_name";
$products = $pdo->query($products_sql)->fetchAll();

// Handle form submission - SIMPLIFIED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    try {
        // 1. Update basic sale info
        $update_sql = "UPDATE EASYSALLES_SALES SET 
                      customer_name = ?,
                      customer_phone = ?,
                      customer_email = ?,
                      payment_method = ?,
                      payment_status = ?,
                      notes = ?
                      WHERE sale_id = ?";
        
        $update_params = [
            $_POST['customer_name'] ?? $sale['customer_name'],
            $_POST['customer_phone'] ?? $sale['customer_phone'],
            $_POST['customer_email'] ?? $sale['customer_email'],
            $_POST['payment_method'] ?? $sale['payment_method'],
            $_POST['payment_status'] ?? $sale['payment_status'],
            $_POST['notes'] ?? $sale['notes'],
            $sale_id
        ];
        
        if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
            $update_sql .= " AND staff_id = ?";
            $update_params[] = $_SESSION['user_id'];
        }
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute($update_params);
        
        // 2. Process item updates if any
        if (isset($_POST['update_items']) && is_array($_POST['update_items'])) {
            foreach ($_POST['update_items'] as $item_id => $data) {
                $update_item_sql = "UPDATE EASYSALLES_SALE_ITEMS 
                                   SET quantity = ?, unit_price = ?, subtotal = ?
                                   WHERE item_id = ? AND sale_id = ?";
                $quantity = floatval($data['quantity'] ?? 1);
                $unit_price = floatval($data['unit_price'] ?? 0);
                $subtotal = $quantity * $unit_price;
                
                $update_item_stmt = $pdo->prepare($update_item_sql);
                $update_item_stmt->execute([$quantity, $unit_price, $subtotal, $item_id, $sale_id]);
            }
        }
        
        // 3. Process item deletions
        if (isset($_POST['delete_items']) && is_array($_POST['delete_items'])) {
            foreach ($_POST['delete_items'] as $item_id) {
                $delete_sql = "DELETE FROM EASYSALLES_SALE_ITEMS WHERE item_id = ? AND sale_id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$item_id, $sale_id]);
            }
        }
        
        // 4. Process new items
        if (isset($_POST['new_items']) && is_array($_POST['new_items'])) {
            foreach ($_POST['new_items'] as $data) {
                if (!empty($data['product_id']) && $data['product_id'] > 0) {
                    $quantity = floatval($data['quantity'] ?? 1);
                    $unit_price = floatval($data['unit_price'] ?? 0);
                    $subtotal = $quantity * $unit_price;
                    
                    $insert_sql = "INSERT INTO EASYSALLES_SALE_ITEMS 
                                  (sale_id, product_id, quantity, unit_price, subtotal)
                                  VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([$sale_id, $data['product_id'], $quantity, $unit_price, $subtotal]);
                }
            }
        }
        
        // 5. Recalculate totals
        $recalc_sql = "SELECT SUM(subtotal) as new_subtotal FROM EASYSALLES_SALE_ITEMS WHERE sale_id = ?";
        $recalc_stmt = $pdo->prepare($recalc_sql);
        $recalc_stmt->execute([$sale_id]);
        $new_subtotal = $recalc_stmt->fetchColumn() ?? 0;
        
        $discount = floatval($_POST['discount_amount'] ?? $sale['discount_amount']);
        $tax = ($new_subtotal - $discount) * 0.01;
        $final_amount = $new_subtotal - $discount + $tax;
        
        $update_totals_sql = "UPDATE EASYSALLES_SALES SET 
                             subtotal_amount = ?,
                             discount_amount = ?,
                             tax_amount = ?,
                             total_amount = ?,
                             final_amount = ?
                             WHERE sale_id = ?";
        $update_totals_stmt = $pdo->prepare($update_totals_sql);
        $update_totals_stmt->execute([
            $new_subtotal,
            $discount,
            $tax,
            $final_amount,
            $final_amount,
            $sale_id
        ]);
        
        $success = "Sale updated successfully!";
        
        // Refresh data
        $stmt->execute($params);
        $sale = $stmt->fetch();
        $items_stmt->execute([$sale_id]);
        $items = $items_stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<style>
.sale-details-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.detail-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #E2E8F0;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1E293B;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #CBD5E1;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
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

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: #D1FAE5;
    color: #065F46;
    border-left: 4px solid #10B981;
}

.alert-error {
    background: #FEE2E2;
    color: #991B1B;
    border-left: 4px solid #EF4444;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th, .items-table td {
    padding: 1rem;
    border-bottom: 1px solid #E2E8F0;
    text-align: left;
}

.items-table th {
    font-weight: 600;
    background: #F8FAFC;
}

.edit-form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #E2E8F0;
}

.input-small {
    width: 80px;
    padding: 0.5rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    text-align: center;
}
</style>

<div class="sale-details-container">
    <h1>Sale Details #<?php echo $sale_id; ?></h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" id="saleForm">
        <input type="hidden" name="save_changes" value="1">
        
        <div class="detail-card">
            <h2 class="card-title">Customer Information</h2>
            
            <input type="text" name="customer_name" class="form-control" 
                   value="<?php echo htmlspecialchars($sale['customer_name']); ?>" required
                   placeholder="Customer Name">
            
            <input type="tel" name="customer_phone" class="form-control" 
                   value="<?php echo htmlspecialchars($sale['customer_phone']); ?>"
                   placeholder="Phone Number">
            
            <input type="email" name="customer_email" class="form-control" 
                   value="<?php echo htmlspecialchars($sale['customer_email']); ?>"
                   placeholder="Email">
            
            <textarea name="notes" class="form-control" rows="3" 
                      placeholder="Notes"><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="detail-card">
            <h2 class="card-title">Payment Information</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label>Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="paid" <?php echo $sale['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $sale['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="cancelled" <?php echo $sale['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash" <?php echo $sale['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $sale['payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="mobile_money" <?php echo $sale['payment_method'] === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 1rem;">
                <label>Discount Amount ($)</label>
                <input type="number" name="discount_amount" class="form-control" 
                       value="<?php echo number_format($sale['discount_amount'], 2); ?>"
                       step="0.01" min="0">
            </div>
        </div>
        
        <div class="detail-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 class="card-title">Sale Items</h2>
                <button type="button" class="btn btn-primary" onclick="addNewItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <!-- Existing Items -->
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                <input type="hidden" name="update_items[<?php echo $item['item_id']; ?>][product_id]" value="<?php echo $item['product_id']; ?>">
                            </td>
                            <td>
                                <input type="number" name="update_items[<?php echo $item['item_id']; ?>][quantity]" 
                                       class="input-small" value="<?php echo $item['quantity']; ?>" min="1"
                                       onchange="updateRowTotal(this)">
                            </td>
                            <td>
                                <input type="number" name="update_items[<?php echo $item['item_id']; ?>][unit_price]" 
                                       class="input-small" value="<?php echo $item['unit_price']; ?>" step="0.01" min="0.01"
                                       onchange="updateRowTotal(this)">
                            </td>
                            <td class="row-subtotal" data-base="<?php echo $item['quantity'] * $item['unit_price']; ?>">
                                $<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary" onclick="deleteItem(this, <?php echo $item['item_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- New Items will be added here -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">
                            <div style="text-align: right; padding: 1rem;">
                                <div>Subtotal: <span id="totalSubtotal">$<?php echo number_format($sale['subtotal_amount'], 2); ?></span></div>
                                <div>Discount: <span id="totalDiscount">$<?php echo number_format($sale['discount_amount'], 2); ?></span></div>
                                <div>Tax (1%): <span id="totalTax">$<?php echo number_format($sale['tax_amount'], 2); ?></span></div>
                                <div style="font-weight: bold; font-size: 1.2rem;">
                                    Total: <span id="totalFinal">$<?php echo number_format($sale['final_amount'], 2); ?></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Hidden field for deleted items -->
        <div id="deletedItemsContainer"></div>
        
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            
            <a href="sale-details.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
let newItemCount = 0;
let deletedItems = [];

// Product data for JavaScript
const products = <?php echo json_encode($products); ?>;

function addNewItem() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <select name="new_items[${newItemCount}][product_id]" class="form-control" onchange="updateNewItemPrice(this)">
                <option value="">Select Product</option>
                ${products.map(p => `
                    <option value="${p.product_id}" data-price="${p.unit_price}">
                        ${p.product_name} (${p.product_code}) - $${p.unit_price}
                    </option>
                `).join('')}
            </select>
        </td>
        <td>
            <input type="number" name="new_items[${newItemCount}][quantity]" 
                   class="input-small" value="1" min="1" onchange="updateRowTotal(this)">
        </td>
        <td>
            <input type="number" name="new_items[${newItemCount}][unit_price]" 
                   class="input-small" value="0" step="0.01" min="0.01" onchange="updateRowTotal(this)">
        </td>
        <td class="row-subtotal">$0.00</td>
        <td>
            <button type="button" class="btn btn-secondary" onclick="this.closest('tr').remove(); calculateTotals();">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    newItemCount++;
}

function updateNewItemPrice(select) {
    const row = select.closest('tr');
    const priceInput = row.querySelector('input[name*="[unit_price]"]');
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || '0';
    priceInput.value = parseFloat(price).toFixed(2);
    updateRowTotal(priceInput);
}

function updateRowTotal(input) {
    const row = input.closest('tr');
    const quantityInput = row.querySelector('input[name*="[quantity]"]');
    const priceInput = row.querySelector('input[name*="[unit_price]"]');
    const subtotalCell = row.querySelector('.row-subtotal');
    
    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const subtotal = quantity * price;
    
    subtotalCell.textContent = '$' + subtotal.toFixed(2);
    calculateTotals();
}

function deleteItem(button, itemId) {
    if (confirm('Delete this item?')) {
        const row = button.closest('tr');
        row.style.display = 'none';
        
        // Add to deleted items list
        if (!deletedItems.includes(itemId)) {
            deletedItems.push(itemId);
            const container = document.getElementById('deletedItemsContainer');
            deletedItems.forEach(itemId => {
                if (!document.querySelector(`input[name="delete_items[${itemId}]"]`)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `delete_items[${itemId}]`;
                    input.value = itemId;
                    container.appendChild(input);
                }
            });
        }
        
        calculateTotals();
    }
}

function calculateTotals() {
    let subtotal = 0;
    
    // Calculate from visible rows
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        if (row.style.display !== 'none') {
            const subtotalText = row.querySelector('.row-subtotal').textContent;
            const rowSubtotal = parseFloat(subtotalText.replace('$', '')) || 0;
            subtotal += rowSubtotal;
        }
    });
    
    const discount = parseFloat(document.querySelector('input[name="discount_amount"]').value) || 0;
    const tax = (subtotal - discount) * 0.01;
    const total = subtotal - discount + tax;
    
    document.getElementById('totalSubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('totalDiscount').textContent = '$' + discount.toFixed(2);
    document.getElementById('totalTax').textContent = '$' + tax.toFixed(2);
    document.getElementById('totalFinal').textContent = '$' + total.toFixed(2);
}

// Initialize calculations
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('input[name="discount_amount"]').addEventListener('input', calculateTotals);
    calculateTotals();
});

// Simple form validation
document.getElementById('saleForm').addEventListener('submit', function(e) {
    if (!confirm('Save all changes?')) {
        e.preventDefault();
        return false;
    }
    return true;
});
</script>

<?php include 'includes/footer.php'; ?>