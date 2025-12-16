<?php
// admin/reports/products.php
$page_title = "Product Reports";
require_once '../includes/header.php';

// Get report parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Build query for product report
$query = "
    SELECT 
        product_id,
        product_name,
        product_code,
        category,
        unit_price,
        cost_price,
        current_stock,
        min_stock,
        max_stock,
        unit_type,
        supplier,
        status,
        created_at
    FROM EASYSALLES_PRODUCTS 
    WHERE 1=1
";

$params = [];

if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
}

if ($status && in_array($status, ['active', 'inactive', 'discontinued'])) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY product_name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Get categories for filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM EASYSALLES_PRODUCTS WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Calculate totals
$totals = [
    'total_products' => count($products),
    'total_stock' => 0,
    'total_value' => 0,
    'low_stock' => 0
];

foreach ($products as $product) {
    $totals['total_stock'] += $product['current_stock'];
    $totals['total_value'] += $product['current_stock'] * $product['unit_price'];
    if ($product['current_stock'] <= $product['min_stock']) {
        $totals['low_stock']++;
    }
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
                <h2>Product Reports</h2>
                <p>Generate and analyze product inventory reports</p>
            </div>
            <div class="page-actions">
                <a href="../products/index.php" class="btn btn-outline">
                    ← Back to Products
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Print Report
                </button>
                <button onclick="exportToExcel()" class="btn btn-primary">
                    📊 Export to Excel
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Report Filters</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="GET" action="" class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                        <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label class="form-label">Actions</label>
                            <div>
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    🔍 Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <a href="products.php" class="btn btn-outline" style="width: 100%;">
                                    🔄 Reset Filters
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row" style="margin-bottom: 1.5rem;">
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--primary); margin: 0;"><?php echo $totals['total_products']; ?></h1>
                        <small class="text-muted">Total Products</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--success); margin: 0;"><?php echo $totals['total_stock']; ?></h1>
                        <small class="text-muted">Total Stock</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--warning); margin: 0;">$<?php echo number_format($totals['total_value'], 2); ?></h1>
                        <small class="text-muted">Total Value</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card" style="text-align: center;">
                    <div style="padding: 1.5rem;">
                        <h1 style="color: var(--error); margin: 0;"><?php echo $totals['low_stock']; ?></h1>
                        <small class="text-muted">Low Stock Items</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Report Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Product Inventory Report</h3>
                <small class="text-muted">
                    Generated on: <?php echo date('F d, Y'); ?>
                    <?php if ($category): ?> | Category: <?php echo htmlspecialchars($category); ?><?php endif; ?>
                    <?php if ($status): ?> | Status: <?php echo ucfirst($status); ?><?php endif; ?>
                </small>
            </div>
            <div style="padding: 1.5rem; overflow-x: auto;">
                <?php if (empty($products)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <span style="font-size: 3rem; color: var(--text-light); opacity: 0.5;">📊</span>
                        <h3 style="margin: 1rem 0;">No Products Found</h3>
                        <p class="text-muted">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Cost</th>
                                <th>Stock</th>
                                <th>Value</th>
                                <th>Supplier</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $stock_value = $product['current_stock'] * $product['unit_price'];
                                $stock_status = $product['current_stock'] <= $product['min_stock'] ? 'error' : 
                                             ($product['current_stock'] >= $product['max_stock'] ? 'success' : 'warning');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                                <td>$<?php echo number_format($product['unit_price'], 2); ?></td>
                                <td>
                                    <?php if ($product['cost_price']): ?>
                                        $<?php echo number_format($product['cost_price'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="badge badge-<?php echo $stock_status; ?>">
                                            <?php echo $product['current_stock']; ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo $product['unit_type']; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($stock_value, 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($product['supplier']): ?>
                                        <?php echo htmlspecialchars($product['supplier']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No supplier</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $product['status'] == 'active' ? 'badge-success' : 
                                                       ($product['status'] == 'inactive' ? 'badge-warning' : 'badge-error'); ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Total Row -->
                            <tr style="background: var(--bg); font-weight: bold;">
                                <td colspan="5">TOTALS</td>
                                <td><?php echo $totals['total_stock']; ?></td>
                                <td>$<?php echo number_format($totals['total_value'], 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function exportToExcel() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (const row of rows) {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                
                for (const col of cols) {
                    // Get text content, remove any buttons/badges
                    let text = col.innerText;
                    // Clean up the text
                    text = text.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                    rowData.push(text);
                }
                
                csv.push(rowData.join(','));
            }
            
            const csvString = csv.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'product_report_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            alert('Report exported successfully!');
        }
        
        // Auto-generate report on page load if filters are set
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('category') || urlParams.has('status')) {
                console.log('Report generated with filters');
            }
        });
    </script>

    <style>
        @media print {
            .page-actions, .btn, .card-header small {
                display: none !important;
            }
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
            .table th {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>
