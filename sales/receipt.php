<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: new_sale.php');
    exit();
}

$sale_id = intval($_GET['id']);

// Get sale details
$sale = Database::query(
    "SELECT s.*, u.full_name as cashier_name 
     FROM easysalles_sales s 
     LEFT JOIN easysalles_users u ON s.user_id = u.id 
     WHERE s.id = ?",
    [$sale_id]
)->fetch();

if (!$sale) {
    header('Location: sales_list.php');
    exit();
}

// Get sale items
$items = Database::query(
    "SELECT si.*, p.name as product_name, p.product_code 
     FROM easysalles_sale_items si 
     JOIN easysalles_products p ON si.product_id = p.id 
     WHERE si.sale_id = ?",
    [$sale_id]
)->fetchAll();

// Get company settings
$settings = Database::query(
    "SELECT setting_key, setting_value FROM easysalles_settings"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .receipt-container {
            max-width: 400px;
            margin: 2rem auto;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px dashed var(--border);
            padding-bottom: 1rem;
        }
        
        .company-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .receipt-details {
            margin-bottom: 2rem;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .receipt-row.total {
            border-top: 2px solid var(--text);
            border-bottom: none;
            font-weight: 700;
            font-size: 1.125rem;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        
        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        .receipt-items th {
            text-align: left;
            padding: 0.5rem;
            border-bottom: 2px solid var(--border);
            font-weight: 600;
        }
        
        .receipt-items td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .receipt-items tr:last-child td {
            border-bottom: none;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px dashed var(--border);
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 1rem;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="company-name"><?php echo APP_NAME; ?></div>
            <div><?php echo $settings['company_address'] ?? '123 Business Street'; ?></div>
            <div>Tel: <?php echo $settings['company_phone'] ?? '+1234567890'; ?></div>
        </div>
        
        <!-- Sale Details -->
        <div class="receipt-details">
            <div class="receipt-row">
                <span>Receipt #:</span>
                <span><strong><?php echo $sale['transaction_id']; ?></strong></span>
            </div>
            <div class="receipt-row">
                <span>Date:</span>
                <span><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="receipt-row">
                <span>Time:</span>
                <span><?php echo date('h:i A', strtotime($sale['sale_time'])); ?></span>
            </div>
            <div class="receipt-row">
                <span>Cashier:</span>
                <span><?php echo htmlspecialchars($sale['cashier_name']); ?></span>
            </div>
        </div>
        
        <!-- Items -->
        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo formatCurrency($item['unit_price']); ?></td>
                    <td><?php echo formatCurrency($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="receipt-details">
            <div class="receipt-row">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($sale['total_amount']); ?></span>
            </div>
            <?php if ($sale['tax_amount'] > 0): ?>
            <div class="receipt-row">
                <span>Tax:</span>
                <span><?php echo formatCurrency($sale['tax_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($sale['discount_amount'] > 0): ?>
            <div class="receipt-row">
                <span>Discount:</span>
                <span>-<?php echo formatCurrency($sale['discount_amount']); ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row total">
                <span>TOTAL:</span>
                <span><?php echo formatCurrency($sale['final_amount']); ?></span>
            </div>
            <div class="receipt-row">
                <span>Payment:</span>
                <span><?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="receipt-footer">
            <div>Thank you for your purchase!</div>
            <div>Please retain this receipt for your records</div>
            <div style="margin-top: 1rem;">Powered by <?php echo APP_NAME; ?></div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <svg class="icon" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                Print Receipt
            </button>
            <a href="new_sale.php" class="btn btn-secondary">
                <svg class="icon" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                New Sale
            </a>
            <a href="../dashboard.php" class="btn btn-secondary">
                <svg class="icon" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                Dashboard
            </a>
        </div>
    </div>
    
    <script>
    // Auto print option
    window.onload = function() {
        // Uncomment to auto-print on page load
        // setTimeout(function() { window.print(); }, 1000);
    };
    
    // Add keyboard shortcut for print (Ctrl+P)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
    </script>
</body>
</html>
