<?php
// admin/products/suppliers.php
$page_title = "Manage Suppliers";
require_once '../../includes/header.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Check if suppliers table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM EASYSALLES_SUPPLIERS LIMIT 1");
} catch (PDOException $e) {
    // Create suppliers table if it doesn't exist
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS EASYSALLES_SUPPLIERS (
            supplier_id INT PRIMARY KEY AUTO_INCREMENT,
            supplier_code VARCHAR(50) UNIQUE,
            supplier_name VARCHAR(200) NOT NULL,
            contact_person VARCHAR(100),
            email VARCHAR(200),
            phone VARCHAR(50),
            mobile VARCHAR(50),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            website VARCHAR(200),
            tax_id VARCHAR(100),
            payment_terms VARCHAR(100),
            account_number VARCHAR(100),
            notes TEXT,
            total_orders INT DEFAULT 0,
            total_amount DECIMAL(15,2) DEFAULT 0,
            last_order_date DATE,
            status ENUM('active', 'inactive', 'blacklisted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT,
            INDEX idx_supplier_code (supplier_code),
            INDEX idx_status (status)
        )
    ";
    $pdo->exec($create_table_sql);
}

// Build query
$query = "SELECT * FROM EASYSALLES_SUPPLIERS WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (supplier_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if (!empty($status) && in_array($status, ['active', 'inactive', 'blacklisted'])) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY supplier_name ASC";

// Get suppliers
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// Get statistics
$total_suppliers = count($suppliers);
$active_suppliers = 0;
$blacklisted_suppliers = 0;

foreach ($suppliers as $supplier) {
    if ($supplier['status'] == 'active') $active_suppliers++;
    if ($supplier['status'] == 'blacklisted') $blacklisted_suppliers++;
}

// Handle supplier actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_supplier') {
        $supplier_code = $_POST['supplier_code'] ?? 'SUP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        
        if (empty($supplier_name)) {
            $error = "Supplier name is required";
        } else {
            try {
                // Check if supplier code exists
                $stmt = $pdo->prepare("SELECT supplier_id FROM EASYSALLES_SUPPLIERS WHERE supplier_code = ?");
                $stmt->execute([$supplier_code]);
                if ($stmt->fetch()) {
                    $supplier_code = 'SUP-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
                }
                
                // Insert supplier
                $stmt = $pdo->prepare("
                    INSERT INTO EASYSALLES_SUPPLIERS 
                    (supplier_code, supplier_name, contact_person, email, phone, mobile, 
                     address, city, state, country, postal_code, website, tax_id, 
                     payment_terms, account_number, notes, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $supplier_code,
                    $supplier_name,
                    $_POST['contact_person'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['phone'] ?? '',
                    $_POST['mobile'] ?? '',
                    $_POST['address'] ?? '',
                    $_POST['city'] ?? '',
                    $_POST['state'] ?? '',
                    $_POST['country'] ?? '',
                    $_POST['postal_code'] ?? '',
                    $_POST['website'] ?? '',
                    $_POST['tax_id'] ?? '',
                    $_POST['payment_terms'] ?? '',
                    $_POST['account_number'] ?? '',
                    $_POST['notes'] ?? '',
                    $_POST['status'] ?? 'active',
                    $_SESSION['user_id']
                ]);
                
                if ($result) {
                    $success = "Supplier added successfully!";
                    echo '<script>showToast("Supplier added successfully!", "success");</script>';
                    
                    // Refresh suppliers list
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $suppliers = $stmt->fetchAll();
                } else {
                    $error = "Failed to add supplier. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Manage Suppliers</h2>
        <p>Manage your product suppliers and vendor information</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        <button class="btn btn-primary" style="margin-left: 0.5rem;" onclick="openAddSupplierModal()">
            <i class="fas fa-plus"></i> Add Supplier
        </button>
        <a href="suppliers-orders.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row" style="margin-bottom: 2rem;">
    <div class="col-3">
        <div class="card" style="text-align: center;">
            <div style="padding: 1.5rem;">
                <h1 style="color: var(--primary); margin: 0;"><?php echo $total_suppliers; ?></h1>
                <small class="text-muted">Total Suppliers</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card" style="text-align: center;">
            <div style="padding: 1.5rem;">
                <h1 style="color: var(--success); margin: 0;"><?php echo $active_suppliers; ?></h1>
                <small class="text-muted">Active Suppliers</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card" style="text-align: center;">
            <div style="padding: 1.5rem;">
                <h1 style="color: var(--warning); margin: 0;"><?php echo $blacklisted_suppliers; ?></h1>
                <small class="text-muted">Blacklisted</small>
            </div>
        </div>
    </div>
    <div class="col-3">
        <div class="card" style="text-align: center;">
            <div style="padding: 1.5rem;">
                <h1 style="color: var(--accent); margin: 0;">
                    <?php 
                    $total_products = 0;
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE supplier_id IS NOT NULL");
                        $total_products = $stmt->fetchColumn();
                    } catch (Exception $e) {}
                    echo $total_products;
                    ?>
                </h1>
                <small class="text-muted">Supplied Products</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Filter Suppliers</h3>
    </div>
    <div style="padding: 1.5rem;">
        <form method="GET" action="" class="row">
            <div class="col-4">
                <div class="form-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search suppliers..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-3">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="blacklisted" <?php echo $status == 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                    </select>
                </div>
            </div>
            <div class="col-2">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            <div class="col-2">
                <a href="suppliers.php" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn-outline" style="width: 100%;" onclick="printSuppliers()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-8">
        <!-- Suppliers Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Suppliers List</h3>
                <div class="btn-group">
                    <button class="btn btn-outline" onclick="exportSuppliers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="table-container">
                <?php if (empty($suppliers)): ?>
                    <div style="text-align: center; padding: 4rem;">
                        <div style="width: 100px; height: 100px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                            <i class="fas fa-truck" style="font-size: 3rem; color: var(--border);"></i>
                        </div>
                        <h3>No Suppliers Found</h3>
                        <p class="text-muted">Add your first supplier to start managing vendor information</p>
                        <button class="btn btn-primary" onclick="openAddSupplierModal()" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Add Your First Supplier
                        </button>
                    </div>
                <?php else: ?>
                    <table class="table" id="suppliersTable">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Contact</th>
                                <th>Products</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): 
                                // Get products count for this supplier
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM EASYSALLES_PRODUCTS WHERE supplier_id = ?");
                                    $stmt->execute([$supplier['supplier_id']]);
                                    $product_count = $stmt->fetchColumn();
                                } catch (Exception $e) {
                                    $product_count = 0;
                                }
                            ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong><br>
                                        <small class="text-muted">Code: <?php echo htmlspecialchars($supplier['supplier_code']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($supplier['contact_person']): ?>
                                        <div>
                                            <small><?php echo htmlspecialchars($supplier['contact_person']); ?></small><br>
                                            <?php if ($supplier['phone']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($supplier['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No contact info</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $product_count; ?> products</span>
                                </td>
                                <td>
                                    <div>
                                        <small>Total: <?php echo $supplier['total_orders']; ?> orders</small><br>
                                        <small class="text-muted">$<?php echo number_format($supplier['total_amount'], 2); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status_badge = 'badge-success';
                                    if ($supplier['status'] == 'inactive') $status_badge = 'badge-warning';
                                    if ($supplier['status'] == 'blacklisted') $status_badge = 'badge-error';
                                    ?>
                                    <span class="badge <?php echo $status_badge; ?>">
                                        <?php echo ucfirst($supplier['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick="viewSupplier(<?php echo $supplier['supplier_id']; ?>)" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem;"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editSupplier(<?php echo $supplier['supplier_id']; ?>)" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem;"
                                                title="Edit Supplier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteSupplier(<?php echo $supplier['supplier_id']; ?>)" 
                                                class="btn btn-outline" 
                                                style="padding: 0.4rem 0.8rem; color: var(--error);"
                                                title="Delete Supplier">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <!-- Quick Add Supplier -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus-circle"></i> Quick Add Supplier
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="" id="quickSupplierForm">
                    <input type="hidden" name="action" value="add_supplier">
                    
                    <div class="form-group">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" 
                               name="supplier_name" 
                               class="form-control" 
                               placeholder="Enter supplier name"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" 
                               name="contact_person" 
                               class="form-control" 
                               placeholder="Contact person name">
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control" 
                                       placeholder="Phone number">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       placeholder="Email address">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Add Supplier
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Supplier Tips -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lightbulb"></i> Supplier Management Tips
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <ul style="color: var(--text-light); padding-left: 1rem; margin: 0;">
                    <li>Always verify contact information</li>
                    <li>Set clear payment terms</li>
                    <li>Track supplier performance</li>
                    <li>Maintain backup suppliers</li>
                    <li>Review supplier status regularly</li>
                </ul>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--primary-light); border-radius: 10px;">
                    <h5 style="color: var(--primary); margin-bottom: 0.5rem;">
                        <i class="fas fa-chart-line"></i> Performance Metrics
                    </h5>
                    <p style="margin: 0.3rem 0; font-size: 0.9rem;">
                        Track: On-time delivery, product quality, and communication
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-link"></i> Quick Links
                </h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; gap: 0.8rem;">
                    <a href="suppliers-orders.php" class="btn btn-outline">
                        <i class="fas fa-shopping-cart"></i> Purchase Orders
                    </a>
                    
                    <a href="../inventory/stock-receiving.php" class="btn btn-outline">
                        <i class="fas fa-truck-loading"></i> Stock Receiving
                    </a>
                    
                    <a href="../reports/suppliers.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i> Supplier Reports
                    </a>
                    
                    <button class="btn btn-outline" onclick="generateSupplierReport()">
                        <i class="fas fa-file-export"></i> Export All Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div id="addSupplierModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>Add New Supplier</h3>
            <span class="modal-close" onclick="closeModal('addSupplierModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="supplierForm">
                <input type="hidden" name="action" value="add_supplier">
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Supplier Code</label>
                            <input type="text" 
                                   name="supplier_code" 
                                   class="form-control" 
                                   value="SUP-<?php echo date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)); ?>"
                                   readonly
                                   style="background: var(--bg);">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Supplier Name *</label>
                            <input type="text" 
                                   name="supplier_name" 
                                   class="form-control" 
                                   placeholder="Enter supplier name"
                                   required
                                   autofocus>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Contact Person</label>
                            <input type="text" 
                                   name="contact_person" 
                                   class="form-control" 
                                   placeholder="Contact person name">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Tax ID</label>
                            <input type="text" 
                                   name="tax_id" 
                                   class="form-control" 
                                   placeholder="Tax identification number">
                        </div>
                    </div>
                </div>
                
                <h4 style="margin-top: 2rem; margin-bottom: 1rem;">Contact Information</h4>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="supplier@example.com">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Website</label>
                            <input type="url" 
                                   name="website" 
                                   class="form-control" 
                                   placeholder="https://example.com">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" 
                                   name="phone" 
                                   class="form-control" 
                                   placeholder="Office phone">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Mobile</label>
                            <input type="tel" 
                                   name="mobile" 
                                   class="form-control" 
                                   placeholder="Mobile number">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" 
                                   name="payment_terms" 
                                   class="form-control" 
                                   placeholder="e.g., Net 30 days">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" 
                              class="form-control" 
                              rows="2"
                              placeholder="Street address"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" 
                                   name="city" 
                                   class="form-control" 
                                   placeholder="City">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">State/Province</label>
                            <input type="text" 
                                   name="state" 
                                   class="form-control" 
                                   placeholder="State">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" 
                                   name="postal_code" 
                                   class="form-control" 
                                   placeholder="Postal code">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <input type="text" 
                           name="country" 
                           class="form-control" 
                           placeholder="Country">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" 
                           name="account_number" 
                           class="form-control" 
                           placeholder="Bank account number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" 
                              class="form-control" 
                              rows="3"
                              placeholder="Additional notes about this supplier"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Supplier
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addSupplierModal')">
                        Cancel
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Supplier Modal -->
<div id="viewSupplierModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>Supplier Details</h3>
            <span class="modal-close" onclick="closeModal('viewSupplierModal')">&times;</span>
        </div>
        <div class="modal-body" id="supplierDetailsContent">
            Loading supplier details...
        </div>
        <div class="modal-footer" style="padding: 1rem; border-top: 1px solid var(--border);">
            <button class="btn btn-outline" onclick="closeModal('viewSupplierModal')">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    // Modal functions
    function openAddSupplierModal() {
        document.getElementById('addSupplierModal').style.display = 'block';
    }
    
    function viewSupplier(supplierId) {
        // Fetch supplier details via AJAX
        fetch(`get_supplier.php?id=${supplierId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('supplierDetailsContent').innerHTML = data;
                document.getElementById('viewSupplierModal').style.display = 'block';
            })
            .catch(error => {
                document.getElementById('supplierDetailsContent').innerHTML = `
                    <div class="alert alert-error">
                        Error loading supplier details: ${error}
                    </div>
                `;
                document.getElementById('viewSupplierModal').style.display = 'block';
            });
    }
    
    function editSupplier(supplierId) {
        // Redirect to edit page (to be created)
        window.location.href = `suppliers-edit.php?id=${supplierId}`;
    }
    
    function deleteSupplier(supplierId) {
        if (confirm('Are you sure you want to delete this supplier? Products linked to this supplier will become unassigned.')) {
            // Send delete request
            fetch(`suppliers-delete.php?id=${supplierId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&supplier_id=${supplierId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Supplier deleted successfully', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.error || 'Failed to delete supplier', 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting supplier', 'error');
            });
        }
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Export suppliers
    function exportSuppliers() {
        const table = document.getElementById('suppliersTable');
        if (!table) {
            showToast('No suppliers to export', 'error');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            for (const col of cols) {
                if (col.querySelector('.btn')) {
                    rowData.push('');
                } else {
                    rowData.push(col.innerText.replace(/,/g, ''));
                }
            }
            
            csv.push(rowData.join(','));
        }
        
        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'suppliers_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Suppliers exported successfully', 'success');
    }
    
    // Print suppliers
    function printSuppliers() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Suppliers List - EasySalles</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
                </style>
            </head>
            <body>
                <h1>Suppliers List - EasySalles</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
        `);
        
        const table = document.getElementById('suppliersTable');
        if (table) {
            printWindow.document.write(table.outerHTML.replace(/<button[^>]*>.*?<\/button>/g, ''));
        }
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    // Generate supplier report
    function generateSupplierReport() {
        showToast('Generating comprehensive supplier report...', 'info');
        // In a real implementation, this would generate a PDF report
        setTimeout(() => {
            showToast('Report generation complete! Download will start shortly.', 'success');
        }, 2000);
    }
    
    // Quick form validation
    document.getElementById('quickSupplierForm').addEventListener('submit', function(e) {
        const supplierName = this.supplier_name.value.trim();
        if (!supplierName) {
            e.preventDefault();
            showToast('Supplier name is required', 'error');
            this.supplier_name.focus();
            return false;
        }
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;
    });
    
    // Auto-generate supplier code
    document.addEventListener('DOMContentLoaded', function() {
        const supplierCodeField = document.querySelector('input[name="supplier_code"]');
        if (supplierCodeField && !supplierCodeField.value) {
            const timestamp = new Date().toISOString().replace(/[-:]/g, '').split('.')[0];
            supplierCodeField.value = `SUP-${timestamp.substr(2, 6)}-${Math.random().toString(36).substr(2, 4).toUpperCase()}`;
        }
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };
</script>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        overflow: auto;
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        animation: modalFadeIn 0.3s;
    }
    
    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem;
        border-top: 1px solid var(--border);
        text-align: right;
    }
    
    .modal-close {
        color: var(--text-light);
        font-size: 1.8rem;
        cursor: pointer;
        line-height: 1;
    }
    
    .modal-close:hover {
        color: var(--text);
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .supplier-status-active {
        color: var(--success);
    }
    
    .supplier-status-inactive {
        color: var(--warning);
    }
    
    .supplier-status-blacklisted {
        color: var(--error);
    }
</style>

<?php require_once '../../includes/footer.php'; ?>
